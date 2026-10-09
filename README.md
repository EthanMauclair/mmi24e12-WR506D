# Penderie

Application web de gestion de garde-robe, pensée d'abord pour le téléphone.

| Couche | Technologie |
|---|---|
| API | Symfony 7.4 LTS + API Platform 4, PHP 8.2 |
| Base de données | MariaDB 12.3 LTS |
| Front | Vue 3 + Vite + TypeScript (application découplée) |
| Environnement | Docker Compose (Nginx, PHP-FPM, MariaDB, Node 24) |
| Tests manuels d'API | Postman (collection versionnée dans `postman/`) |

Les décisions prises au fil du projet sont tenues dans [docs/journal-de-decisions.md](docs/journal-de-decisions.md).

---

## Prérequis

- **Docker Desktop** (sous Windows : avec le moteur WSL 2 activé)
- **Git**
- **Postman** (application de bureau ou web)

Rien d'autre : PHP, Composer, Node et MariaDB tournent dans les conteneurs.

## Installation

```bash
# 1. Cloner le dépôt et se placer sur la branche d'intégration
git clone https://github.com/EthanMauclair/mmi24e12-WR506D.git
cd mmi24e12-WR506D
git checkout develop

# 2. Créer le fichier de variables locales (jamais versionné)
cp .env.example .env
#    puis modifier les mots de passe dans .env (lettres et chiffres uniquement)

# 3. Construire et démarrer la stack
docker compose up -d --build
```

Au premier démarrage, le conteneur `php` exécute `composer install` et le conteneur `frontend` exécute `npm install`. Cela prend quelques minutes ; suivre l'avancement avec :

```bash
docker compose logs -f php frontend
```

## Adresses

| Service | URL |
|---|---|
| Application Vue | http://localhost:5173 |
| API (point d'entrée) | http://localhost:8080/api |
| Documentation de l'API (Swagger UI) | http://localhost:8080/api/docs |
| Contrat OpenAPI (JSON) | http://localhost:8080/api/docs.jsonopenapi |
| MariaDB (client SQL externe) | `localhost:3307`, identifiants du fichier `.env` |

## Commandes utiles

```bash
# Console Symfony
docker compose exec php bin/console <commande>

# Ajouter une dépendance PHP
docker compose exec php composer require <paquet>

# Ajouter une dépendance JavaScript
docker compose exec frontend npm install <paquet>

# Créer / appliquer une migration de base de données
docker compose exec php bin/console make:migration
docker compose exec php bin/console doctrine:migrations:migrate

# Arrêter la stack (les données de la base sont conservées)
docker compose down

# Tout réinitialiser, base de données comprise
docker compose down -v
```

## Postman

1. Dans Postman : **Import** → sélectionner les deux fichiers du dossier `postman/`.
2. Choisir l'environnement **« Penderie - local »** en haut à droite.
3. Lancer la collection **« Penderie API »** (bouton *Run*) : chaque requête contient des tests automatiques.

Quand l'API évolue, la collection est mise à jour **dans la même merge request** que le code.

## Organisation du dépôt

```
.
├── backend/        API Symfony 7.4 (API Platform, Doctrine)
├── frontend/       Application Vue 3 (Vite, TypeScript, Vue Router, Pinia)
├── docker/         Images et configuration (PHP-FPM, Nginx)
├── postman/        Collection et environnement Postman
├── docs/           Journal de décisions
├── compose.yaml    Stack de développement
└── .env.example    Modèle des variables locales
```

## Gitflow

| Branche | Rôle | Reçoit |
|---|---|---|
| `main` | Version livrée, toujours déployable | Uniquement des merges de `release/*` et `hotfix/*` |
| `develop` | Intégration | Uniquement des merges de `feature/*` |
| `feature/<sujet>` | Une fonctionnalité | Commits de travail |
| `release/<version>` | Préparation d'une version | Corrections de stabilisation |
| `hotfix/<sujet>` | Correctif urgent en production | Le correctif |

Règles :

- **Aucun commit direct sur `main` ni sur `develop`** : tout passe par une merge request (pull request GitHub), même en travaillant seul.
- **Commits conventionnels** à l'impératif présent : `feat:`, `fix:`, `docs:`, `refactor:`, `test:`, `chore:`.
- Une merge request = une intention, relue avant fusion (modèle dans `.github/pull_request_template.md`).

```bash
# Démarrer une fonctionnalité
git checkout develop && git pull
git checkout -b feature/mon-sujet
# ... commits ...
git push -u origin feature/mon-sujet
# puis ouvrir une pull request feature/mon-sujet -> develop sur GitHub
```
