<?php

namespace App\Service\Article;

use App\Entity\Article;
use App\Entity\Categorie;
use App\Entity\Couleur;
use App\Entity\DetailLivre;
use App\Entity\DetailVetement;
use App\Entity\DetailVinyle;
use App\Entity\Etiquette;
use App\Entity\Logement;
use App\Entity\Marque;
use App\Entity\PhotoArticle;
use App\Entity\Stockage;
use App\Entity\Utilisateur;
use App\Enum\EtatArticle;
use App\Enum\FamilleCategorie;
use App\Enum\FormatVinyle;
use App\Enum\ModeAjout;
use App\Service\CodeBarres\RechercheCodeBarres;
use App\Service\Photo\TraitementPhoto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Crée un article et son détail (vêtement, livre ou vinyle) à partir des données du formulaire d'ajout.
 */
final class CreationArticle
{
    /** Sites dont on accepte les couvertures proposées par la recherche de code-barres */
    private const HOTES_COUVERTURES = ['covers.openlibrary.org', 'books.google.com', 'books.googleusercontent.com', 'coverartarchive.org'];

    /** Renomme les chemins de propriété des entités en noms de champs du formulaire */
    private const CHAMPS = ['categorie' => 'categorieId', 'stockage' => 'stockageId', 'couleurPrincipale' => 'couleurId', 'chemin' => 'photo'];

    /** @var array<string, string> */
    private array $erreurs = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
        private readonly TraitementPhoto $photos,
    ) {
    }

    /**
     * @param array<string, mixed> $donnees
     *
     * @throws ArticleInvalideException avec un message par champ invalide
     */
    public function creer(Logement $logement, Utilisateur $proprietaire, array $donnees): Article
    {
        $this->erreurs = [];

        $article = (new Article())
            ->setLogement($logement)
            ->setProprietaire($proprietaire)
            ->setTitre(self::texte($donnees['titre'] ?? null) ?? '')
            ->setDescription(self::texte($donnees['description'] ?? null))
            ->setEstPersonnel(true === ($donnees['estPersonnel'] ?? false))
            ->setEtat(EtatArticle::tryFrom((string) ($donnees['etat'] ?? '')));

        $modeAjout = ModeAjout::tryFrom((string) ($donnees['modeAjout'] ?? ''));
        null === $modeAjout ? $this->erreur('modeAjout', 'Mode d\'ajout inconnu.') : $article->setModeAjout($modeAjout);

        $categorie = $this->trouver(Categorie::class, $donnees['categorieId'] ?? null);
        null === $categorie ? $this->erreur('categorieId', 'Choisissez une catégorie.') : $article->setCategorie($categorie);

        if (null !== ($donnees['stockageId'] ?? null)) {
            $stockage = $this->trouver(Stockage::class, $donnees['stockageId']);
            null === $stockage ? $this->erreur('stockageId', 'Cet emplacement n\'existe pas.') : $article->setStockage($stockage);
        }

        foreach ((array) ($donnees['etiquetteIds'] ?? []) as $idEtiquette) {
            $etiquette = $this->trouver(Etiquette::class, $idEtiquette);
            if (null === $etiquette || $etiquette->getLogement() !== $logement) {
                $this->erreur('etiquetteIds', 'Une étiquette choisie n\'appartient pas à ce logement.');
                continue;
            }
            $article->addEtiquette($etiquette);
        }

        $aValider = [$article];
        if (null !== ($photo = $this->photo($donnees, $modeAjout))) {
            $article->addPhoto($photo);
            $aValider[] = $photo;
        }
        if (null !== $categorie && null !== ($detail = $this->detail($article, $categorie->getFamille(), $donnees))) {
            $aValider[] = $detail;
        }

        foreach ($aValider as $entite) {
            foreach ($this->validator->validate($entite) as $violation) {
                $chemin = $violation->getPropertyPath();
                $this->erreur(self::CHAMPS[$chemin] ?? ('' === $chemin ? 'general' : $chemin), (string) $violation->getMessage());
            }
        }

        if ([] !== $this->erreurs) {
            throw new ArticleInvalideException($this->erreurs);
        }

        foreach ($aValider as $entite) {
            $this->em->persist($entite);
        }
        $this->em->flush();

        return $article;
    }

    /**
     * Photo du scan (enregistrée au préalable par /api/scans/vetement) ou couverture trouvée par code-barres.
     *
     * @param array<string, mixed> $donnees
     */
    private function photo(array $donnees, ?ModeAjout $modeAjout): ?PhotoArticle
    {
        $photo = self::texte($donnees['photo'] ?? null);
        if (null !== $photo) {
            $dejaUtilisee = null !== $this->em->getRepository(PhotoArticle::class)->findOneBy(['chemin' => $photo]);
            if (!$this->photos->existe($photo) || $dejaUtilisee) {
                $this->erreur('photo', 'Cette photo n\'est plus disponible : reprenez la photo.');

                return null;
            }

            return (new PhotoArticle())->setChemin($photo)->setEstImageScan(ModeAjout::SCAN_IMAGE === $modeAjout);
        }

        $couverture = self::texte($donnees['couverture'] ?? null);
        if (null === $couverture) {
            return null;
        }
        $hote = parse_url($couverture, \PHP_URL_HOST);
        if (!str_starts_with($couverture, 'https://') || !\in_array($hote, self::HOTES_COUVERTURES, true)) {
            $this->erreur('couverture', 'Cette couverture ne provient pas d\'une source reconnue.');

            return null;
        }

        return (new PhotoArticle())->setChemin($couverture);
    }

    /**
     * @param array<string, mixed> $donnees
     */
    private function detail(Article $article, FamilleCategorie $famille, array $donnees): DetailVetement|DetailLivre|DetailVinyle|null
    {
        return match ($famille) {
            FamilleCategorie::VETEMENT, FamilleCategorie::ACCESSOIRE => $this->detailVetement($article, (array) ($donnees['vetement'] ?? [])),
            FamilleCategorie::LIVRE => $this->detailLivre($article, (array) ($donnees['livre'] ?? [])),
            FamilleCategorie::VINYLE => $this->detailVinyle($article, (array) ($donnees['vinyle'] ?? [])),
            FamilleCategorie::AUTRE => null,
        };
    }

    /**
     * @param array<string, mixed> $donnees
     */
    private function detailVetement(Article $article, array $donnees): DetailVetement
    {
        $detail = (new DetailVetement($article))
            ->setTaille(self::texte($donnees['taille'] ?? null))
            ->setMatiere(self::texte($donnees['matiere'] ?? null))
            ->setMarque($this->marque(self::texte($donnees['marque'] ?? null)));

        if (null !== ($donnees['couleurId'] ?? null)) {
            $couleur = $this->trouver(Couleur::class, $donnees['couleurId']);
            null === $couleur ? $this->erreur('couleurId', 'Cette couleur n\'existe pas.') : $detail->setCouleurPrincipale($couleur);
        }

        return $detail;
    }

    /**
     * @param array<string, mixed> $donnees
     */
    private function detailLivre(Article $article, array $donnees): DetailLivre
    {
        $isbn = self::texte($donnees['isbn'] ?? null);

        return (new DetailLivre($article))
            ->setIsbn(null === $isbn ? null : RechercheCodeBarres::normaliser($isbn))
            ->setAuteur(self::texte($donnees['auteur'] ?? null))
            ->setEditeur(self::texte($donnees['editeur'] ?? null))
            ->setNbPages(self::entier($donnees['nbPages'] ?? null))
            ->setAnneeParution(self::entier($donnees['anneeParution'] ?? null))
            ->setSerie(self::texte($donnees['serie'] ?? null))
            ->setTome(self::entier($donnees['tome'] ?? null));
    }

    /**
     * @param array<string, mixed> $donnees
     */
    private function detailVinyle(Article $article, array $donnees): DetailVinyle
    {
        $ean = self::texte($donnees['ean'] ?? null);

        return (new DetailVinyle($article))
            ->setEan(null === $ean ? null : RechercheCodeBarres::normaliser($ean))
            ->setArtiste(self::texte($donnees['artiste'] ?? null))
            ->setLabel(self::texte($donnees['label'] ?? null))
            ->setAnneeSortie(self::entier($donnees['anneeSortie'] ?? null))
            ->setFormat(FormatVinyle::tryFrom((string) ($donnees['format'] ?? '')))
            ->setNbDisques(self::entier($donnees['nbDisques'] ?? null) ?? 1);
    }

    /**
     * Marque existante (sans tenir compte de la casse), sinon nouvelle marque.
     */
    private function marque(?string $nom): ?Marque
    {
        if (null === $nom) {
            return null;
        }

        $marque = $this->em->getRepository(Marque::class)->createQueryBuilder('m')
            ->andWhere('LOWER(m.nom) = LOWER(:nom)')
            ->setParameter('nom', $nom)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (null === $marque) {
            $marque = (new Marque())->setNom(mb_substr($nom, 0, 80));
            $this->em->persist($marque);
        }

        return $marque;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $classe
     *
     * @return T|null
     */
    private function trouver(string $classe, mixed $id): ?object
    {
        $id = self::entier($id);

        return null === $id ? null : $this->em->find($classe, $id);
    }

    private function erreur(string $champ, string $message): void
    {
        $this->erreurs[$champ] ??= $message;
    }

    private static function texte(mixed $valeur): ?string
    {
        if (!\is_string($valeur) || '' === trim($valeur)) {
            return null;
        }

        return trim($valeur);
    }

    private static function entier(mixed $valeur): ?int
    {
        if (\is_int($valeur)) {
            return $valeur;
        }

        return \is_string($valeur) && 1 === preg_match('/^\d+$/', trim($valeur)) ? (int) trim($valeur) : null;
    }
}
