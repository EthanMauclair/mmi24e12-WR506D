<?php

namespace App\Service\CodeBarres;

use App\Enum\FormatVinyle;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Retrouve un livre (ISBN) ou un vinyle (EAN) à partir de son code-barres, dans des bases ouvertes et gratuites :
 * Open Library puis Google Books pour les livres, MusicBrainz pour les disques.
 */
final class RechercheCodeBarres
{
    // MusicBrainz exige un User-Agent identifiant l'application
    private const USER_AGENT = 'Penderie/1.0 (https://github.com/EthanMauclair/mmi24e12-WR506D)';

    private readonly HttpClientInterface $client;

    /** Vrai si une base n'a pas répondu pendant la dernière recherche */
    private bool $baseInjoignable = false;

    public function __construct(HttpClientInterface $client, private readonly LoggerInterface $logger)
    {
        // MusicBrainz limite à une requête par seconde (503 au-delà) : on réessaie après un court délai
        $this->client = new RetryableHttpClient($client, maxRetries: 2, logger: $logger);
    }

    /**
     * Garde uniquement les chiffres (et le X final d'un ISBN-10).
     */
    public static function normaliser(string $code): string
    {
        return strtoupper((string) preg_replace('/[^0-9Xx]/', '', $code));
    }

    public static function estIsbn(string $code): bool
    {
        return 1 === preg_match('/^(97[89]\d{10}|\d{9}[\dX])$/', $code);
    }

    /**
     * @return array<string, mixed>|null fiche trouvée, ou null si le code est inconnu
     *
     * @throws BaseInjoignableException si aucune base n'a pu répondre : on ne peut pas conclure que le code est inconnu
     */
    public function rechercher(string $code): ?array
    {
        $code = self::normaliser($code);
        $this->baseInjoignable = false;

        if (self::estIsbn($code)) {
            $fiche = $this->livreOpenLibrary($code);
            // Google Books n'est qu'un secours : son quota sans clé est souvent épuisé
            if (null === $fiche) {
                $erreurOpenLibrary = $this->baseInjoignable;
                $fiche = $this->livreGoogleBooks($code);
                $this->baseInjoignable = $erreurOpenLibrary;
            }
        } else {
            $fiche = 1 === preg_match('/^\d{8,14}$/', $code) ? $this->disqueMusicBrainz($code) : null;
        }

        if (null === $fiche && $this->baseInjoignable) {
            throw new BaseInjoignableException();
        }

        return $fiche;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function livreOpenLibrary(string $isbn): ?array
    {
        $donnees = $this->obtenir('https://openlibrary.org/api/books', [
            'bibkeys' => 'ISBN:'.$isbn,
            'format' => 'json',
            'jscmd' => 'data',
        ]);
        $livre = $donnees['ISBN:'.$isbn] ?? null;
        if (!\is_array($livre) || empty($livre['title'])) {
            return null;
        }

        return $this->livre(
            $isbn,
            trim($livre['title'].(empty($livre['subtitle']) ? '' : ' : '.$livre['subtitle'])),
            $livre['authors'][0]['name'] ?? null,
            $livre['publishers'][0]['name'] ?? null,
            $livre['number_of_pages'] ?? null,
            $livre['publish_date'] ?? null,
            $livre['cover']['large'] ?? $livre['cover']['medium'] ?? null,
            array_map(static fn ($sujet) => $sujet['name'] ?? '', $livre['subjects'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function livreGoogleBooks(string $isbn): ?array
    {
        $donnees = $this->obtenir('https://www.googleapis.com/books/v1/volumes', ['q' => 'isbn:'.$isbn]);
        $livre = $donnees['items'][0]['volumeInfo'] ?? null;
        if (!\is_array($livre) || empty($livre['title'])) {
            return null;
        }

        return $this->livre(
            $isbn,
            trim($livre['title'].(empty($livre['subtitle']) ? '' : ' : '.$livre['subtitle'])),
            $livre['authors'][0] ?? null,
            $livre['publisher'] ?? null,
            $livre['pageCount'] ?? null,
            $livre['publishedDate'] ?? null,
            $livre['imageLinks']['thumbnail'] ?? null,
            $livre['categories'] ?? [],
        );
    }

    /**
     * @param list<string> $sujets
     *
     * @return array<string, mixed>
     */
    private function livre(string $isbn, string $titre, ?string $auteur, ?string $editeur, mixed $nbPages, ?string $date, ?string $couverture, array $sujets): array
    {
        return [
            'type' => 'LIVRE',
            'code' => $isbn,
            'titre' => mb_substr($titre, 0, 150),
            'categorie' => self::categorieLivre($sujets),
            'couverture' => self::urlSecurisee($couverture),
            'livre' => [
                'isbn' => \strlen($isbn) <= 13 ? $isbn : null,
                'auteur' => null === $auteur ? null : mb_substr($auteur, 0, 150),
                'editeur' => null === $editeur ? null : mb_substr($editeur, 0, 100),
                'nbPages' => \is_int($nbPages) && $nbPages > 0 ? $nbPages : null,
                'anneeParution' => self::annee($date),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function disqueMusicBrainz(string $ean): ?array
    {
        $donnees = $this->obtenir('https://musicbrainz.org/ws/2/release/', [
            'query' => 'barcode:'.$ean,
            'fmt' => 'json',
            'limit' => 5,
        ]);

        // La recherche plein texte renvoie toujours des résultats approchants :
        // on ne garde qu'une édition dont le code-barres est exactement celui scanné
        // (sans les zéros de tête : un UPC à 12 chiffres vaut un EAN à 13 chiffres commençant par 0)
        $disque = null;
        foreach ($donnees['releases'] ?? [] as $edition) {
            if (ltrim((string) ($edition['barcode'] ?? ''), '0') === ltrim($ean, '0')) {
                $disque = $edition;
                break;
            }
        }
        if (null === $disque || empty($disque['title'])) {
            return null;
        }

        $supports = $disque['media'] ?? [];
        $format = (string) ($supports[0]['format'] ?? '');
        $formatVinyle = match (true) {
            str_contains($format, '7"') => FormatVinyle::SINGLE_45T,
            str_contains($format, 'Vinyl') || str_contains($format, '12"') => FormatVinyle::LP_33T,
            default => FormatVinyle::AUTRE,
        };

        return [
            'type' => 'VINYLE',
            'code' => $ean,
            'titre' => mb_substr($disque['title'], 0, 150),
            'categorie' => match (true) {
                FormatVinyle::SINGLE_45T === $formatVinyle => ['Musique', 'Vinyles', 'Singles (45 tours)'],
                \count($supports) >= 3 => ['Musique', 'Vinyles', 'Coffrets'],
                default => ['Musique', 'Vinyles', 'Albums (33 tours)'],
            },
            'couverture' => 'https://coverartarchive.org/release/'.$disque['id'].'/front-500',
            'vinyle' => [
                'ean' => \strlen($ean) <= 13 ? $ean : null,
                'artiste' => isset($disque['artist-credit'][0]['name']) ? mb_substr($disque['artist-credit'][0]['name'], 0, 150) : null,
                'label' => isset($disque['label-info'][0]['label']['name']) ? mb_substr($disque['label-info'][0]['label']['name'], 0, 100) : null,
                'anneeSortie' => self::annee($disque['date'] ?? null),
                'format' => $formatVinyle->value,
                'nbDisques' => max(1, \count($supports)),
            ],
        ];
    }

    /**
     * @param array<string, scalar> $parametres
     *
     * @return array<string, mixed>
     */
    private function obtenir(string $url, array $parametres): array
    {
        try {
            return $this->client->request('GET', $url, [
                'query' => $parametres,
                'headers' => ['User-Agent' => self::USER_AGENT, 'Accept' => 'application/json'],
                'timeout' => 10,
            ])->toArray();
        } catch (ExceptionInterface $e) {
            $this->logger->warning('Recherche de code-barres impossible sur {url} : {erreur}', ['url' => $url, 'erreur' => $e->getMessage()]);
            $this->baseInjoignable = true;

            return [];
        }
    }

    /**
     * Mots-clés des sujets (anglais et français) et leur poids, par catégorie de livre.
     * Les sujets d'Open Library mélangent toutes les éditions d'une œuvre (une adaptation en BD
     * ajoute « Comics » à un roman) : on retient la catégorie qui obtient le meilleur score.
     */
    private const MOTS_CLES_LIVRES = [
        'Mangas' => [['manga'], 3],
        'Comics' => [['comic', 'graphic novel', 'superhero'], 3],
        'Bandes dessinées' => [['bande dessinée', 'bandes dessinées'], 3],
        'Cuisine' => [['cook', 'cuisine', 'recipe', 'recette'], 3],
        'Jeunesse' => [['juvenile', 'jeunesse', "children's"], 2],
        'Science-fiction et fantasy' => [['science fiction', 'fantasy', 'science-fiction'], 2],
        'Policiers et thrillers' => [['detective', 'thriller', 'policier', 'mystery', 'crime'], 2],
        'Romans' => [['fiction', 'novel', 'roman', 'novela'], 1],
    ];

    private const SOUS_CATEGORIES_LIVRES = [
        'Mangas' => 'BD, comics et mangas',
        'Comics' => 'BD, comics et mangas',
        'Bandes dessinées' => 'BD, comics et mangas',
        'Cuisine' => 'Documentaires',
        'Jeunesse' => 'Documentaires',
        'Science-fiction et fantasy' => 'Romans',
        'Policiers et thrillers' => 'Romans',
        'Romans' => 'Romans',
    ];

    /**
     * Catégorie proposée d'après les sujets du livre (racine › sous-catégorie › catégorie).
     *
     * @param list<string> $sujets
     *
     * @return list<string>
     */
    private static function categorieLivre(array $sujets): array
    {
        $scores = array_fill_keys(array_keys(self::MOTS_CLES_LIVRES), 0);
        foreach ($sujets as $sujet) {
            $sujet = mb_strtolower($sujet);
            foreach (self::MOTS_CLES_LIVRES as $categorie => [$motsCles, $poids]) {
                foreach ($motsCles as $motCle) {
                    if (str_contains($sujet, $motCle)) {
                        $scores[$categorie] += $poids;
                        break;
                    }
                }
            }
        }

        arsort($scores);
        $meilleure = array_key_first($scores);
        $categorie = $scores[$meilleure] > 0 ? $meilleure : 'Romans';

        return ['Livres', self::SOUS_CATEGORIES_LIVRES[$categorie], $categorie];
    }

    private static function annee(?string $date): ?int
    {
        return null !== $date && 1 === preg_match('/\b(\d{4})\b/', $date, $correspondance) ? (int) $correspondance[1] : null;
    }

    private static function urlSecurisee(?string $url): ?string
    {
        return null === $url ? null : preg_replace('#^http://#', 'https://', $url);
    }
}
