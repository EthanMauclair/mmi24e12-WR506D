# Penderie — mmi24e12-WR506D

Application web pour indexer les articles d'un logement (vêtements, vinyles, livres, comics…) et retrouver où chacun est rangé : Logement › Pièce › Stockage › Article.

API **Symfony 7.4 LTS**, interface **Vue.js 3**, base **MariaDB 13**, le tout lancé avec **Docker**.

## Prérequis

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (lancé avant toute commande)
- Git
- [Postman](https://www.postman.com/downloads/) (facultatif, pour tester l'API)

Aucune installation locale de PHP, Composer, Node ou MariaDB n'est nécessaire : tout tourne dans les conteneurs.

## Installation

```bash
git clone https://github.com/<votre-compte>/mmi24e12-WR506D.git
cd mmi24e12-WR506D
cp .env.example .env
```

Ouvrez `.env` et remplacez les valeurs `change_me` :

- `MARIADB_PASSWORD` et `MARIADB_ROOT_PASSWORD` : mots de passe de votre choix
- `APP_SECRET` : une chaîne aléatoire, par exemple générée avec `openssl rand -hex 32`
- `OPENROUTER_API_KEY` (facultatif) : clé gratuite à créer sur [openrouter.ai/keys](https://openrouter.ai/keys), pour la reconnaissance des vêtements par IA. Sans clé, le scan garde la photo et les champs se remplissent à la main.

## Lancement

```bash
docker compose up -d --build
```

Le premier démarrage prend quelques minutes : les dépendances PHP (`composer install`) et JavaScript (`npm install`) sont installées automatiquement dans les conteneurs, puis les migrations de base de données sont appliquées.

| Service      | URL                               |
|--------------|-----------------------------------|
| Frontend Vue | http://localhost:5173             |
| API Symfony  | http://localhost:8080/api/health  |
| MariaDB      | `localhost:3306`                  |

Si l'API répond `{"status":"ok"}`, tout fonctionne. Les ports se changent dans `.env` s'ils sont déjà pris.

Chargez ensuite les référentiels (catégories, couleurs) et les données de démonstration :

```bash
docker compose exec php bin/console doctrine:fixtures:load -n
```

Attention : cette commande vide la base avant de la remplir.

Suivre la progression du premier démarrage :

```bash
docker compose logs -f php frontend
```

Arrêter le projet :

```bash
docker compose down          # conserve la base de données
docker compose down -v       # supprime aussi les données et les dépendances installées
```

## Comptes de démonstration

Après le chargement des fixtures, tous les comptes ont le mot de passe `penderie2026` :

| E-mail             | Rôle dans « Appartement Lyon »                         |
|--------------------|--------------------------------------------------------|
| `lea@exemple.fr`   | Propriétaire : gère les pièces et les stockages        |
| `tom@exemple.fr`   | Co-propriétaire                                        |
| `ines@exemple.fr`  | Co-propriétaire                                        |
| `jules@exemple.fr` | Membre mineur, rattaché à Léa                          |
| `sam@exemple.fr`   | Aucun logement : peut créer le sien                    |

Léa possède un article personnel (« Montre de ma grand-mère ») que les autres membres ne voient pas.

Vous pouvez aussi créer votre propre compte depuis l'écran d'inscription.

## Fonctionnalités disponibles

- **Authentification** : inscription (un mineur indique l'e-mail du compte majeur qui le rattache), connexion, déconnexion. La session est un cookie géré par Symfony ; 5 tentatives de connexion échouées bloquent temporairement le compte.
- **Inventaire** :
  - créer un logement, choisir le logement affiché, voir ses chiffres clés ;
  - ajouter des pièces et des stockages (propriétaire du logement uniquement) ;
  - parcourir les articles d'un stockage, ouvrir la fiche d'un article ;
  - rechercher « Où est rangé… ? » par titre, catégorie, marque ou couleur ;
  - rendre un article personnel (visible uniquement par la personne qui l'a ajouté).
- **Ajout d'article par scan** (bouton central de la barre de navigation) :
  - **vêtement** : la photo est réduite (1024 px), convertie en JPEG puis envoyée en base64 à un modèle de vision d'OpenRouter (`google/gemma-4-31b-it:free` par défaut, modifiable avec `OPENROUTER_MODELE`), qui propose la catégorie, la couleur, la marque si elle est lisible et un titre. 30 analyses par heure et par utilisateur ;
  - **livre ou vinyle** : le code-barres est lu dans le navigateur, puis la fiche est cherchée dans Open Library (et Google Books en secours) pour les livres, MusicBrainz pour les disques ;
  - **saisie manuelle**, aussi proposée quand un code-barres n'est dans aucune base ;
  - tous les champs restent modifiables avant l'enregistrement ; la photo du scan est conservée.

La caméra du navigateur n'est accessible que sur une page sécurisée : `http://localhost` fonctionne, mais depuis un téléphone sur le réseau local il faut une adresse en HTTPS. À défaut, le bouton « Importer depuis la galerie » permet de choisir ou de prendre une photo.

Les autres écrans (tenues, marché, profil, prêts) arrivent dans les prochaines versions.

## API

| Méthode | Route                                   | Description                                    |
|---------|-----------------------------------------|------------------------------------------------|
| POST    | `/api/inscription`                      | Crée un compte et ouvre la session             |
| POST    | `/api/connexion`                        | Ouvre la session (`email`, `motDePasse`)       |
| POST    | `/api/deconnexion`                      | Ferme la session                               |
| GET     | `/api/moi`                              | Utilisateur connecté                           |
| GET     | `/api/logements`                        | Logements dont on est propriétaire ou membre   |
| POST    | `/api/logements`                        | Crée un logement                               |
| GET     | `/api/logements/{id}`                   | Détail : chiffres clés et pièces               |
| GET     | `/api/logements/{id}/recherche?q=…`     | Recherche d'articles avec leur emplacement     |
| POST    | `/api/logements/{id}/pieces`            | Ajoute une pièce                               |
| GET     | `/api/pieces/{id}`                      | Détail : stockages et articles                 |
| POST    | `/api/pieces/{id}/stockages`            | Ajoute un stockage                             |
| GET     | `/api/logements/{id}/rangement`         | Emplacements et étiquettes pour l'ajout        |
| GET     | `/api/referentiels`                     | Catégories, couleurs, formats et états         |
| POST    | `/api/scans/vetement`                   | Envoie la photo d'un vêtement (`photo`, multipart) et renvoie les suggestions de l'IA |
| GET     | `/api/scans/code-barres/{code}`         | Fiche d'un livre (ISBN) ou d'un vinyle (EAN)   |
| POST    | `/api/articles`                         | Ajoute un article avec son détail et sa photo  |
| GET     | `/api/articles/{id}`                    | Fiche d'un article                             |
| PATCH   | `/api/articles/{id}`                    | Rend un article personnel ou non               |

Les erreurs sont renvoyées en JSON : `{"message": "..."}`, avec un objet `violations` (`{"champ": "message"}`) pour les erreurs de validation (422).

## Commandes utiles

```bash
docker compose exec php bin/console make:entity          # créer une entité
docker compose exec php bin/console make:migration        # générer une migration
docker compose exec php bin/console doctrine:migrations:migrate
docker compose exec php composer require <paquet>
docker compose exec frontend npm install <paquet>
docker compose exec frontend npm run lint
```

Les dépendances PHP (`vendor/`) et le cache Symfony (`var/`) vivent dans des volumes Docker, pour de meilleures performances sous Windows et macOS : utilisez toujours Composer via `docker compose exec php`.

Les photos des scans sont enregistrées dans `backend/public/uploads/` (non versionné).

Après une modification de `.env` (par exemple l'ajout de la clé OpenRouter), recréez le conteneur PHP pour qu'il la prenne en compte :

```bash
docker compose up -d php
```

## Postman

Importez `postman/penderie.postman_collection.json` dans Postman. Lancez d'abord « Authentification › Connexion » : Postman garde le cookie de session pour les requêtes suivantes, et les requêtes d'inventaire remplissent elles-mêmes les identifiants (logement, pièce, article) à réutiliser.

## Structure

```
backend/      API Symfony 7.4
frontend/     Application Vue 3 (Vite, Vue Router, Pinia)
docker/       Images et configuration (PHP-FPM, Nginx)
postman/      Collection Postman de l'API
ia-usage/     Suivi des prompts IA utilisés
compose.yaml  Orchestration des services
```

En développement, le serveur Vite relaie les appels `/api/*` vers Symfony : le front et l'API partagent la même origine, ce qui permet l'authentification par cookie de session.

## Workflow Git

Le projet suit **Gitflow** :

- `main` : toujours déployable, ne reçoit que des fusions de `develop` (release) ou de `hotfix/…`
- `develop` : branche d'intégration
- `feature/<nom>` : une branche par fonctionnalité, créée depuis `develop`
- `hotfix/<nom>` : correctif urgent, créé depuis `main`

Aucun commit direct sur `main` ni sur `develop` : tout passe par une **Pull Request** (description rédigée, relecture, puis fusion), même en travaillant seul.

Les messages de commit suivent les [Conventional Commits](https://www.conventionalcommits.org/fr/) au présent de l'impératif, un commit par intention :

```
feat: ajoute la recherche d'un article
fix: corrige le décompte des articles prêtés
docs: complète la procédure d'installation
```

Préfixes utilisés : `feat`, `fix`, `docs`, `refactor`, `test`, `chore`.

Ne sont jamais versionnés : `vendor/`, `node_modules/`, `.env`, `.env.local` et tout secret.

## Usage de l'IA

Les prompts utilisés avec un assistant IA sont consignés dans [ia-usage/prompts.md](ia-usage/prompts.md).
