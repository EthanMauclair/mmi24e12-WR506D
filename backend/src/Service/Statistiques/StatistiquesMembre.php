<?php

namespace App\Service\Statistiques;

use App\Entity\Utilisateur;
use App\Service\Traduction\Referentiels;
use Doctrine\DBAL\Connection;

/**
 * Statistiques de l'espace d'un membre (« Mon espace ») : ses articles, ses prêts, ses ventes et son activité sociale.
 * Seuls les articles qu'il a ajoutés sont comptés, articles personnels compris (il est seul à les voir).
 */
final class StatistiquesMembre
{
    /** Période de la courbe des ajouts, en mois */
    public const MOIS = 12;

    public function __construct(
        private readonly Connection $connexion,
        private readonly Referentiels $referentiels,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function calculer(Utilisateur $membre): array
    {
        $id = $membre->getId();
        $aujourdhui = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $articles = $this->ligne('SELECT COUNT(*) total, SUM(est_personnel) personnels FROM article WHERE proprietaire_id = ?', [$id]);

        return [
            'articles' => $articles + [
                'parFamille' => $this->repartition(
                    'SELECT c.famille code, COUNT(*) nombre FROM article a JOIN categorie c ON c.id = a.categorie_id WHERE a.proprietaire_id = ? GROUP BY c.famille ORDER BY nombre DESC',
                    $id,
                ),
                'parStatut' => $this->repartition('SELECT statut code, COUNT(*) nombre FROM article WHERE proprietaire_id = ? GROUP BY statut ORDER BY nombre DESC', $id),
                'parMode' => $this->repartition('SELECT mode_ajout code, COUNT(*) nombre FROM article WHERE proprietaire_id = ? GROUP BY mode_ajout ORDER BY nombre DESC', $id),
                'parLogement' => array_map(
                    static fn (array $ligne) => ['nom' => $ligne['nom'], 'nombre' => (int) $ligne['nombre']],
                    $this->connexion->fetchAllAssociative(
                        'SELECT l.nom, COUNT(*) nombre FROM article a JOIN logement l ON l.id = a.logement_id WHERE a.proprietaire_id = ? GROUP BY l.id, l.nom ORDER BY nombre DESC',
                        [$id],
                    ),
                ),
            ],
            'ajoutsParMois' => $this->parMois($id),
            'couleurs' => array_map(
                fn (array $ligne) => ['nom' => $this->referentiels->nom($ligne['nom']), 'codeHex' => $ligne['code_hex'], 'nombre' => (int) $ligne['nombre']],
                $this->connexion->fetchAllAssociative(
                    'SELECT c.nom, c.code_hex, COUNT(*) nombre FROM detail_vetement d JOIN article a ON a.id = d.article_id JOIN couleur c ON c.id = d.couleur_principale_id
                     WHERE a.proprietaire_id = ? GROUP BY c.id, c.nom, c.code_hex ORDER BY nombre DESC LIMIT 5',
                    [$id],
                ),
            ),
            'marques' => array_map(
                static fn (array $ligne) => ['nom' => $ligne['nom'], 'nombre' => (int) $ligne['nombre']],
                $this->connexion->fetchAllAssociative(
                    'SELECT m.nom, COUNT(*) nombre FROM detail_vetement d JOIN article a ON a.id = d.article_id JOIN marque m ON m.id = d.marque_id
                     WHERE a.proprietaire_id = ? GROUP BY m.id, m.nom ORDER BY nombre DESC LIMIT 5',
                    [$id],
                ),
            ),
            'prets' => $this->ligne(
                'SELECT SUM(date_retour_reelle IS NULL) enCours, SUM(date_retour_reelle IS NULL AND date_retour_prevue < ?) enRetard,
                    SUM(date_retour_reelle IS NOT NULL) rendus
                 FROM pret WHERE preteur_id = ?',
                [$aujourdhui, $id],
            ),
            'ventes' => $this->ligne(
                "SELECT SUM(an.statut IN ('EN_LIGNE', 'RESERVEE')) enCours, SUM(an.statut = 'VENDUE') vendues,
                    COALESCE(SUM(CASE WHEN an.statut = 'VENDUE' THEN an.prix END), 0) montant
                 FROM annonce an JOIN article a ON a.id = an.article_id WHERE a.proprietaire_id = ?",
                [$id],
            ),
            'social' => $this->ligne(
                "SELECT (SELECT COUNT(*) FROM abonnement WHERE suivi_id = :id AND statut = 'ACCEPTE') abonnes,
                    (SELECT COUNT(*) FROM abonnement WHERE abonne_id = :id AND statut = 'ACCEPTE') abonnements,
                    (SELECT COUNT(*) FROM abonnement WHERE suivi_id = :id AND est_ami = 1) amis,
                    (SELECT COUNT(*) FROM tenue WHERE createur_id = :id) tenues,
                    (SELECT COUNT(*) FROM like_tenue l JOIN tenue t ON t.id = l.tenue_id WHERE t.createur_id = :id) likesRecus,
                    (SELECT COUNT(*) FROM commentaire c LEFT JOIN article a ON a.id = c.article_id LEFT JOIN tenue t ON t.id = c.tenue_id
                     WHERE (a.proprietaire_id = :id OR t.createur_id = :id) AND c.auteur_id != :id) commentairesRecus",
                ['id' => $id],
            ),
        ];
    }

    /**
     * Articles ajoutés chaque mois sur les 12 derniers mois, mois vides compris.
     *
     * @return list<array{mois: string, nombre: int}>
     */
    private function parMois(int $id): array
    {
        $debut = new \DateTimeImmutable('first day of this month -'.(self::MOIS - 1).' months');
        $nombres = $this->connexion->fetchAllKeyValue(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') mois, COUNT(*) FROM article WHERE proprietaire_id = ? AND created_at >= ? GROUP BY mois",
            [$id, $debut->format('Y-m-d')],
        );

        $serie = [];
        for ($mois = $debut, $i = 0; $i < self::MOIS; $mois = $mois->modify('+1 month'), ++$i) {
            $serie[] = ['mois' => $mois->format('Y-m'), 'nombre' => (int) ($nombres[$mois->format('Y-m')] ?? 0)];
        }

        return $serie;
    }

    /**
     * @return list<array{code: string, nombre: int}>
     */
    private function repartition(string $sql, int $id): array
    {
        return array_map(
            static fn (array $ligne) => ['code' => $ligne['code'], 'nombre' => (int) $ligne['nombre']],
            $this->connexion->fetchAllAssociative($sql, [$id]),
        );
    }

    /**
     * @param array<int|string, mixed> $parametres
     *
     * @return array<string, int|float>
     */
    private function ligne(string $sql, array $parametres): array
    {
        return array_map(
            static fn ($valeur) => str_contains((string) $valeur, '.') ? round((float) $valeur, 2) : (int) $valeur,
            $this->connexion->fetchAssociative($sql, $parametres) ?: [],
        );
    }
}
