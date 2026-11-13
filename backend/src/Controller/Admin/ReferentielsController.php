<?php

namespace App\Controller\Admin;

use App\Controller\ApiController;
use App\Entity\Categorie;
use App\Entity\Couleur;
use App\Entity\Marque;
use App\Enum\ZoneTenue;
use App\Repository\CategorieRepository;
use App\Repository\CouleurRepository;
use App\Repository\MarqueRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Back office de l'administrateur : catégories, couleurs et marques.
 *
 * Les noms sont gérés en français, la langue principale ; leurs traductions anglaise et portugaise
 * sont dans backend/translations/referentiels.*.yaml.
 */
#[Route('/api/admin')]
final class ReferentielsController extends ApiController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
        private readonly Connection $connexion,
    ) {
    }

    #[Route('/referentiels', name: 'api_admin_referentiels', methods: ['GET'])]
    public function lister(CategorieRepository $categories, CouleurRepository $couleurs, MarqueRepository $marques): JsonResponse
    {
        $parCategorie = $this->connexion->fetchAllKeyValue('SELECT categorie_id, COUNT(*) FROM article GROUP BY categorie_id');
        $parCouleur = $this->connexion->fetchAllKeyValue('SELECT couleur_principale_id, COUNT(*) FROM detail_vetement WHERE couleur_principale_id IS NOT NULL GROUP BY couleur_principale_id');
        $parMarque = $this->connexion->fetchAllKeyValue('SELECT marque_id, COUNT(*) FROM detail_vetement WHERE marque_id IS NOT NULL GROUP BY marque_id');

        $toutes = $categories->findAll();
        usort($toutes, static fn (Categorie $a, Categorie $b) => strcmp($a->getChemin(), $b->getChemin()));

        return $this->json([
            'categories' => array_map(static fn (Categorie $c) => [
                'id' => $c->getId(),
                'nom' => $c->getNom(),
                'chemin' => $c->getChemin(),
                'parentId' => $c->getParent()?->getId(),
                'famille' => $c->getFamille()->value,
                'zoneTenue' => $c->getZoneTenue()?->value,
                'nbArticles' => (int) ($parCategorie[$c->getId()] ?? 0),
                'nbEnfants' => $c->getEnfants()->count(),
            ], $toutes),
            'couleurs' => array_map(static fn (Couleur $c) => [
                'id' => $c->getId(),
                'nom' => $c->getNom(),
                'codeHex' => $c->getCodeHex(),
                'nbArticles' => (int) ($parCouleur[$c->getId()] ?? 0),
            ], $couleurs->findBy([], ['id' => 'ASC'])),
            'marques' => array_map(static fn (Marque $m) => [
                'id' => $m->getId(),
                'nom' => $m->getNom(),
                'nbArticles' => (int) ($parMarque[$m->getId()] ?? 0),
            ], $marques->findBy([], ['nom' => 'ASC'])),
        ]);
    }

    /**
     * Ajoute une catégorie sous une autre (elle hérite de sa famille). Corps : nom, parentId, zoneTenue facultative.
     */
    #[Route('/categories', name: 'api_admin_categorie_creer', methods: ['POST'])]
    public function creerCategorie(Request $request, CategorieRepository $categories): JsonResponse
    {
        $donnees = $this->lireJson($request);
        $parent = $categories->find((int) ($donnees['parentId'] ?? 0));
        if (null === $parent) {
            return $this->reponseInvalide(['parentId' => 'Choisissez la catégorie parente.']);
        }

        $categorie = (new Categorie())->setParent($parent)->setFamille($parent->getFamille())
            ->setZoneTenue($parent->getZoneTenue());

        return $this->enregistrerCategorie($categorie, $donnees, Response::HTTP_CREATED);
    }

    /**
     * Renomme une catégorie ou change sa zone de tenue (haut, bas…), utilisée par le générateur de tenue.
     */
    #[Route('/categories/{id}', name: 'api_admin_categorie_modifier', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function modifierCategorie(#[MapEntity(id: 'id')] Categorie $categorie, Request $request): JsonResponse
    {
        return $this->enregistrerCategorie($categorie, $this->lireJson($request), Response::HTTP_OK);
    }

    #[Route('/categories/{id}', name: 'api_admin_categorie_supprimer', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function supprimerCategorie(#[MapEntity(id: 'id')] Categorie $categorie): JsonResponse
    {
        $utilisee = (int) $this->connexion->fetchOne('SELECT COUNT(*) FROM article WHERE categorie_id = ?', [$categorie->getId()]);
        if ($utilisee > 0 || !$categorie->getEnfants()->isEmpty()) {
            return $this->reponseInvalide(['general' => 'Cette catégorie contient des articles ou des sous-catégories : elle ne peut pas être supprimée.']);
        }

        $this->em->remove($categorie);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/couleurs', name: 'api_admin_couleur_creer', methods: ['POST'])]
    public function creerCouleur(Request $request): JsonResponse
    {
        return $this->enregistrerCouleur(new Couleur(), $this->lireJson($request), Response::HTTP_CREATED);
    }

    #[Route('/couleurs/{id}', name: 'api_admin_couleur_modifier', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function modifierCouleur(#[MapEntity(id: 'id')] Couleur $couleur, Request $request): JsonResponse
    {
        return $this->enregistrerCouleur($couleur, $this->lireJson($request), Response::HTTP_OK);
    }

    #[Route('/couleurs/{id}', name: 'api_admin_couleur_supprimer', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function supprimerCouleur(#[MapEntity(id: 'id')] Couleur $couleur): JsonResponse
    {
        $utilisee = (int) $this->connexion->fetchOne(
            'SELECT COUNT(*) FROM detail_vetement WHERE couleur_principale_id = :id OR couleur_secondaire_id = :id',
            ['id' => $couleur->getId()],
        );
        if ($utilisee > 0) {
            return $this->reponseInvalide(['general' => 'Cette couleur est utilisée par des articles : elle ne peut pas être supprimée.']);
        }

        $this->em->remove($couleur);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Renomme une marque (corrige une faute de saisie ou de l'IA).
     */
    #[Route('/marques/{id}', name: 'api_admin_marque_modifier', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function modifierMarque(#[MapEntity(id: 'id')] Marque $marque, Request $request): JsonResponse
    {
        $marque->setNom(trim((string) ($this->lireJson($request)['nom'] ?? '')));
        $messages = $this->messagesParChamp($this->validator->validate($marque));
        if ([] !== $messages) {
            $this->em->refresh($marque);

            return $this->reponseInvalide($messages);
        }
        $this->em->flush();

        return $this->json(['id' => $marque->getId(), 'nom' => $marque->getNom()]);
    }

    /**
     * Supprime une marque : les articles qui la portaient n'ont plus de marque.
     */
    #[Route('/marques/{id}', name: 'api_admin_marque_supprimer', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function supprimerMarque(#[MapEntity(id: 'id')] Marque $marque): JsonResponse
    {
        $this->connexion->executeStatement('UPDATE detail_vetement SET marque_id = NULL WHERE marque_id = ?', [$marque->getId()]);
        $this->em->remove($marque);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<string, mixed> $donnees
     */
    private function enregistrerCategorie(Categorie $categorie, array $donnees, int $statut): JsonResponse
    {
        if (\array_key_exists('zoneTenue', $donnees)) {
            $zone = null === $donnees['zoneTenue'] ? null : ZoneTenue::tryFrom((string) $donnees['zoneTenue']);
            if (null !== $donnees['zoneTenue'] && null === $zone) {
                return $this->reponseInvalide(['zoneTenue' => 'Zone de tenue inconnue.']);
            }
            $categorie->setZoneTenue($zone);
        }
        $categorie->setNom(trim((string) ($donnees['nom'] ?? '')));

        $messages = $this->messagesParChamp($this->validator->validate($categorie));
        if ([] !== $messages) {
            if (null !== $categorie->getId()) {
                $this->em->refresh($categorie);
            }

            return $this->reponseInvalide($messages);
        }

        $this->em->persist($categorie);
        $this->em->flush();

        return $this->json(['id' => $categorie->getId(), 'nom' => $categorie->getNom(), 'chemin' => $categorie->getChemin()], $statut);
    }

    /**
     * @param array<string, mixed> $donnees
     */
    private function enregistrerCouleur(Couleur $couleur, array $donnees, int $statut): JsonResponse
    {
        $couleur->setNom(trim((string) ($donnees['nom'] ?? '')))->setCodeHex(mb_strtoupper(trim((string) ($donnees['codeHex'] ?? ''))));

        $messages = $this->messagesParChamp($this->validator->validate($couleur));
        if ([] !== $messages) {
            if (null !== $couleur->getId()) {
                $this->em->refresh($couleur);
            }

            return $this->reponseInvalide($messages);
        }

        $this->em->persist($couleur);
        $this->em->flush();

        return $this->json(['id' => $couleur->getId(), 'nom' => $couleur->getNom(), 'codeHex' => $couleur->getCodeHex()], $statut);
    }
}
