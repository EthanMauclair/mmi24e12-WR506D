<?php

namespace App\Controller;

use App\Api\Presentateur;
use App\Entity\DetailVetement;
use App\Entity\Logement;
use App\Entity\Tenue;
use App\Entity\Utilisateur;
use App\Enum\ZoneTenue;
use App\Repository\ArticleRepository;
use App\Repository\EtiquetteRepository;
use App\Repository\TenueRepository;
use App\Security\Voter\LogementVoter;
use App\Security\Voter\ProfilVoter;
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
    public function __construct(private readonly Presentateur $presentateur)
    {
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

        return $this->json($this->presentateur->tenue($tenue), Response::HTTP_CREATED);
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

        return $this->json(array_map(
            $this->presentateur->tenue(...),
            $tenues->findBy($criteres, ['createdAt' => 'DESC', 'id' => 'DESC']),
        ));
    }

    #[Route('/tenues/{id}', name: 'api_tenue_supprimer', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function supprimer(#[MapEntity(id: 'id')] Tenue $tenue, #[CurrentUser] Utilisateur $utilisateur, EntityManagerInterface $em): JsonResponse
    {
        if ($tenue->getCreateur() !== $utilisateur) {
            throw $this->createAccessDeniedException('Seul son créateur peut supprimer cette tenue.');
        }

        $em->remove($tenue);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
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
