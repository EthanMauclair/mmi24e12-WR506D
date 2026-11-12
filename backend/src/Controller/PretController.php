<?php

namespace App\Controller;

use App\Api\Presentateur;
use App\Entity\Article;
use App\Entity\Pret;
use App\Entity\Utilisateur;
use App\Enum\StatutArticle;
use App\Repository\PretRepository;
use App\Repository\UtilisateurRepository;
use App\Security\Voter\ArticleVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Suivi des prêts : à qui un article est prêté (un membre ou un simple nom), quand, et quand il revient.
 * Prêter un article demande le droit de le modifier (celui qui l'a ajouté, ou le propriétaire du logement).
 */
#[Route('/api')]
final class PretController extends ApiController
{
    public function __construct(private readonly Presentateur $presentateur)
    {
    }

    /**
     * Prêts consentis par le membre connecté, en cours et rendus.
     */
    #[Route('/prets', name: 'api_prets', methods: ['GET'])]
    public function lister(#[CurrentUser] Utilisateur $utilisateur, PretRepository $prets): JsonResponse
    {
        $parStatut = $prets->findDuPreteur($utilisateur);

        return $this->json([
            'enCours' => $this->presentateur->prets($parStatut['enCours']),
            'rendus' => $this->presentateur->prets($parStatut['rendus']),
        ]);
    }

    /**
     * Prête l'article. Corps : emprunteur (pseudo d'un membre) ou emprunteurNom (simple nom), datePret
     * (AAAA-MM-JJ, aujourd'hui par défaut), dateRetourPrevue et note facultatives.
     */
    #[Route('/articles/{id}/prets', name: 'api_article_preter', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ArticleVoter::MODIFIER, 'article')]
    public function preter(
        #[MapEntity(id: 'id')] Article $article,
        #[CurrentUser] Utilisateur $utilisateur,
        Request $request,
        UtilisateurRepository $utilisateurs,
        ValidatorInterface $validator,
        EntityManagerInterface $em,
    ): JsonResponse {
        if (\in_array($article->getStatut(), [StatutArticle::EN_VENTE, StatutArticle::VENDU], true)) {
            return $this->reponseInvalide(['general' => 'Un article en vente ou vendu ne peut pas être prêté.']);
        }

        $donnees = $this->lireJson($request);
        $texte = static fn (string $cle): ?string => \is_string($donnees[$cle] ?? null) && '' !== trim($donnees[$cle]) ? trim($donnees[$cle]) : null;
        $pret = (new Pret())->setArticle($article)->setPreteur($utilisateur)->setNote($texte('note'));

        if (null !== $pseudo = $texte('emprunteur')) {
            $emprunteur = $utilisateurs->findOneBy(['pseudo' => ltrim($pseudo, '@')]);
            if (null === $emprunteur) {
                return $this->reponseInvalide(['emprunteur' => 'Aucun membre ne porte ce pseudo.']);
            }
            if ($emprunteur === $utilisateur) {
                return $this->reponseInvalide(['emprunteur' => 'Vous ne pouvez pas vous prêter un article.']);
            }
            $pret->setEmprunteur($emprunteur);
        } elseif (null !== $nom = $texte('emprunteurNom')) {
            $pret->setEmprunteurNom($nom);
        } else {
            return $this->reponseInvalide(['emprunteurNom' => 'Indiquez à qui vous prêtez l\'article.']);
        }

        $dates = [];
        foreach (['datePret', 'dateRetourPrevue'] as $champ) {
            $valeur = $texte($champ);
            $dates[$champ] = null === $valeur ? null : \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);
            if (false === $dates[$champ]) {
                return $this->reponseInvalide([$champ => 'Date invalide.']);
            }
        }
        $aujourdhui = new \DateTimeImmutable('today');
        if (null !== $dates['datePret'] && $dates['datePret'] > $aujourdhui) {
            return $this->reponseInvalide(['datePret' => 'La date de prêt ne peut pas être dans le futur.']);
        }
        $pret->setDatePret($dates['datePret'] ?? $aujourdhui)->setDateRetourPrevue($dates['dateRetourPrevue']);

        $messages = $this->messagesParChamp($validator->validate($pret), ['article' => 'general']);
        if ([] !== $messages) {
            return $this->reponseInvalide($messages);
        }

        $article->setStatut(StatutArticle::PRETE);
        $em->persist($pret);
        $em->flush();

        return $this->json($this->presentateur->prets([$pret])[0], Response::HTTP_CREATED);
    }

    /**
     * Marque le prêt comme rendu aujourd'hui : l'article redevient disponible.
     */
    #[Route('/prets/{id}/retour', name: 'api_pret_retour', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function retour(#[MapEntity(id: 'id')] Pret $pret, #[CurrentUser] Utilisateur $utilisateur, EntityManagerInterface $em): JsonResponse
    {
        $this->verifierPreteur($pret, $utilisateur);
        if (!$pret->estEnCours()) {
            return $this->reponseInvalide(['general' => 'Ce prêt est déjà marqué comme rendu.']);
        }

        $pret->setDateRetourReelle(new \DateTimeImmutable('today'));
        if (StatutArticle::PRETE === $pret->getArticle()->getStatut()) {
            $pret->getArticle()->setStatut(StatutArticle::DISPONIBLE);
        }
        $em->flush();

        return $this->json($this->presentateur->prets([$pret])[0]);
    }

    /**
     * Supprime un prêt saisi par erreur ; s'il était en cours, l'article redevient disponible.
     */
    #[Route('/prets/{id}', name: 'api_pret_supprimer', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function supprimer(#[MapEntity(id: 'id')] Pret $pret, #[CurrentUser] Utilisateur $utilisateur, EntityManagerInterface $em): JsonResponse
    {
        $this->verifierPreteur($pret, $utilisateur);
        if ($pret->estEnCours() && StatutArticle::PRETE === $pret->getArticle()->getStatut()) {
            $pret->getArticle()->setStatut(StatutArticle::DISPONIBLE);
        }
        $em->remove($pret);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Le prêteur, ou toute personne qui peut modifier l'article (le propriétaire du logement).
     */
    private function verifierPreteur(Pret $pret, Utilisateur $utilisateur): void
    {
        if ($pret->getPreteur() !== $utilisateur && !$this->isGranted(ArticleVoter::MODIFIER, $pret->getArticle())) {
            throw $this->createAccessDeniedException('Seul le prêteur peut modifier ce prêt.');
        }
    }
}
