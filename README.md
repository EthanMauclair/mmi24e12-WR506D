# Pendu — mmi24e12-WR506D

Application web du jeu du pendu : API **Symfony 7.4 LTS**, interface **Vue.js 3**, base **MariaDB 13**, le tout lancé avec **Docker**.

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

Suivre la progression du premier démarrage :

```bash
docker compose logs -f php frontend
```

Arrêter le projet :

```bash
docker compose down          # conserve la base de données
docker compose down -v       # supprime aussi les données
```

## Commandes utiles

```bash
docker compose exec php bin/console make:entity          # créer une entité
docker compose exec php bin/console make:migration        # générer une migration
docker compose exec php bin/console doctrine:migrations:migrate
docker compose exec php composer require <paquet>
docker compose exec frontend npm install <paquet>
docker compose exec frontend npm run lint
```

## Postman

Importez `postman/hangman.postman_collection.json` dans Postman. La variable `baseUrl` vaut `http://localhost:8080` par défaut.

## Structure

```
backend/      API Symfony 7.4
frontend/     Application Vue 3 (Vite, Vue Router, Pinia)
docker/       Images et configuration (PHP-FPM, Nginx)
postman/      Collection Postman de l'API
ia-usage/     Suivi des prompts IA utilisés
compose.yaml  Orchestration des services
```

En développement, le serveur Vite relaie les appels `/api/*` vers Symfony : le frontend peut donc appeler `fetch('/api/...')` directement.

## Workflow Git

Le projet suit **Gitflow** :

- `main` : toujours déployable, ne reçoit que des fusions de `develop` (release) ou de `hotfix/…`
- `develop` : branche d'intégration
- `feature/<nom>` : une branche par fonctionnalité, créée depuis `develop`
- `hotfix/<nom>` : correctif urgent, créé depuis `main`

Aucun commit direct sur `main` ni sur `develop` : tout passe par une **Pull Request** (description rédigée, relecture, puis fusion), même en travaillant seul.

Les messages de commit suivent les [Conventional Commits](https://www.conventionalcommits.org/fr/) au présent de l'impératif, un commit par intention :

```
feat: ajoute le choix aléatoire d'un mot
fix: corrige le décompte des erreurs
docs: complète la procédure d'installation
```

Préfixes utilisés : `feat`, `fix`, `docs`, `refactor`, `test`, `chore`.

Ne sont jamais versionnés : `vendor/`, `node_modules/`, `.env`, `.env.local` et tout secret.

## Usage de l'IA

Les prompts utilisés avec un assistant IA sont consignés dans [ia-usage/prompts.md](ia-usage/prompts.md).
