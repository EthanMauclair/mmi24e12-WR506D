<?php

namespace App\Service\Statistiques;

use App\Service\Traduction\Referentiels;
use Doctrine\DBAL\Connection;

/**
 * Indicateurs du site entier pour le tableau de bord du super admin, calculés en SQL :
 * quelques requêtes agrégées, quel que soit le nombre de membres et d'articles.
 */
final class IndicateursSite
{
    /** Période des courbes d'activité, en jours */
    public const JOURS = 30;

    public function __construct(
        private readonly Connection $connexion,
        private readonly Referentiels $referentiels,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function calculer(): array
    {
        $depuis7 = (new \DateTimeImmutable('-7 days'))->format('Y-m-d H:i:s');
        $depuis30 = (new \DateTimeImmutable('-30 days'))->format('Y-m-d H:i:s');
        $aujourdhui = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $membres = $this->ligne(
            'SELECT COUNT(*) total,
                SUM(created_at >= :depuis7) nouveaux7j,
                SUM(created_at >= :depuis30) nouveaux30j,
                SUM(derniere_connexion_at >= :depuis30) actifs30j,
                SUM(est_bloque) bloques,
                SUM(roles LIKE :admin) admins
             FROM utilisateur',
            ['depuis7' => $depuis7, 'depuis30' => $depuis30, 'admin' => '%_ADMIN%'],
        );

        $articles = $this->ligne(
            'SELECT COUNT(*) total, SUM(est_personnel) personnels, SUM(created_at >= :depuis30) ajouts30j FROM article',
            ['depuis30' => $depuis30],
        );

        $prets = $this->ligne(
            'SELECT SUM(date_retour_reelle IS NULL) enCours,
                SUM(date_retour_reelle IS NULL AND date_retour_prevue < :aujourdhui) enRetard,
                COUNT(*) total
             FROM pret',
            ['aujourdhui' => $aujourdhui],
        );

        $marche = $this->ligne(
            "SELECT SUM(statut = 'EN_LIGNE') enLigne, SUM(statut = 'RESERVEE') reservees, SUM(statut = 'VENDUE') vendues,
                COALESCE(SUM(CASE WHEN statut = 'VENDUE' THEN prix END), 0) volumeVentes,
                COALESCE(AVG(CASE WHEN statut = 'VENDUE' THEN prix END), 0) prixMoyenVente
             FROM annonce",
        );
        $marche['conversations'] = (int) $this->connexion->fetchOne('SELECT COUNT(*) FROM conversation');
        $marche['messages30j'] = (int) $this->connexion->fetchOne('SELECT COUNT(*) FROM message WHERE envoye_at >= ?', [$depuis30]);

        $social = $this->ligne(
            "SELECT (SELECT COUNT(*) FROM tenue) tenues,
                (SELECT COUNT(*) FROM tenue WHERE est_privee = 0) tenuesPubliques,
                (SELECT COUNT(*) FROM like_tenue) likes,
                (SELECT COUNT(*) FROM commentaire) commentaires,
                (SELECT COUNT(*) FROM abonnement WHERE statut = 'ACCEPTE') abonnements,
                (SELECT COUNT(*) FROM abonnement WHERE statut = 'EN_ATTENTE') demandesEnAttente",
        );

        return [
            'membres' => $membres,
            'contenu' => $articles + [
                'logements' => (int) $this->connexion->fetchOne('SELECT COUNT(*) FROM logement'),
                'parFamille' => $this->repartition(
                    'SELECT c.famille code, COUNT(*) nombre FROM article a JOIN categorie c ON c.id = a.categorie_id GROUP BY c.famille ORDER BY nombre DESC',
                ),
                // Part de chaque mode d'ajout : mesure l'usage du scan (IA et code-barres)
                'parMode' => $this->repartition('SELECT mode_ajout code, COUNT(*) nombre FROM article GROUP BY mode_ajout ORDER BY nombre DESC'),
                'parStatut' => $this->repartition('SELECT statut code, COUNT(*) nombre FROM article GROUP BY statut ORDER BY nombre DESC'),
            ],
            'prets' => $prets,
            'marche' => $marche,
            'social' => $social,
            'series' => [
                'inscriptions' => $this->parJour('utilisateur', 'created_at'),
                'articles' => $this->parJour('article', 'created_at'),
            ],
            'tops' => [
                'categories' => array_map(
                    fn (array $ligne) => ['nom' => $this->referentiels->nom($ligne['nom']), 'nombre' => (int) $ligne['nombre']],
                    $this->connexion->fetchAllAssociative('SELECT c.nom, COUNT(*) nombre FROM article a JOIN categorie c ON c.id = a.categorie_id GROUP BY c.id, c.nom ORDER BY nombre DESC LIMIT 5'),
                ),
                'marques' => array_map(
                    static fn (array $ligne) => ['nom' => $ligne['nom'], 'nombre' => (int) $ligne['nombre']],
                    $this->connexion->fetchAllAssociative('SELECT m.nom, COUNT(*) nombre FROM detail_vetement d JOIN marque m ON m.id = d.marque_id GROUP BY m.id, m.nom ORDER BY nombre DESC LIMIT 5'),
                ),
            ],
        ];
    }

    /**
     * Nombre de lignes créées chaque jour sur la période, jours sans création compris (à 0).
     *
     * @return list<array{jour: string, nombre: int}>
     */
    private function parJour(string $table, string $colonne): array
    {
        $debut = new \DateTimeImmutable('today -'.(self::JOURS - 1).' days');
        $nombres = $this->connexion->fetchAllKeyValue(
            "SELECT DATE($colonne) jour, COUNT(*) FROM $table WHERE $colonne >= ? GROUP BY DATE($colonne)",
            [$debut->format('Y-m-d')],
        );

        $serie = [];
        for ($jour = $debut, $i = 0; $i < self::JOURS; $jour = $jour->modify('+1 day'), ++$i) {
            $serie[] = ['jour' => $jour->format('Y-m-d'), 'nombre' => (int) ($nombres[$jour->format('Y-m-d')] ?? 0)];
        }

        return $serie;
    }

    /**
     * @return list<array{code: string, nombre: int}>
     */
    private function repartition(string $sql): array
    {
        return array_map(
            static fn (array $ligne) => ['code' => $ligne['code'], 'nombre' => (int) $ligne['nombre']],
            $this->connexion->fetchAllAssociative($sql),
        );
    }

    /**
     * Une ligne de compteurs, convertis en nombres (les SUM de MariaDB renvoient des chaînes, ou NULL sur une table vide).
     *
     * @param array<string, mixed> $parametres
     *
     * @return array<string, int|float>
     */
    private function ligne(string $sql, array $parametres = []): array
    {
        return array_map(
            static fn ($valeur) => str_contains((string) $valeur, '.') ? round((float) $valeur, 2) : (int) $valeur,
            $this->connexion->fetchAssociative($sql, $parametres) ?: [],
        );
    }
}
