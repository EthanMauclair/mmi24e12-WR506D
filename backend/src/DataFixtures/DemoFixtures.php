<?php

namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\Categorie;
use App\Entity\Couleur;
use App\Entity\DetailLivre;
use App\Entity\DetailVetement;
use App\Entity\DetailVinyle;
use App\Entity\Etiquette;
use App\Entity\Logement;
use App\Entity\Marque;
use App\Entity\MembreLogement;
use App\Entity\Piece;
use App\Entity\Pret;
use App\Entity\Stockage;
use App\Entity\Utilisateur;
use App\Enum\FormatVinyle;
use App\Enum\ModeAjout;
use App\Enum\RoleLogement;
use App\Enum\Saison;
use App\Enum\StatutArticle;
use App\Enum\TypeStockage;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Données de démonstration reprises des maquettes : le logement « Appartement Lyon » et ses articles.
 * Tous les comptes ont le mot de passe MOT_DE_PASSE (données fictives, uniquement pour le développement).
 */
class DemoFixtures extends Fixture implements DependentFixtureInterface
{
    public const MOT_DE_PASSE = 'penderie2026';

    private ObjectManager $manager;

    /** @var array<string, Marque> */
    private array $marques = [];

    /** @var array<string, Etiquette> */
    private array $etiquettes = [];

    public function __construct(private readonly UserPasswordHasherInterface $hacheur)
    {
    }

    public function getDependencies(): array
    {
        return [CategorieFixtures::class, CouleurFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $this->manager = $manager;

        $lea = $this->utilisateur('lea.chine', 'lea@exemple.fr', '1996-04-18');
        $tom = $this->utilisateur('tom.martin', 'tom@exemple.fr', '1994-09-02');
        $ines = $this->utilisateur('ines.r', 'ines@exemple.fr', '1998-01-25');
        $jules = $this->utilisateur('jules.chine', 'jules@exemple.fr', '2014-06-10')->setTuteur($lea);
        $this->utilisateur('sam.invite', 'sam@exemple.fr', '1990-11-30');

        $logement = (new Logement())->setNom('Appartement Lyon')->setProprietaire($lea);
        $manager->persist($logement);
        $logement->addMembre((new MembreLogement())->setUtilisateur($tom)->setRole(RoleLogement::COPROPRIETAIRE));
        $logement->addMembre((new MembreLogement())->setUtilisateur($ines)->setRole(RoleLogement::COPROPRIETAIRE));
        $logement->addMembre((new MembreLogement())->setUtilisateur($jules)->setRole(RoleLogement::MINEUR));

        foreach (['Travail', 'Hiver', 'Week-end', 'Soirée', 'Sport'] as $nom) {
            $etiquette = (new Etiquette())->setNom($nom)->setCreateur($lea);
            $logement->addEtiquette($etiquette);
            $this->etiquettes[$nom] = $etiquette;
        }

        $stockages = [];
        foreach ([
            'Chambre' => ['Armoire' => TypeStockage::ARMOIRE, 'Commode' => TypeStockage::COMMODE, 'Sous le lit' => TypeStockage::BOITE],
            'Entrée' => ['Placard' => TypeStockage::PLACARD, 'Meuble à chaussures' => TypeStockage::AUTRE],
            'Salon' => ['Étagère vinyles' => TypeStockage::ETAGERE, 'Bibliothèque' => TypeStockage::BIBLIOTHEQUE],
            'Bureau' => ['Étagère comics' => TypeStockage::ETAGERE, 'Cartons stock' => TypeStockage::BOITE],
        ] as $nomPiece => $stockagesPiece) {
            $piece = (new Piece())->setNom($nomPiece);
            $logement->addPiece($piece);
            foreach ($stockagesPiece as $nomStockage => $type) {
                $stockage = (new Stockage())->setNom($nomStockage)->setType($type);
                $piece->addStockage($stockage);
                $stockages[$nomStockage] = $stockage;
            }
        }

        // Vêtements : [titre, propriétaire, stockage, catégorie, marque, couleur, taille, matière, étiquettes, options]
        $vetements = [
            ['Pull col roulé', $lea, 'Armoire', ['Femmes', 'Pulls et sweats', 'Pulls'], 'COS', 'Bordeaux', 'M', 'Laine mérinos', ['Travail', 'Hiver', 'Week-end'], ['saison' => Saison::AUTOMNE_HIVER, 'chaleur' => 4]],
            ['Veste en jean', $lea, 'Armoire', ['Femmes', 'Manteaux et vestes', 'Vestes en jean'], "Levi's", 'Bleu', 'S', 'Denim', ['Week-end'], ['pretee' => 'Camille']],
            ['Chemise rayée', $lea, 'Armoire', ['Femmes', 'Hauts et t-shirts', 'Chemises'], 'Uniqlo', 'Blanc', 'M', 'Coton', ['Travail'], []],
            ['Sweat zippé', $tom, 'Armoire', ['Hommes', 'Pulls et sweats', 'Sweats zippés'], 'Carhartt', 'Vert', 'L', 'Coton', ['Week-end', 'Sport'], ['capuche' => true]],
            ['T-shirt blanc', $ines, 'Commode', ['Femmes', 'Hauts et t-shirts', 'T-shirts'], 'Uniqlo', 'Blanc', 'S', 'Coton', [], ['saison' => Saison::PRINTEMPS_ETE, 'chaleur' => 1]],
            ['Jean droit', $lea, 'Commode', ['Femmes', 'Pantalons', 'Jeans'], "Levi's", 'Bleu', '38', 'Denim', ['Week-end'], []],
            ['Imperméable', $lea, 'Placard', ['Femmes', 'Manteaux et vestes', 'Imperméables'], 'Rains', 'Noir', 'M', null, ['Travail'], ['impermeable' => true, 'capuche' => true]],
            ['Baskets blanches', $ines, 'Meuble à chaussures', ['Femmes', 'Chaussures', 'Baskets'], 'Veja', 'Blanc', '39', 'Cuir', ['Week-end'], []],
            ['Montre de ma grand-mère', $lea, 'Commode', ['Femmes', 'Bijoux et montres', 'Montres'], null, 'Doré', null, 'Or', [], ['personnel' => true]],
        ];
        foreach ($vetements as [$titre, $proprietaire, $stockage, $chemin, $marque, $couleur, $taille, $matiere, $etiquettes, $options]) {
            $article = $this->article($logement, $proprietaire, $stockages[$stockage], $chemin, $titre, ModeAjout::SCAN_IMAGE)
                ->setEstPersonnel($options['personnel'] ?? false);
            foreach ($etiquettes as $nom) {
                $article->addEtiquette($this->etiquettes[$nom]);
            }
            $manager->persist((new DetailVetement($article))
                ->setMarque(null === $marque ? null : $this->marque($marque))
                ->setCouleurPrincipale($this->couleur($couleur))
                ->setTaille($taille)
                ->setMatiere($matiere)
                ->setSaison($options['saison'] ?? Saison::TOUTES)
                ->setNiveauChaleur($options['chaleur'] ?? 3)
                ->setACapuche($options['capuche'] ?? false)
                ->setEstImpermeable($options['impermeable'] ?? false));

            if (isset($options['pretee'])) {
                $article->setStatut(StatutArticle::PRETE);
                $manager->persist((new Pret())
                    ->setArticle($article)
                    ->setPreteur($proprietaire)
                    ->setEmprunteurNom($options['pretee'])
                    ->setDatePret(new \DateTimeImmutable('-12 days'))
                    ->setDateRetourPrevue(new \DateTimeImmutable('+18 days')));
            }
        }

        foreach ([
            ['Random Access Memories', 'Daft Punk', 'Columbia', 2013, FormatVinyle::LP_33T, 2],
            ['Rumours', 'Fleetwood Mac', 'Warner Bros.', 1977, FormatVinyle::LP_33T, 1],
        ] as [$titre, $artiste, $label, $annee, $format, $disques]) {
            $article = $this->article($logement, $lea, $stockages['Étagère vinyles'], ['Musique', 'Vinyles', 'Albums (33 tours)'], $titre, ModeAjout::SCAN_CODE_BARRE);
            $manager->persist((new DetailVinyle($article))
                ->setArtiste($artiste)->setLabel($label)->setAnneeSortie($annee)->setFormat($format)->setNbDisques($disques));
        }

        foreach ([
            ["L'Étranger", 'Bibliothèque', ['Livres', 'Romans', 'Romans'], 'Albert Camus', 'Gallimard', 1942, null],
            ['Watchmen', 'Étagère comics', ['Livres', 'BD, comics et mangas', 'Comics'], 'Alan Moore', 'Urban Comics', 1987, null],
            ['One Piece', 'Étagère comics', ['Livres', 'BD, comics et mangas', 'Mangas'], 'Eiichirō Oda', 'Glénat', 2000, 1],
        ] as [$titre, $stockage, $chemin, $auteur, $editeur, $annee, $tome]) {
            $article = $this->article($logement, $lea, $stockages[$stockage], $chemin, $titre, ModeAjout::SCAN_CODE_BARRE);
            $manager->persist((new DetailLivre($article))
                ->setAuteur($auteur)->setEditeur($editeur)->setAnneeParution($annee)->setTome($tome));
        }

        $manager->flush();
    }

    private function utilisateur(string $pseudo, string $email, string $dateNaissance): Utilisateur
    {
        $utilisateur = (new Utilisateur())
            ->setPseudo($pseudo)
            ->setEmail($email)
            ->setDateNaissance(new \DateTimeImmutable($dateNaissance));
        $utilisateur->setMotDePasse($this->hacheur->hashPassword($utilisateur, self::MOT_DE_PASSE));
        $this->manager->persist($utilisateur);

        return $utilisateur;
    }

    /**
     * @param list<string> $cheminCategorie racine › sous-catégorie › catégorie
     */
    private function article(Logement $logement, Utilisateur $proprietaire, Stockage $stockage, array $cheminCategorie, string $titre, ModeAjout $modeAjout): Article
    {
        $article = (new Article())
            ->setLogement($logement)
            ->setProprietaire($proprietaire)
            ->setStockage($stockage)
            ->setCategorie($this->categorie($cheminCategorie))
            ->setTitre($titre)
            ->setModeAjout($modeAjout);
        $this->manager->persist($article);

        return $article;
    }

    /**
     * @param list<string> $chemin
     */
    private function categorie(array $chemin): Categorie
    {
        $categorie = null;
        foreach ($chemin as $nom) {
            $categorie = $this->manager->getRepository(Categorie::class)->findOneBy(['nom' => $nom, 'parent' => $categorie])
                ?? throw new \LogicException(\sprintf('Catégorie introuvable : %s', implode(' › ', $chemin)));
        }

        return $categorie;
    }

    private function couleur(string $nom): Couleur
    {
        return $this->manager->getRepository(Couleur::class)->findOneBy(['nom' => $nom])
            ?? throw new \LogicException(\sprintf('Couleur introuvable : %s', $nom));
    }

    private function marque(string $nom): Marque
    {
        if (!isset($this->marques[$nom])) {
            $this->marques[$nom] = (new Marque())->setNom($nom);
            $this->manager->persist($this->marques[$nom]);
        }

        return $this->marques[$nom];
    }
}
