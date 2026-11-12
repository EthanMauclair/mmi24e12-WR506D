<?php

namespace App\Controller;

use App\Api\Presentateur;
use App\Entity\DetailVetement;
use App\Entity\LikeTenue;
use App\Entity\Logement;
use App\Entity\Tenue;
use App\Entity\Utilisateur;
use App\Enum\ZoneTenue;
use App\Repository\ArticleRepository;
use App\Repository\CommentaireRepository;
use App\Repository\EtiquetteRepository;
use App\Repository\LikeTenueRepository;
use App\Repository\TenueRepository;
use App\Security\Voter\LogementVoter;
use App\Security\Voter\ProfilVoter;
use App\Security\Voter\TenueVoter;
use App\Service\Meteo\Meteo;
use App\Service\Meteo\ServiceMeteo;
use App\Service\Tenue\GenerateurTenue;
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
 * Générateur de tenue et tenues enregistrées.
 */
#[Route('/api')]
final class TenueController extends ApiController
{
    public function __construct(
        private readonly Presentateur $presentateur,
        private readonly LikeTenueRepository $likes,
        private readonly CommentaireRepository $commentaires,
    ) {
    }

    /**
     * Propose une tenue avec les vêtements du membre dans ce logement.
     * Paramètres facultatifs : etiquette (identifiant), lat et lon (météo), eviter (identifiants séparés par des virgules).
     */
    #[Route('/logements/{id}/tenue-proposee', name: 'api_tenue_proposee', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(LogementVoter::VOIR, 'logement')]
    public function proposer(
        #[MapEntity(id: 'id')] Logement $logement,
        #[CurrentUser] Utilisateur $utilisateur,
        Request $request,
        ArticleRepository $articles,
        EtiquetteRepository $etiquettes,
        ServiceMeteo $serviceMeteo,
        GenerateurTenue $generateur,
        EntityManagerInterface $em,
    ): JsonResponse {
        $requete = $request->query;
        $etiquette = $requete->has('etiquette') ? $etiquettes->findOneBy(['id' => $requete->getInt('etiquette'), 'logement' => $logement]) : null;

        $meteo = null;
        $latitude = filter_var($requete->get('lat'), \FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($requete->get('lon'), \FILTER_VALIDATE_FLOAT);
        if (false !== $latitude && false !== $longitude && abs($latitude) <= 90 && abs($longitude) <= 180) {
            $meteo = $serviceMeteo->actuelle($latitude, $longitude);
        }

        $eviter = array_map('intval', array_filter(explode(',', (string) $requete->get('eviter', ''))));
        $candidats = $articles->findPourTenue($logement, $utilisateur);
        $details = [];
        if ([] !== $candidats) {
            foreach ($em->getRepository(DetailVetement::class)->findBy(['article' => $candidats]) as $detail) {
                $details[$detail->getArticle()->getId()] = $detail;
            }
        }

        $proposition = $generateur->proposer($candidats, $details, $etiquette, $meteo, $eviter, new \DateTimeImmutable());
        $resumes = [];
        foreach ($this->presentateur->articlesResumes(array_values(array_filter(array_column($proposition, 'article')))) as $resume) {
            $resumes[$resume['id']] = $resume;
        }

        return $this->json([
            'meteo' => null === $meteo ? null : $this->presenterMeteo($meteo),
            'tenue' => array_map(static fn (array $ligne) => [
                'zone' => $ligne['zone']->value,
                'libelleZone' => ZoneTenue::COUCHE_EXTERIEURE === $ligne['zone'] ? 'Veste' : $ligne['zone']->label(),
                'pluie' => $ligne['pluie'],
                'article' => null === $ligne['article'] ? null : $resumes[$ligne['article']->getId()],
            ], $proposition),
        ]);
    }

    /**
     * Enregistre une tenue (bouton « Partager ») : publique, elle apparaît sur le profil ; privée, seul son créateur la voit.
     */
    #[Route('/tenues', name: 'api_tenue_creer', methods: ['POST'])]
    public function creer(
        Request $request,
        #[CurrentUser] Utilisateur $utilisateur,
        ArticleRepository $articles,
        ValidatorInterface $validator,
        EntityManagerInterface $em,
    ): JsonResponse {
        $donnees = $this->lireJson($request);
        $ids = \is_array($donnees['articleIds'] ?? null) ? array_unique(array_map('intval', $donnees['articleIds'])) : [];
        $trouves = [] === $ids ? [] : $articles->findBy(['id' => $ids]);
        if (\count($trouves) !== \count($ids)) {
            return $this->reponseInvalide(['articles' => 'Un des articles de la tenue n\'existe plus.']);
        }

        $tenue = (new Tenue())
            ->setCreateur($utilisateur)
            ->setTitre(\is_string($donnees['titre'] ?? null) ? trim($donnees['titre']) : '')
            ->setDescription(\is_string($donnees['description'] ?? null) && '' !== trim($donnees['description']) ? trim($donnees['description']) : null)
            ->setEstPrivee(true === ($donnees['estPrivee'] ?? false));
        foreach ($trouves as $article) {
            $tenue->addArticle($article);
        }

        $messages = $this->messagesParChamp($validator->validate($tenue));
        if ([] !== $messages) {
            return $this->reponseInvalide($messages);
        }

        $em->persist($tenue);
        $em->flush();

        return $this->json($this->presenterTenues([$tenue], $utilisateur)[0], Response::HTTP_CREATED);
    }

    /**
     * Fil des tenues : celles des membres suivis et les siennes, par pages de 10 (avant = dernière tenue affichée).
     */
    #[Route('/tenues/fil', name: 'api_tenues_fil', methods: ['GET'])]
    public function fil(Request $request, #[CurrentUser] Utilisateur $utilisateur, TenueRepository $tenues): JsonResponse
    {
        $avant = $request->query->has('avant') ? $request->query->getInt('avant') : null;
        $page = $tenues->findFil($utilisateur, $avant);

        return $this->json([
            'tenues' => $this->presenterTenues($page, $utilisateur),
            'suite' => \count($page) === TenueRepository::TAILLE_PAGE,
        ]);
    }

    /**
     * Une tenue : page ouverte par le lien partagé, visiteurs compris pour une tenue publique d'un profil public.
     */
    #[Route('/tenues/{id}', name: 'api_tenue', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(TenueVoter::VOIR, 'tenue')]
    public function detail(#[MapEntity(id: 'id')] Tenue $tenue, #[CurrentUser] ?Utilisateur $visiteur): JsonResponse
    {
        return $this->json($this->presenterTenues([$tenue], $visiteur)[0]);
    }

    #[Route('/tenues/{id}/like', name: 'api_tenue_aimer', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[IsGranted(TenueVoter::AIMER, 'tenue')]
    public function aimer(
        #[MapEntity(id: 'id')] Tenue $tenue,
        #[CurrentUser] Utilisateur $utilisateur,
        LikeTenueRepository $likes,
        EntityManagerInterface $em,
    ): JsonResponse {
        if (null === $likes->findOneBy(['tenue' => $tenue, 'utilisateur' => $utilisateur])) {
            $em->persist((new LikeTenue())->setTenue($tenue)->setUtilisateur($utilisateur));
            $em->flush();
        }

        return $this->json($this->resumeLikes($tenue, $utilisateur, $likes));
    }

    #[Route('/tenues/{id}/like', name: 'api_tenue_ne_plus_aimer', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[IsGranted(TenueVoter::AIMER, 'tenue')]
    public function nePlusAimer(
        #[MapEntity(id: 'id')] Tenue $tenue,
        #[CurrentUser] Utilisateur $utilisateur,
        LikeTenueRepository $likes,
        EntityManagerInterface $em,
    ): JsonResponse {
        if (null !== $like = $likes->findOneBy(['tenue' => $tenue, 'utilisateur' => $utilisateur])) {
            $em->remove($like);
            $em->flush();
        }

        return $this->json($this->resumeLikes($tenue, $utilisateur, $likes));
    }

    /**
     * Tenues d'un profil, les plus récentes d'abord. Les tenues privées ne sont montrées qu'à leur créateur.
     */
    #[Route('/profils/{pseudo}/tenues', name: 'api_profil_tenues', requirements: ['pseudo' => '[a-zA-Z0-9._-]+'], methods: ['GET'])]
    #[IsGranted(ProfilVoter::VOIR_CONTENU, 'membre')]
    public function duProfil(
        #[MapEntity(mapping: ['pseudo' => 'pseudo'])] Utilisateur $membre,
        #[CurrentUser] ?Utilisateur $visiteur,
        TenueRepository $tenues,
    ): JsonResponse {
        $criteres = ['createur' => $membre] + ($visiteur === $membre ? [] : ['estPrivee' => false]);

        return $this->json($this->presenterTenues($tenues->findBy($criteres, ['createdAt' => 'DESC', 'id' => 'DESC']), $visiteur));
    }

    #[Route('/tenues/{id}', name: 'api_tenue_supprimer', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[IsGranted(TenueVoter::SUPPRIMER, 'tenue', message: 'Seul son créateur peut supprimer cette tenue.')]
    public function supprimer(#[MapEntity(id: 'id')] Tenue $tenue, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($tenue);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Tenues avec leurs likes, leurs commentaires (nombre et dernier) et les droits du visiteur,
     * en deux requêtes pour toute la liste.
     *
     * @param list<Tenue> $tenues
     *
     * @return list<array<string, mixed>>
     */
    private function presenterTenues(array $tenues, ?Utilisateur $visiteur): array
    {
        $likes = $this->likes->resumer($tenues, $visiteur);
        $commentaires = $this->commentaires->resumerTenues($tenues);

        return array_map(function (Tenue $tenue) use ($likes, $commentaires) {
            $dernier = $commentaires[$tenue->getId()]['dernier'] ?? null;

            return $this->presentateur->tenue($tenue) + [
                'nbLikes' => $likes[$tenue->getId()]['nombre'] ?? 0,
                'aime' => $likes[$tenue->getId()]['aime'] ?? false,
                'nbCommentaires' => $commentaires[$tenue->getId()]['nombre'] ?? 0,
                'dernierCommentaire' => null === $dernier ? null : [
                    'auteur' => $this->presentateur->membre($dernier->getAuteur()),
                    'contenu' => $dernier->getContenu(),
                ],
                'peutAimer' => $this->isGranted(TenueVoter::AIMER, $tenue),
                'peutCommenter' => $this->isGranted(TenueVoter::COMMENTER, $tenue),
                'peutSupprimer' => $this->isGranted(TenueVoter::SUPPRIMER, $tenue),
            ];
        }, $tenues);
    }

    /**
     * @return array{nbLikes: int, aime: bool}
     */
    private function resumeLikes(Tenue $tenue, Utilisateur $utilisateur, LikeTenueRepository $likes): array
    {
        $resume = $likes->resumer([$tenue], $utilisateur)[$tenue->getId()] ?? ['nombre' => 0, 'aime' => false];

        return ['nbLikes' => $resume['nombre'], 'aime' => $resume['aime']];
    }

    /**
     * @return array<string, mixed>
     */
    private function presenterMeteo(Meteo $meteo): array
    {
        return [
            'ville' => $meteo->ville,
            'temperature' => $meteo->temperature,
            'libelle' => $meteo->libelle,
            'pluie' => $meteo->pluie,
            'conseil' => match (true) {
                $meteo->pluie => 'On privilégie capuche et imperméable',
                $meteo->estFroid() => 'On privilégie les pièces chaudes',
                $meteo->estChaud() => 'On privilégie les pièces légères',
                default => 'Une veste légère suffit',
            },
        ];
    }
}
