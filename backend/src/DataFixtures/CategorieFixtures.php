<?php

namespace App\DataFixtures;

use App\Entity\Categorie;
use App\Enum\FamilleCategorie;
use App\Enum\ZoneTenue;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Arborescence racine › sous-catégorie › catégorie.
 *
 * Une sous-catégorie s'écrit 'Nom' ou ['Nom', ZoneTenue] : sa zone est héritée par ses catégories.
 * Une catégorie s'écrit 'Nom' ou ['Nom', ZoneTenue] quand la zone ne s'applique qu'à elle.
 */
class CategorieFixtures extends Fixture
{
    /** Sous-catégories rangées en famille ACCESSOIRE, avec leurs enfants. */
    private const SOUS_CATEGORIES_ACCESSOIRE = ['Sacs', 'Accessoires', 'Bijoux et montres'];

    public function load(ObjectManager $manager): void
    {
        foreach ($this->arborescence() as $nomRacine => [$famille, $sousCategories]) {
            $racine = $this->creer($manager, $nomRacine, $famille, null, null);

            foreach ($sousCategories as [$sousCategorie, $categories]) {
                [$nomSous, $zoneSous] = \is_array($sousCategorie) ? $sousCategorie : [$sousCategorie, null];
                $familleSous = \in_array($nomSous, self::SOUS_CATEGORIES_ACCESSOIRE, true) ? FamilleCategorie::ACCESSOIRE : $famille;
                $sous = $this->creer($manager, $nomSous, $familleSous, $zoneSous, $racine);

                foreach ($categories as $categorie) {
                    [$nom, $zone] = \is_array($categorie) ? $categorie : [$categorie, $zoneSous];
                    $this->creer($manager, $nom, $familleSous, $zone, $sous);
                }
            }
        }

        $manager->flush();
    }

    private function creer(ObjectManager $manager, string $nom, FamilleCategorie $famille, ?ZoneTenue $zone, ?Categorie $parent): Categorie
    {
        $categorie = (new Categorie())
            ->setNom($nom)
            ->setFamille($famille)
            ->setZoneTenue($zone);
        $parent?->addEnfant($categorie);
        $manager->persist($categorie);

        return $categorie;
    }

    /**
     * @return array<string, array{FamilleCategorie, list<array{string|array{string, ZoneTenue}, list<string|array{string, ZoneTenue}>}>}>
     */
    private function arborescence(): array
    {
        $hauts = ['T-shirts', 'Débardeurs', 'Chemises', 'Blouses', 'Tops'];
        $pulls = ['Pulls', 'Gilets', 'Sweats', 'Sweats à capuche', 'Sweats zippés'];
        $manteaux = ['Manteaux', 'Vestes', 'Doudounes', 'Imperméables', 'Blazers', 'Vestes en jean'];
        $accessoires = ['Ceintures', 'Chapeaux et casquettes', 'Écharpes et foulards', 'Gants', 'Lunettes de soleil'];

        return [
            'Femmes' => [FamilleCategorie::VETEMENT, [
                [['Hauts et t-shirts', ZoneTenue::HAUT], $hauts],
                [['Pulls et sweats', ZoneTenue::HAUT], $pulls],
                [['Manteaux et vestes', ZoneTenue::COUCHE_EXTERIEURE], $manteaux],
                [['Pantalons', ZoneTenue::BAS], ['Jeans', 'Pantalons', 'Joggings', 'Leggings']],
                [['Shorts', ZoneTenue::BAS], ['Shorts']],
                [['Jupes', ZoneTenue::BAS], ['Jupes courtes', 'Jupes longues']],
                [['Robes', ZoneTenue::UNE_PIECE], ['Robes de jour', 'Robes de soirée', 'Robes d\'été']],
                [['Combinaisons', ZoneTenue::UNE_PIECE], ['Combinaisons', 'Combishorts']],
                ['Sport', [['Hauts de sport', ZoneTenue::HAUT], ['Bas de sport', ZoneTenue::BAS], ['Brassières', ZoneTenue::HAUT]]],
                ['Maillots de bain', [['Maillots de bain', ZoneTenue::UNE_PIECE]]],
                [['Chaussures', ZoneTenue::CHAUSSURES], ['Baskets', 'Bottes', 'Bottines', 'Escarpins', 'Sandales', 'Mocassins']],
                [['Sacs', ZoneTenue::ACCESSOIRE], ['Sacs à main', 'Sacs à dos', 'Pochettes', 'Tote bags']],
                [['Accessoires', ZoneTenue::ACCESSOIRE], $accessoires],
                [['Bijoux et montres', ZoneTenue::ACCESSOIRE], ['Colliers', 'Bagues', 'Boucles d\'oreilles', 'Bracelets', 'Montres']],
            ]],
            'Hommes' => [FamilleCategorie::VETEMENT, [
                [['Hauts et t-shirts', ZoneTenue::HAUT], $hauts],
                [['Pulls et sweats', ZoneTenue::HAUT], $pulls],
                [['Manteaux et vestes', ZoneTenue::COUCHE_EXTERIEURE], $manteaux],
                [['Pantalons', ZoneTenue::BAS], ['Jeans', 'Pantalons', 'Joggings', 'Chinos']],
                [['Shorts', ZoneTenue::BAS], ['Shorts']],
                [['Costumes', ZoneTenue::UNE_PIECE], ['Costumes']],
                ['Sport', [['Hauts de sport', ZoneTenue::HAUT], ['Bas de sport', ZoneTenue::BAS], ['Maillots', ZoneTenue::HAUT]]],
                ['Maillots de bain', [['Maillots de bain', ZoneTenue::UNE_PIECE]]],
                [['Chaussures', ZoneTenue::CHAUSSURES], ['Baskets', 'Bottes', 'Bottines', 'Chaussures de ville', 'Sandales', 'Mocassins']],
                [['Sacs', ZoneTenue::ACCESSOIRE], ['Sacs à dos', 'Sacoches', 'Sacs de voyage']],
                [['Accessoires', ZoneTenue::ACCESSOIRE], $accessoires],
                [['Bijoux et montres', ZoneTenue::ACCESSOIRE], ['Bracelets', 'Bagues', 'Montres']],
            ]],
            'Enfants' => [FamilleCategorie::VETEMENT, [
                ['Vêtements enfants', [
                    ['Hauts', ZoneTenue::HAUT],
                    ['Bas', ZoneTenue::BAS],
                    ['Manteaux', ZoneTenue::COUCHE_EXTERIEURE],
                    ['Robes', ZoneTenue::UNE_PIECE],
                    ['Pyjamas', ZoneTenue::UNE_PIECE],
                ]],
                [['Chaussures enfants', ZoneTenue::CHAUSSURES], ['Chaussures']],
            ]],
            'Livres' => [FamilleCategorie::LIVRE, [
                ['Romans', ['Romans', 'Policiers et thrillers', 'Science-fiction et fantasy']],
                ['BD, comics et mangas', ['Bandes dessinées', 'Comics', 'Mangas']],
                ['Documentaires', ['Beaux livres', 'Cuisine', 'Développement personnel', 'Jeunesse']],
            ]],
            'Musique' => [FamilleCategorie::VINYLE, [
                ['Vinyles', ['Albums (33 tours)', 'Singles (45 tours)', 'Maxis', 'Coffrets']],
            ]],
            'Autres' => [FamilleCategorie::AUTRE, [
                ['Divers', ['Jeux et jouets', 'Décoration', 'Autre']],
            ]],
        ];
    }
}
