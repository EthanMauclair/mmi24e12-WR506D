<?php

namespace App\Service\Meteo;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Météo lue en direct sur Open-Meteo (gratuit, sans clé), et nom de la ville sur OpenStreetMap (Nominatim).
 *
 * La position est arrondie à environ 1 km et les réponses sont gardées en cache : les deux services
 * ne sont pas interrogés à chaque « Autre idée », et la position exacte ne quitte pas le serveur.
 */
final class ServiceMeteo
{
    private const URL_METEO = 'https://api.open-meteo.com/v1/forecast';
    private const URL_VILLE = 'https://nominatim.openstreetmap.org/reverse';

    /** Codes météo WMO renvoyés par Open-Meteo */
    private const LIBELLES = [
        0 => 'Ensoleillé', 1 => 'Peu nuageux', 2 => 'Nuageux', 3 => 'Couvert', 45 => 'Brouillard', 48 => 'Brouillard',
        51 => 'Bruine', 53 => 'Bruine', 55 => 'Bruine', 56 => 'Bruine verglaçante', 57 => 'Bruine verglaçante',
        61 => 'Pluie', 63 => 'Pluie', 65 => 'Forte pluie', 66 => 'Pluie verglaçante', 67 => 'Pluie verglaçante',
        71 => 'Neige', 73 => 'Neige', 75 => 'Forte neige', 77 => 'Neige', 80 => 'Averses', 81 => 'Averses',
        82 => 'Fortes averses', 85 => 'Averses de neige', 86 => 'Averses de neige', 95 => 'Orage', 96 => 'Orage', 99 => 'Orage',
    ];

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return Meteo|null null si la météo n'a pas pu être lue (le générateur se base alors sur la saison)
     */
    public function actuelle(float $latitude, float $longitude): ?Meteo
    {
        $latitude = round($latitude, 2);
        $longitude = round($longitude, 2);
        $donnees = $this->cache->get(self::cle('meteo', $latitude, $longitude), function (ItemInterface $element) use ($latitude, $longitude): ?array {
            $element->expiresAfter(900);
            try {
                $reponse = $this->client->request('GET', self::URL_METEO, [
                    'query' => ['latitude' => $latitude, 'longitude' => $longitude, 'current' => 'temperature_2m,precipitation,weather_code'],
                    'timeout' => 5,
                ])->toArray();
            } catch (ExceptionInterface $e) {
                $this->logger->warning('Météo indisponible : {erreur}', ['erreur' => $e->getMessage()]);
                $element->expiresAfter(60);

                return null;
            }

            return $reponse['current'] ?? null;
        });

        if (!\is_array($donnees) || !isset($donnees['temperature_2m'], $donnees['weather_code'])) {
            return null;
        }

        $code = (int) $donnees['weather_code'];
        // Pluie, bruine, averses, orage ou neige : on cherche une veste imperméable ou à capuche
        $pluie = ($code >= 51 && $code <= 99) || (float) ($donnees['precipitation'] ?? 0) > 0;

        return new Meteo(
            $this->ville($latitude, $longitude),
            round((float) $donnees['temperature_2m']),
            $pluie,
            self::LIBELLES[$code] ?? ($pluie ? 'Pluie' : 'Nuageux'),
        );
    }

    private function ville(float $latitude, float $longitude): ?string
    {
        return $this->cache->get(self::cle('ville', $latitude, $longitude), function (ItemInterface $element) use ($latitude, $longitude): ?string {
            $element->expiresAfter(86400);
            try {
                $adresse = $this->client->request('GET', self::URL_VILLE, [
                    'query' => ['format' => 'jsonv2', 'lat' => $latitude, 'lon' => $longitude, 'zoom' => 10, 'accept-language' => 'fr'],
                    // Nominatim demande que chaque application s'identifie
                    'headers' => ['User-Agent' => 'Penderie/1.0 (projet etudiant WR506D)'],
                    'timeout' => 4,
                ])->toArray()['address'] ?? [];
            } catch (ExceptionInterface $e) {
                $this->logger->info('Ville introuvable : {erreur}', ['erreur' => $e->getMessage()]);
                $element->expiresAfter(300);

                return null;
            }

            return $adresse['city'] ?? $adresse['town'] ?? $adresse['village'] ?? $adresse['municipality'] ?? null;
        });
    }

    /** Les clés de cache n'acceptent ni « . » ni « - » : on hache la position */
    private static function cle(string $prefixe, float $latitude, float $longitude): string
    {
        return $prefixe.'_'.md5($latitude.'_'.$longitude);
    }
}
