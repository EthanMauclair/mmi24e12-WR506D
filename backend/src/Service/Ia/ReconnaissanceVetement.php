<?php

namespace App\Service\Ia;

use App\Entity\Categorie;
use App\Entity\Couleur;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Reconnaît un vêtement ou un accessoire sur une photo, via un modèle de vision d'OpenRouter.
 *
 * La réponse du modèle n'est jamais utilisée telle quelle : chaque valeur est vérifiée
 * contre les catégories et les couleurs de la base, et ignorée si elle n'y figure pas.
 */
final class ReconnaissanceVetement
{
    private const URL = 'https://openrouter.ai/api/v1/chat/completions';

    private const INSTRUCTIONS_SYSTEME = <<<'TXT'
        Tu es l'assistant d'inventaire d'une application de garde-robe. Tu reçois la photo d'UN vêtement ou d'UN accessoire et tu renvoies sa fiche sous la forme d'un unique objet JSON.

        Règles :
        1. Analyse uniquement l'article principal, au centre de la photo. Ignore le fond, les mains, le cintre et les autres objets.
        2. "categorieId" : l'identifiant (nombre entier) de la catégorie la plus précise de la liste CATÉGORIES fournie. N'utilise jamais un identifiant absent de la liste. Si rien n'indique un vêtement pour hommes, choisis la catégorie « Femmes ». Si l'article ne correspond à aucune catégorie, mets null.
        3. "couleur" : la couleur dominante de l'article, écrite exactement comme l'un des noms de la liste COULEURS. Mets « Multicolore » si aucune couleur ne domine.
        4. "marque" : uniquement si un nom de marque est réellement lisible sur l'article (étiquette, logo, inscription). Recopie-le tel qu'il est écrit. Ne déduis jamais une marque à partir du style : en cas de doute, mets null.
        5. "titre" : un nom court et descriptif en français, de 2 à 5 mots, sans la marque ni la couleur (exemples : « Pull col roulé », « Veste en jean », « Baskets montantes »).
        6. "confiance" : un nombre entre 0 et 1 qui indique ta certitude sur la catégorie.

        Réponds uniquement avec l'objet JSON, sans texte avant ni après, sans bloc de code, au format exact :
        {"categorieId": 12, "couleur": "Bordeaux", "marque": "COS", "titre": "Pull col roulé", "confiance": 0.8}
        TXT;

    /**
     * Temps total accordé à l'analyse, en secondes. Au-delà, les fournisseurs gratuits sont saturés
     * et l'utilisateur, qui a déjà la fiche sous les yeux, doit vite savoir qu'il remplit les champs
     * lui-même. Reste sous les 90 s d'attente de Nginx.
     */
    private const BUDGET_SECONDES = 20;

    /**
     * Le modèle principal répond en 2 à 3 s quand il est disponible : sans réponse passé ce délai,
     * le modèle de secours (chez un autre fournisseur) est interrogé en parallèle, et la première
     * fiche valide l'emporte.
     */
    private const DELAI_AVANT_SECOURS = 3;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'OPENROUTER_API_KEY')] private readonly string $cleApi,
        #[Autowire(env: 'OPENROUTER_MODELE')] private readonly string $modele,
        #[Autowire(env: 'OPENROUTER_MODELE_SECOURS')] private readonly string $modeleSecours,
    ) {
    }

    public function estDisponible(): bool
    {
        return '' !== trim($this->cleApi);
    }

    /**
     * @param string          $imageBase64 data URL JPEG (data:image/jpeg;base64,…)
     * @param list<Categorie> $categories  catégories proposables (feuilles de l'arborescence)
     * @param list<Couleur>   $couleurs
     *
     * @return array{categorie: ?Categorie, couleur: ?Couleur, marque: ?string, titre: ?string}|null
     *                                                                                                null si le modèle n'a pas pu répondre
     */
    public function reconnaitre(string $imageBase64, array $categories, array $couleurs): ?array
    {
        if (!$this->estDisponible()) {
            return null;
        }

        // Les modèles gratuits sont partagés par tous les utilisateurs d'OpenRouter : ils répondent
        // tantôt en 2 s, tantôt jamais. Plutôt que d'attendre un modèle saturé, on en interroge un second.
        $debut = microtime(true);
        $modeleSecours = trim($this->modeleSecours);
        $secoursDisponible = '' !== $modeleSecours && $modeleSecours !== trim($this->modele);
        $essais = [$this->interroger(trim($this->modele), $imageBase64, $categories, $couleurs, self::BUDGET_SECONDES)];

        while ([] !== $essais || $secoursDisponible) {
            $ecoule = microtime(true) - $debut;
            if ($ecoule >= self::BUDGET_SECONDES) {
                break;
            }
            // Secours lancé si le modèle principal tarde, ou tout de suite s'il a déjà échoué
            if ($secoursDisponible && ([] === $essais || $ecoule >= self::DELAI_AVANT_SECOURS)) {
                $essais[] = $this->interroger($modeleSecours, $imageBase64, $categories, $couleurs, self::BUDGET_SECONDES - $ecoule);
                $secoursDisponible = false;
            }
            $limite = $secoursDisponible ? self::DELAI_AVANT_SECOURS : self::BUDGET_SECONDES;

            foreach ($this->client->stream($essais, max(0.1, $limite - $ecoule)) as $reponse => $morceau) {
                try {
                    $termine = false;
                    if (!$morceau->isTimeout()) {
                        // Lire le statut tout de suite : sinon le flux lève une exception sur une erreur 5xx
                        // au lieu de laisser lire() la traiter comme l'échec de ce seul modèle
                        if ($morceau->isFirst()) {
                            $reponse->getStatusCode();
                        }
                        $termine = $morceau->isLast();
                    }
                } catch (ExceptionInterface $e) {
                    $termine = true;
                }
                if (!$termine) {
                    if (microtime(true) - $debut >= $limite) {
                        break;
                    }
                    continue;
                }

                $essais = array_values(array_filter($essais, static fn ($essai) => $essai !== $reponse));
                $texte = $this->lire($reponse);
                $resultat = null === $texte ? null : $this->interpreter($texte, $categories, $couleurs);
                if (null !== $resultat) {
                    foreach ($essais as $essai) {
                        $essai->cancel();
                    }

                    return $resultat;
                }
                // Essai raté : on reprend la boucle avec ceux qui restent (et le secours s'il n'est pas lancé)
                break;
            }
        }

        foreach ($essais as $essai) {
            $essai->cancel();
        }
        $this->logger->warning('Aucun modèle n\'a répondu à temps pour reconnaître le vêtement.');

        return null;
    }

    /**
     * Lance un appel à OpenRouter, sans attendre sa réponse.
     *
     * @param list<Categorie> $categories
     * @param list<Couleur>   $couleurs
     */
    private function interroger(string $modele, string $imageBase64, array $categories, array $couleurs, float $dureeMax): ResponseInterface
    {
        return $this->client->request('POST', self::URL, [
            'auth_bearer' => $this->cleApi,
            'headers' => ['X-Title' => 'Penderie'],
            'max_duration' => $dureeMax,
            'json' => [
                'model' => $modele,
                'temperature' => 0.1,
                // Gemma 4 raisonne avant de répondre (jusqu'à ~1 500 jetons, impossible à désactiver chez
                // le fournisseur gratuit) : une limite trop basse est consommée avant d'écrire le JSON
                'max_tokens' => 4000,
                // Le raisonnement n'est pas renvoyé : seule la fiche JSON nous intéresse
                'reasoning' => ['exclude' => true],
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => self::INSTRUCTIONS_SYSTEME],
                    ['role' => 'user', 'content' => [
                        ['type' => 'text', 'text' => $this->demande($categories, $couleurs)],
                        ['type' => 'image_url', 'image_url' => ['url' => $imageBase64]],
                    ]],
                ],
            ],
            'user_data' => $modele,
        ]);
    }

    /**
     * @return string|null texte de la réponse, ou null si le modèle n'a pas répondu
     */
    private function lire(ResponseInterface $reponse): ?string
    {
        try {
            $donnees = $reponse->toArray();
        } catch (ExceptionInterface $e) {
            $this->logger->warning('Reconnaissance du vêtement impossible avec {modele} : {erreur}', [
                'modele' => $reponse->getInfo('user_data'),
                'erreur' => $e->getMessage(),
            ]);

            return null;
        }

        $texte = $donnees['choices'][0]['message']['content'] ?? null;
        if (!\is_string($texte)) {
            // finish_reason « length » : la limite de jetons a été atteinte avant la réponse
            $this->logger->warning('Le modèle {modele} n\'a pas renvoyé de fiche (fin : {fin}, erreur : {erreur}).', [
                'modele' => $donnees['model'] ?? '?',
                'fin' => $donnees['choices'][0]['finish_reason'] ?? '?',
                'erreur' => $donnees['error']['message'] ?? 'aucune',
            ]);

            return null;
        }

        return $texte;
    }

    /**
     * @param list<Categorie> $categories
     * @param list<Couleur>   $couleurs
     */
    private function demande(array $categories, array $couleurs): string
    {
        $lignesCategories = array_map(
            static fn (Categorie $c) => $c->getId().' | '.$c->getChemin(),
            $categories,
        );
        $nomsCouleurs = array_map(static fn (Couleur $c) => $c->getNom(), $couleurs);

        return "Voici la photo d'un article que l'utilisateur veut ranger dans son dressing. "
            ."Identifie sa catégorie, sa couleur dominante, sa marque si elle est lisible, et donne-lui un titre, en respectant strictement les règles.\n\n"
            ."CATÉGORIES (identifiant | chemin) :\n".implode("\n", $lignesCategories)."\n\n"
            .'COULEURS : '.implode(', ', $nomsCouleurs);
    }

    /**
     * @param list<Categorie> $categories
     * @param list<Couleur>   $couleurs
     *
     * @return array{categorie: ?Categorie, couleur: ?Couleur, marque: ?string, titre: ?string}|null
     */
    private function interpreter(string $texte, array $categories, array $couleurs): ?array
    {
        // Certains modèles entourent malgré tout le JSON d'un bloc de code ou de texte
        $debut = strpos($texte, '{');
        $fin = strrpos($texte, '}');
        $json = false === $debut || false === $fin ? null : json_decode(substr($texte, $debut, $fin - $debut + 1), true);
        if (!\is_array($json)) {
            $this->logger->warning('Le modèle n\'a pas renvoyé de JSON valide.', ['texte' => $texte]);

            return null;
        }

        $categorie = null;
        foreach ($categories as $candidate) {
            if ($candidate->getId() === (int) ($json['categorieId'] ?? 0)) {
                $categorie = $candidate;
            }
        }

        $couleur = null;
        $nomCouleur = mb_strtolower(trim((string) ($json['couleur'] ?? '')));
        foreach ($couleurs as $candidate) {
            if (mb_strtolower($candidate->getNom()) === $nomCouleur) {
                $couleur = $candidate;
            }
        }

        return [
            'categorie' => $categorie,
            'couleur' => $couleur,
            'marque' => self::texteCourt($json['marque'] ?? null, 80),
            'titre' => self::texteCourt($json['titre'] ?? null, 150),
        ];
    }


    private static function texteCourt(mixed $valeur, int $longueurMax): ?string
    {
        if (!\is_string($valeur) || '' === trim($valeur) || 'null' === mb_strtolower(trim($valeur))) {
            return null;
        }

        return mb_substr(trim($valeur), 0, $longueurMax);
    }
}
