<?php

namespace App\Controller;

use App\Api\Presentateur;
use App\Entity\Abonnement;
use App\Entity\Utilisateur;
use App\Enum\StatutAbonnement;
use App\Repository\AbonnementRepository;
use App\Repository\ArticleRepository;
use App\Repository\UtilisateurRepository;
use App\Security\Voter\ProfilVoter;
use App\Service\Social\Relations;
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
 * Réseau social : recherche de membres, profils, abonnements, demandes et amis.
 * Les routes de consultation (GET) sont ouvertes aux visiteurs sans compte (voir security.yaml).
 */
#[Route('/api')]
final class ProfilController extends ApiController
{
    private const PSEUDO = '[a-zA-Z0-9._-]+';

    public function __construct(
        private readonly Presentateur $presentateur,
        private readonly Relations $relations,
        private readonly AbonnementRepository $abonnements,
    ) {
    }

    #[Route('/membres', name: 'api_membres', methods: ['GET'])]
    public function rechercher(Request $request, UtilisateurRepository $utilisateurs): JsonResponse
    {
        $texte = trim((string) $request->query->get('q', ''));
        if (mb_strlen($texte) < 2) {
            return $this->json([]);
        }

        return $this->json(array_map($this->presentateur->membre(...), $utilisateurs->rechercher($texte)));
    }

    #[Route('/profils/{pseudo}', name: 'api_profil', requirements: ['pseudo' => self::PSEUDO], methods: ['GET'])]
    public function profil(
        #[MapEntity(mapping: ['pseudo' => 'pseudo'])] Utilisateur $membre,
        #[CurrentUser] ?Utilisateur $visiteur,
        ArticleRepository $articles,
    ): JsonResponse {
        return $this->json($this->presenterProfil($membre, $visiteur, $articles));
    }

    #[Route('/profils/{pseudo}/articles', name: 'api_profil_articles', requirements: ['pseudo' => self::PSEUDO], methods: ['GET'])]
    #[IsGranted(ProfilVoter::VOIR_CONTENU, 'membre')]
    public function articles(#[MapEntity(mapping: ['pseudo' => 'pseudo'])] Utilisateur $membre, ArticleRepository $articles): JsonResponse
    {
        return $this->json($this->presentateur->articlesDuProfil($articles->findDuProfil($membre)));
    }

    /**
     * Abonnés acceptés. Sur son propre profil, on voit en plus lesquels sont des amis.
     */
    #[Route('/profils/{pseudo}/abonnes', name: 'api_profil_abonnes', requirements: ['pseudo' => self::PSEUDO], methods: ['GET'])]
    #[IsGranted(ProfilVoter::VOIR_CONTENU, 'membre')]
    public function abonnes(#[MapEntity(mapping: ['pseudo' => 'pseudo'])] Utilisateur $membre, #[CurrentUser] ?Utilisateur $visiteur): JsonResponse
    {
        $estMoi = $visiteur === $membre;

        return $this->json(array_map(
            fn (Abonnement $abonnement) => $this->presentateur->membre($abonnement->getAbonne())
                + ($estMoi ? ['estAmi' => $abonnement->isEstAmi()] : []),
            $this->abonnements->findAbonnes($membre),
        ));
    }

    #[Route('/profils/{pseudo}/abonnements', name: 'api_profil_abonnements', requirements: ['pseudo' => self::PSEUDO], methods: ['GET'])]
    #[IsGranted(ProfilVoter::VOIR_CONTENU, 'membre')]
    public function abonnementsDuMembre(#[MapEntity(mapping: ['pseudo' => 'pseudo'])] Utilisateur $membre): JsonResponse
    {
        return $this->json(array_map(
            fn (Abonnement $abonnement) => $this->presentateur->membre($abonnement->getSuivi()),
            $this->abonnements->findAbonnements($membre),
        ));
    }

    /**
     * S'abonne au membre : abonnement immédiat pour un profil public, demande en attente pour un profil privé.
     */
    #[Route('/profils/{pseudo}/abonnement', name: 'api_profil_abonner', requirements: ['pseudo' => self::PSEUDO], methods: ['POST'])]
    public function abonner(
        #[MapEntity(mapping: ['pseudo' => 'pseudo'])] Utilisateur $membre,
        #[CurrentUser] Utilisateur $utilisateur,
        ValidatorInterface $validator,
        EntityManagerInterface $em,
        ArticleRepository $articles,
    ): JsonResponse {
        if (null === $this->abonnements->entre($utilisateur, $membre)) {
            $abonnement = (new Abonnement())->setAbonne($utilisateur)->setSuivi($membre);
            $messages = $this->messagesParChamp($validator->validate($abonnement));
            if ([] !== $messages) {
                return $this->reponseInvalide($messages);
            }
            $em->persist($abonnement);
            $em->flush();
        }

        return $this->json($this->presenterProfil($membre, $utilisateur, $articles), Response::HTTP_CREATED);
    }

    /**
     * Se désabonne, ou annule une demande en attente.
     */
    #[Route('/profils/{pseudo}/abonnement', name: 'api_profil_desabonner', requirements: ['pseudo' => self::PSEUDO], methods: ['DELETE'])]
    public function desabonner(
        #[MapEntity(mapping: ['pseudo' => 'pseudo'])] Utilisateur $membre,
        #[CurrentUser] Utilisateur $utilisateur,
        EntityManagerInterface $em,
        ArticleRepository $articles,
    ): JsonResponse {
        if (null !== $abonnement = $this->abonnements->entre($utilisateur, $membre)) {
            $em->remove($abonnement);
            $em->flush();
        }

        return $this->json($this->presenterProfil($membre, $utilisateur, $articles));
    }

    /**
     * Modifie son profil. Un profil qui devient public accepte toutes les demandes en attente.
     */
    #[Route('/moi/profil', name: 'api_moi_profil', methods: ['PUT'])]
    public function modifierProfil(
        Request $request,
        #[CurrentUser] Utilisateur $utilisateur,
        ValidatorInterface $validator,
        EntityManagerInterface $em,
    ): JsonResponse {
        $donnees = $this->lireJson($request);
        $texte = static fn (string $cle): ?string => \is_string($donnees[$cle] ?? null) && '' !== trim($donnees[$cle]) ? trim($donnees[$cle]) : null;

        if (\array_key_exists('estPrive', $donnees) && !\is_bool($donnees['estPrive'])) {
            return $this->reponseInvalide(['estPrive' => 'Cette valeur doit être vraie ou fausse.']);
        }
        $devientPublic = $utilisateur->isEstPrive() && false === ($donnees['estPrive'] ?? true);

        $utilisateur
            ->setPseudo($texte('pseudo') ?? '')
            ->setPrenom($texte('prenom'))
            ->setNom($texte('nom'))
            ->setBio($texte('bio'))
            ->setEstPrive($donnees['estPrive'] ?? $utilisateur->isEstPrive());

        $messages = $this->messagesParChamp($validator->validate($utilisateur));
        if ([] !== $messages) {
            // L'utilisateur est l'utilisateur de la session : on annule les changements refusés
            $em->refresh($utilisateur);

            return $this->reponseInvalide($messages);
        }

        if ($devientPublic) {
            $this->abonnements->accepterDemandes($utilisateur);
        }
        $em->flush();

        return $this->json($this->presentateur->utilisateur($utilisateur));
    }

    #[Route('/moi/demandes', name: 'api_moi_demandes', methods: ['GET'])]
    public function demandes(#[CurrentUser] Utilisateur $utilisateur): JsonResponse
    {
        return $this->json(array_map(
            fn (Abonnement $demande) => ['id' => $demande->getId(), 'membre' => $this->presentateur->membre($demande->getAbonne())],
            $this->abonnements->findDemandesRecues($utilisateur),
        ));
    }

    #[Route('/moi/demandes/{id}/acceptation', name: 'api_moi_demande_accepter', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function accepterDemande(
        #[MapEntity(id: 'id')] Abonnement $demande,
        #[CurrentUser] Utilisateur $utilisateur,
        EntityManagerInterface $em,
    ): JsonResponse {
        $this->verifierDemandeRecue($demande, $utilisateur);
        $demande->accepter();
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/moi/demandes/{id}', name: 'api_moi_demande_refuser', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function refuserDemande(
        #[MapEntity(id: 'id')] Abonnement $demande,
        #[CurrentUser] Utilisateur $utilisateur,
        EntityManagerInterface $em,
    ): JsonResponse {
        $this->verifierDemandeRecue($demande, $utilisateur);
        $em->remove($demande);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Choisit un abonné comme ami (ou le retire de ses amis).
     */
    #[Route('/moi/abonnes/{pseudo}', name: 'api_moi_abonne_ami', requirements: ['pseudo' => self::PSEUDO], methods: ['PUT'])]
    public function changerAmi(
        #[MapEntity(mapping: ['pseudo' => 'pseudo'])] Utilisateur $abonne,
        #[CurrentUser] Utilisateur $utilisateur,
        Request $request,
        EntityManagerInterface $em,
    ): JsonResponse {
        $donnees = $this->lireJson($request);
        if (!\is_bool($donnees['estAmi'] ?? null)) {
            return $this->reponseInvalide(['estAmi' => 'Cette valeur doit être vraie ou fausse.']);
        }

        $abonnement = $this->abonnements->entre($abonne, $utilisateur);
        if (StatutAbonnement::ACCEPTE !== $abonnement?->getStatut()) {
            return $this->reponseInvalide(['estAmi' => 'Seul un de vos abonnés peut être choisi comme ami.']);
        }
        $abonnement->setEstAmi($donnees['estAmi']);
        $em->flush();

        return $this->json($this->presentateur->membre($abonne) + ['estAmi' => $abonnement->isEstAmi()]);
    }

    /**
     * Retire un abonné : il ne suit plus le membre (et n'est plus son ami).
     */
    #[Route('/moi/abonnes/{pseudo}', name: 'api_moi_abonne_retirer', requirements: ['pseudo' => self::PSEUDO], methods: ['DELETE'])]
    public function retirerAbonne(
        #[MapEntity(mapping: ['pseudo' => 'pseudo'])] Utilisateur $abonne,
        #[CurrentUser] Utilisateur $utilisateur,
        EntityManagerInterface $em,
    ): JsonResponse {
        if (null !== $abonnement = $this->abonnements->entre($abonne, $utilisateur)) {
            $em->remove($abonnement);
            $em->flush();
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function verifierDemandeRecue(Abonnement $demande, Utilisateur $utilisateur): void
    {
        if ($demande->getSuivi() !== $utilisateur || StatutAbonnement::EN_ATTENTE !== $demande->getStatut()) {
            throw $this->createNotFoundException('Cette demande d\'abonnement n\'existe plus.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function presenterProfil(Utilisateur $membre, ?Utilisateur $visiteur, ArticleRepository $articles): array
    {
        $estMoi = $visiteur === $membre;
        $compteurs = $this->abonnements->compter($membre);
        $abonnement = $this->relations->abonnement($visiteur, $membre);
        $reciproque = null === $visiteur ? null : $this->relations->abonnement($membre, $visiteur);
        $peutVoirContenu = $this->relations->peutVoirContenu($visiteur, $membre);

        return $this->presentateur->membre($membre) + [
            'prenom' => $membre->getPrenom(),
            'nom' => $membre->getNom(),
            'bio' => $membre->getBio(),
            'peutVoirContenu' => $peutVoirContenu,
            'compteurs' => [
                // Le nombre d'articles d'un profil privé n'est montré qu'à ceux qui peuvent les voir
                'articles' => $peutVoirContenu ? $articles->compterDuProfil($membre) : null,
                'abonnes' => $compteurs['abonnes'],
                'abonnements' => $compteurs['abonnements'],
            ],
            'relation' => [
                'estMoi' => $estMoi,
                'abonnement' => $abonnement?->getStatut()->value,
                // Le membre m'a choisi comme ami : je peux commenter ses articles
                'estAmi' => true === $abonnement?->isEstAmi(),
                'mEstAbonne' => StatutAbonnement::ACCEPTE === $reciproque?->getStatut(),
            ],
            'moi' => $estMoi ? ['demandes' => $compteurs['demandes'], 'amis' => $compteurs['amis']] : null,
        ];
    }
}
