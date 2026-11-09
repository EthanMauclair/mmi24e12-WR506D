<?php

namespace App\Api;

use App\Entity\Article;
use App\Entity\DetailLivre;
use App\Entity\DetailVetement;
use App\Entity\DetailVinyle;
use App\Entity\Logement;
use App\Entity\Utilisateur;
use App\Enum\RoleLogement;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Met en forme les entités pour les réponses JSON de l'API.
 */
final class Presentateur
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function utilisateur(Utilisateur $utilisateur): array
    {
        return [
            'id' => $utilisateur->getId(),
            'pseudo' => $utilisateur->getPseudo(),
            'email' => $utilisateur->getEmail(),
            'estPrive' => $utilisateur->isEstPrive(),
            'estMineur' => $utilisateur->estMineur(),
        ];
    }

    /**
     * Membre tel que les autres le voient (listes, auteur d'un commentaire…) : jamais son e-mail.
     *
     * @return array<string, mixed>
     */
    public function membre(Utilisateur $membre): array
    {
        $nomComplet = trim(($membre->getPrenom() ?? '').' '.($membre->getNom() ?? ''));

        return [
            'id' => $membre->getId(),
            'pseudo' => $membre->getPseudo(),
            'nomComplet' => '' === $nomComplet ? null : $nomComplet,
            'avatar' => $membre->getAvatar(),
            'estPrive' => $membre->isEstPrive(),
        ];
    }

    /**
     * Articles d'un profil : comme articlesResumes, sans ce qui dit où ils sont rangés.
     *
     * @param list<Article> $articles
     *
     * @return list<array<string, mixed>>
     */
    public function articlesDuProfil(array $articles): array
    {
        return array_map(
            static fn (array $article) => array_diff_key($article, ['stockageId' => true, 'emplacement' => true]),
            $this->articlesResumes($articles),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function logementResume(Logement $logement, Utilisateur $utilisateur): array
    {
        $role = $logement->roleDe($utilisateur);

        return [
            'id' => $logement->getId(),
            'nom' => $logement->getNom(),
            'role' => $role instanceof RoleLogement ? $role->value : $role,
            'nbCoproprietaires' => \count(array_filter(
                $logement->getMembres()->toArray(),
                static fn ($membre) => RoleLogement::COPROPRIETAIRE === $membre->getRole(),
            )),
        ];
    }

    /**
     * @param list<Article> $articles
     *
     * @return list<array<string, mixed>>
     */
    public function articlesResumes(array $articles): array
    {
        $details = $this->details($articles);

        return array_map(function (Article $article) use ($details) {
            $vetement = $details[DetailVetement::class][$article->getId()] ?? null;
            $premierePhoto = $article->getPhotos()->first();

            return [
                'id' => $article->getId(),
                'titre' => $article->getTitre(),
                'infos' => $this->infos($article, $details, false),
                'categorie' => $article->getCategorie()->getNom(),
                'statut' => $article->getStatut()->value,
                'estPersonnel' => $article->isEstPersonnel(),
                'couleur' => $vetement?->getCouleurPrincipale()?->getCodeHex(),
                'photo' => $premierePhoto ? $premierePhoto->getChemin() : null,
                'stockageId' => $article->getStockage()?->getId(),
                'emplacement' => $this->emplacement($article),
            ];
        }, $articles);
    }

    /**
     * @return array<string, mixed>
     */
    public function articleDetail(Article $article, bool $peutModifier, bool $peutChangerConfidentialite): array
    {
        $details = $this->details([$article]);
        $vetement = $details[DetailVetement::class][$article->getId()] ?? null;
        $couleur = $vetement?->getCouleurPrincipale();
        $stockage = $article->getStockage();
        $photos = $article->getPhotos()->getValues();
        $photoScan = array_values(array_filter($photos, static fn ($photo) => $photo->isEstImageScan()))[0] ?? null;

        return [
            'id' => $article->getId(),
            'titre' => $article->getTitre(),
            'infos' => $this->infos($article, $details, true),
            'description' => $article->getDescription(),
            'statut' => $article->getStatut()->value,
            'libelleStatut' => $article->getStatut()->label(),
            'categorie' => $article->getCategorie()->getNom(),
            'couleur' => null === $couleur ? null : ['nom' => $couleur->getNom(), 'codeHex' => $couleur->getCodeHex()],
            'photos' => array_map(static fn ($photo) => $photo->getChemin(), $photos),
            'dateScan' => $photoScan?->getCreatedAt()->format(\DATE_ATOM),
            'emplacement' => null === $stockage ? null : [
                'piece' => ['id' => $stockage->getPiece()->getId(), 'nom' => $stockage->getPiece()->getNom()],
                'stockage' => ['id' => $stockage->getId(), 'nom' => $stockage->getNom()],
            ],
            'logementId' => $article->getLogement()->getId(),
            'etiquettes' => array_map(
                static fn ($etiquette) => ['id' => $etiquette->getId(), 'nom' => $etiquette->getNom()],
                $article->getEtiquettes()->getValues(),
            ),
            'estPersonnel' => $article->isEstPersonnel(),
            'peutModifier' => $peutModifier,
            'peutChangerConfidentialite' => $peutChangerConfidentialite,
            'formulaire' => $peutModifier ? $this->formulaire($article, $details) : null,
        ];
    }

    /**
     * Valeurs brutes de l'article, pour pré-remplir le formulaire de modification.
     *
     * @param array<class-string, array<int, object>> $details
     *
     * @return array<string, mixed>
     */
    private function formulaire(Article $article, array $details): array
    {
        $id = $article->getId();
        $vetement = $details[DetailVetement::class][$id] ?? null;
        $livre = $details[DetailLivre::class][$id] ?? null;
        $vinyle = $details[DetailVinyle::class][$id] ?? null;

        return [
            'titre' => $article->getTitre(),
            'description' => $article->getDescription(),
            'etat' => $article->getEtat()?->value,
            'categorieId' => $article->getCategorie()->getId(),
            'stockageId' => $article->getStockage()?->getId(),
            'etiquetteIds' => array_map(static fn ($etiquette) => $etiquette->getId(), $article->getEtiquettes()->getValues()),
            'vetement' => null === $vetement ? null : [
                'marque' => $vetement->getMarque()?->getNom(),
                'couleurId' => $vetement->getCouleurPrincipale()?->getId(),
                'taille' => $vetement->getTaille(),
                'matiere' => $vetement->getMatiere(),
            ],
            'livre' => null === $livre ? null : [
                'isbn' => $livre->getIsbn(),
                'auteur' => $livre->getAuteur(),
                'editeur' => $livre->getEditeur(),
                'nbPages' => $livre->getNbPages(),
                'anneeParution' => $livre->getAnneeParution(),
                'serie' => $livre->getSerie(),
                'tome' => $livre->getTome(),
            ],
            'vinyle' => null === $vinyle ? null : [
                'ean' => $vinyle->getEan(),
                'artiste' => $vinyle->getArtiste(),
                'label' => $vinyle->getLabel(),
                'anneeSortie' => $vinyle->getAnneeSortie(),
                'format' => $vinyle->getFormat()?->value,
                'nbDisques' => $vinyle->getNbDisques(),
            ],
        ];
    }

    private function emplacement(Article $article): ?string
    {
        $stockage = $article->getStockage();

        return null === $stockage ? null : $stockage->getPiece()->getNom().' › '.$stockage->getNom();
    }

    /**
     * Ligne d'informations sous le titre, selon la famille de l'article.
     * Version courte (cartes) : « COS · Bordeaux · M ». Version longue (fiche) : « COS · Laine mérinos · Taille M ».
     *
     * @param array<class-string, array<int, object>> $details
     */
    private function infos(Article $article, array $details, bool $complet): string
    {
        $id = $article->getId();
        $morceaux = [];

        if ($vetement = $details[DetailVetement::class][$id] ?? null) {
            $morceaux = $complet
                ? [$vetement->getMarque()?->getNom(), $vetement->getMatiere(), $vetement->getTaille() ? 'Taille '.$vetement->getTaille() : null]
                : [$vetement->getMarque()?->getNom(), $vetement->getCouleurPrincipale()?->getNom(), $vetement->getTaille()];
        } elseif ($livre = $details[DetailLivre::class][$id] ?? null) {
            $morceaux = [$livre->getAuteur(), $livre->getEditeur(), $complet ? $livre->getAnneeParution() : null];
        } elseif ($vinyle = $details[DetailVinyle::class][$id] ?? null) {
            $morceaux = [$vinyle->getArtiste(), $vinyle->getLabel(), $complet ? $vinyle->getAnneeSortie() : null];
        }

        $morceaux = array_filter($morceaux, static fn ($morceau) => null !== $morceau && '' !== $morceau);

        return [] === $morceaux ? $article->getCategorie()->getNom() : implode(' · ', $morceaux);
    }

    /**
     * Charge en une requête par type les détails (vêtement, livre, vinyle) des articles.
     *
     * @param list<Article> $articles
     *
     * @return array<class-string, array<int, object>> détails par classe puis par identifiant d'article
     */
    private function details(array $articles): array
    {
        $details = [];
        foreach ([DetailVetement::class, DetailLivre::class, DetailVinyle::class] as $classe) {
            $details[$classe] = [];
            if ([] === $articles) {
                continue;
            }
            foreach ($this->em->getRepository($classe)->findBy(['article' => $articles]) as $detail) {
                $details[$classe][$detail->getArticle()->getId()] = $detail;
            }
        }

        return $details;
    }
}
