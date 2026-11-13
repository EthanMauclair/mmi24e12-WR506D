<?php

namespace App\Controller\Admin;

use App\Controller\ApiController;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Service\Statistiques\IndicateursSite;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Back office du super admin (propriétaire du site) : indicateurs et gestion des membres.
 */
#[Route('/api/admin')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class MembresController extends ApiController
{
    private const PAR_PAGE = 20;
    private const ROLES = ['MEMBRE' => [], 'ADMIN' => ['ROLE_ADMIN'], 'SUPER_ADMIN' => ['ROLE_SUPER_ADMIN']];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $connexion,
    ) {
    }

    #[Route('/indicateurs', name: 'api_admin_indicateurs', methods: ['GET'])]
    public function indicateurs(IndicateursSite $indicateurs): JsonResponse
    {
        return $this->json($indicateurs->calculer());
    }

    /**
     * Membres, les plus récents d'abord, par pages de 20. Paramètres : q (pseudo, e-mail, nom), page.
     */
    #[Route('/membres', name: 'api_admin_membres', methods: ['GET'])]
    public function lister(Request $request, UtilisateurRepository $utilisateurs): JsonResponse
    {
        $texte = trim((string) $request->query->get('q', ''));
        $page = max(1, $request->query->getInt('page', 1));

        $requete = $utilisateurs->createQueryBuilder('u')->orderBy('u.createdAt', 'DESC')->addOrderBy('u.id', 'DESC');
        if ('' !== $texte) {
            $requete->andWhere('u.pseudo LIKE :texte OR u.email LIKE :texte OR u.prenom LIKE :texte OR u.nom LIKE :texte')
                ->setParameter('texte', '%'.addcslashes($texte, '%_').'%');
        }
        $total = (int) (clone $requete)->select('COUNT(u.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();
        $membres = $requete->setFirstResult(($page - 1) * self::PAR_PAGE)->setMaxResults(self::PAR_PAGE)->getQuery()->getResult();

        $nbArticles = [] === $membres ? [] : $this->connexion->fetchAllKeyValue(
            'SELECT proprietaire_id, COUNT(*) FROM article WHERE proprietaire_id IN (?) GROUP BY proprietaire_id',
            [array_map(static fn (Utilisateur $u) => $u->getId(), $membres)],
            [ArrayParameterType::INTEGER],
        );

        return $this->json([
            'membres' => array_map(fn (Utilisateur $u) => $this->presenter($u) + ['nbArticles' => (int) ($nbArticles[$u->getId()] ?? 0)], $membres),
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / self::PAR_PAGE)),
        ]);
    }

    /**
     * Un membre et ce que supprimer son compte effacerait : la suppression est en cascade.
     */
    #[Route('/membres/{id}', name: 'api_admin_membre', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(#[MapEntity(id: 'id')] Utilisateur $membre): JsonResponse
    {
        $impact = $this->connexion->fetchAssociative(
            'SELECT (SELECT COUNT(*) FROM logement WHERE proprietaire_id = :id) logements,
                (SELECT COUNT(*) FROM article WHERE proprietaire_id = :id) articles,
                (SELECT COUNT(*) FROM article a JOIN logement l ON l.id = a.logement_id WHERE l.proprietaire_id = :id AND a.proprietaire_id != :id) articlesAutres,
                (SELECT COUNT(*) FROM tenue WHERE createur_id = :id) tenues,
                (SELECT COUNT(*) FROM commentaire WHERE auteur_id = :id) commentaires',
            ['id' => $membre->getId()],
        );

        return $this->json($this->presenter($membre) + ['impact' => array_map('intval', $impact)]);
    }

    /**
     * Bloque ou débloque un membre, ou change son rôle (MEMBRE, ADMIN, SUPER_ADMIN). Jamais sur son propre compte.
     */
    #[Route('/membres/{id}', name: 'api_admin_membre_modifier', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function modifier(#[MapEntity(id: 'id')] Utilisateur $membre, #[CurrentUser] Utilisateur $admin, Request $request): JsonResponse
    {
        if ($membre === $admin) {
            return $this->reponseInvalide(['general' => 'Vous ne pouvez pas modifier votre propre compte depuis le back office.']);
        }

        $donnees = $this->lireJson($request);
        if (\array_key_exists('estBloque', $donnees)) {
            if (!\is_bool($donnees['estBloque'])) {
                return $this->reponseInvalide(['estBloque' => 'Cette valeur doit être vraie ou fausse.']);
            }
            $membre->setEstBloque($donnees['estBloque']);
        }
        if (\array_key_exists('role', $donnees)) {
            if (!\is_string($donnees['role']) || !isset(self::ROLES[$donnees['role']])) {
                return $this->reponseInvalide(['role' => 'Rôle inconnu.']);
            }
            $membre->setRoles(self::ROLES[$donnees['role']]);
        }
        $this->em->flush();

        return $this->json($this->presenter($membre));
    }

    #[Route('/membres/{id}', name: 'api_admin_membre_supprimer', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function supprimer(#[MapEntity(id: 'id')] Utilisateur $membre, #[CurrentUser] Utilisateur $admin): JsonResponse
    {
        if ($membre === $admin) {
            return $this->reponseInvalide(['general' => 'Vous ne pouvez pas supprimer votre propre compte depuis le back office.']);
        }

        $this->em->remove($membre);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string, mixed>
     */
    private function presenter(Utilisateur $membre): array
    {
        return [
            'id' => $membre->getId(),
            'pseudo' => $membre->getPseudo(),
            'email' => $membre->getEmail(),
            'nomComplet' => trim(($membre->getPrenom() ?? '').' '.($membre->getNom() ?? '')) ?: null,
            'avatar' => $membre->getAvatar(),
            'role' => $membre->estSuperAdmin() ? 'SUPER_ADMIN' : ($membre->estAdmin() ? 'ADMIN' : 'MEMBRE'),
            'estBloque' => $membre->isEstBloque(),
            'estMineur' => $membre->estMineur(),
            'estPrive' => $membre->isEstPrive(),
            'createdAt' => $membre->getCreatedAt()->format(\DATE_ATOM),
            'derniereConnexionAt' => $membre->getDerniereConnexionAt()?->format(\DATE_ATOM),
        ];
    }
}
