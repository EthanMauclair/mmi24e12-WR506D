<?php

namespace App\Service\Tenue;

use App\Entity\Article;
use App\Entity\DetailVetement;
use App\Entity\Etiquette;
use App\Enum\Saison;
use App\Enum\ZoneTenue;
use App\Service\Meteo\Meteo;

/**
 * Propose une tenue : un haut et un bas (ou une pièce unique), des chaussures, et une veste s'il fait frais
 * ou s'il pleut.
 *
 * Chaque vêtement reçoit une note :
 * - étiquette choisie : seuls les vêtements qui la portent, ou qui n'ont aucune étiquette, sont retenus
 *   (avec « Travail », une robe étiquetée « Soirée » est écartée) ; ceux qui la portent passent devant ;
 * - météo : saison et niveau de chaleur adaptés à la température, veste imperméable ou à capuche sous la pluie ;
 * - « Autre idée » : les vêtements déjà proposés passent derrière ;
 * - un peu de hasard, pour varier les propositions.
 */
final class GenerateurTenue
{
    /** Chance de proposer une robe ou une combinaison plutôt qu'un haut et un bas, quand c'est possible */
    private const CHANCE_UNE_PIECE = 0.3;

    /**
     * @param list<Article>               $articles candidats (voir ArticleRepository::findPourTenue)
     * @param array<int, DetailVetement>  $details  détail vêtement de chaque candidat, par identifiant d'article
     * @param list<int>                   $eviter   articles de la proposition précédente
     *
     * @return list<array{zone: ZoneTenue, article: ?Article, pluie: bool}> une ligne par zone ; article null
     *                                                                         si aucun vêtement ne convient
     */
    public function proposer(array $articles, array $details, ?Etiquette $etiquette, ?Meteo $meteo, array $eviter, \DateTimeImmutable $date): array
    {
        $candidats = array_values(array_filter(
            $articles,
            static fn (Article $article) => isset($details[$article->getId()])
                && (null === $etiquette || $article->getEtiquettes()->isEmpty() || $article->getEtiquettes()->contains($etiquette)),
        ));
        $parZone = [];
        foreach ($candidats as $article) {
            $parZone[$article->getCategorie()->getZoneTenue()->value][] = $article;
        }
        $de = static fn (ZoneTenue $zone): array => $parZone[$zone->value] ?? [];

        $saison = null !== $meteo ? ($meteo->temperature < 15 ? Saison::AUTOMNE_HIVER : Saison::PRINTEMPS_ETE)
            : (\in_array((int) $date->format('n'), [4, 5, 6, 7, 8, 9], true) ? Saison::PRINTEMPS_ETE : Saison::AUTOMNE_HIVER);
        $chaleurIdeale = null === $meteo ? 3 : ($meteo->estFroid() ? 4 : ($meteo->estChaud() ? 1 : 3));
        $pluie = $meteo?->pluie ?? false;

        $unePiece = [] !== $de(ZoneTenue::UNE_PIECE)
            && ([] === $de(ZoneTenue::HAUT) || [] === $de(ZoneTenue::BAS) || mt_rand() / mt_getrandmax() < self::CHANCE_UNE_PIECE);
        $zones = $unePiece ? [ZoneTenue::UNE_PIECE] : [ZoneTenue::HAUT, ZoneTenue::BAS];
        // Une veste, sauf par temps chaud et sec
        if ($pluie || null === $meteo || !$meteo->estChaud()) {
            $zones[] = ZoneTenue::COUCHE_EXTERIEURE;
        }
        $zones[] = ZoneTenue::CHAUSSURES;

        $tenue = [];
        foreach ($zones as $zone) {
            $meilleur = null;
            $meilleureNote = null;
            foreach ($de($zone) as $article) {
                $detail = $details[$article->getId()];
                $note = $this->noter($article, $detail, $zone, $etiquette, $saison, $chaleurIdeale, $pluie, $eviter);
                if (null === $meilleureNote || $note > $meilleureNote) {
                    [$meilleur, $meilleureNote] = [$article, $note];
                }
            }
            $protege = null !== $meilleur && ($details[$meilleur->getId()]->isEstImpermeable() || $details[$meilleur->getId()]->isACapuche());
            $tenue[] = ['zone' => $zone, 'article' => $meilleur, 'pluie' => $pluie && ZoneTenue::COUCHE_EXTERIEURE === $zone && $protege];
        }

        return $tenue;
    }

    /**
     * @param list<int> $eviter
     */
    private function noter(Article $article, DetailVetement $detail, ZoneTenue $zone, ?Etiquette $etiquette, Saison $saison, int $chaleurIdeale, bool $pluie, array $eviter): float
    {
        $note = mt_rand(0, 150) / 100;

        if (null !== $etiquette && $article->getEtiquettes()->contains($etiquette)) {
            $note += 3;
        }

        $note += match ($detail->getSaison()) {
            $saison => 2,
            Saison::TOUTES => 1,
            default => -3,
        };
        $note -= abs($detail->getNiveauChaleur() - $chaleurIdeale) * 0.75;

        if ($pluie && ZoneTenue::COUCHE_EXTERIEURE === $zone) {
            $note += ($detail->isEstImpermeable() ? 4 : 0) + ($detail->isACapuche() ? 2 : 0);
        }

        if (\in_array($article->getId(), $eviter, true)) {
            $note -= 5;
        }

        return $note;
    }
}
