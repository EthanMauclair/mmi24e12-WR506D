# Journal de décisions

Une entrée par décision. On ne réécrit pas une entrée : si une décision change, on ajoute une nouvelle entrée qui la remplace, et on met à jour le statut de l'ancienne.

**Statuts :** `Proposée` · `Acceptée` · `À revoir` · `Remplacée par D-xxx`

Les justifications détaillées sont dans [ANNEXE-justifications.md](ANNEXE-justifications.md).

---

## D-001 : Initialiser l'infrastructure avant le pitch 1

- **Date :** 2026-10-09
- **Statut :** Acceptée (écart assumé)
- **Contexte :** le module interdit tout code applicatif avant la validation du pitch 1.
- **Décision :** initialiser dès maintenant les squelettes Symfony et Vue et la stack Docker, sans entité, sans écran, sans règle métier.
- **Alternatives :** dépôt documentaire uniquement jusqu'au pitch 1.
- **Raison :** vérifier tôt que l'environnement fonctionne sur les machines de travail, sans figer le modèle métier.
- **Risque :** que ces commits soient considérés comme du code avant le pitch 1. Si le pitch 1 retient un autre front, `frontend/` sera supprimé.

## D-002 : Symfony 7.4 LTS

- **Date :** 2026-10-09
- **Statut :** Acceptée
- **Décision :** Symfony 7.4 LTS (sécurité jusqu'en novembre 2029).
- **Alternatives :** Symfony 8.1, fin de support en janvier 2027, PHP 8.4 obligatoire.
- **Raison :** durée de vie supérieure à celle du projet ; compatible avec PHP 8.2 ; cliente sans équipe technique.

## D-003 : API Platform pour l'API

- **Date :** 2026-10-09
- **Statut :** Acceptée
- **Décision :** API Platform 4, sous `/api`, formats JSON-LD et JSON.
- **Alternatives :** contrôleurs Symfony écrits à la main.
- **Raison :** contrat OpenAPI généré, pagination, validation et sécurité par opération.
- **Conséquence :** groupes de sérialisation explicites obligatoires sur chaque ressource.

## D-004 : PHP 8.2

- **Date :** 2026-10-09
- **Statut :** À revoir avant la mise en production
- **Décision :** PHP 8.2, contrainte de départ du projet.
- **Risque :** fin des correctifs de sécurité le 31 décembre 2026.
- **Action prévue :** migrer vers PHP 8.4 (changer `FROM` dans `docker/php/Dockerfile` et `config.platform.php` dans `backend/composer.json`).

## D-005 : Front découplé en Vue 3

- **Date :** 2026-10-09
- **Statut :** Proposée, à valider au pitch 1
- **Décision :** Vue 3 + Vite + TypeScript + Vue Router + Pinia, consommant l'API.
- **Alternatives :** Twig + Stimulus + Turbo ; rendu serveur sans JS.
- **Raison :** usage mobile, interactions riches, API utilisée en permanence.
- **Conséquences :** CORS ; référencement et aperçus de liens à traiter pour les pages publiques ; authentification sans état à concevoir.
- **Question ouverte à poser à la cliente :** le partage d'une tenue avec une personne sans compte fait-il partie de la V1 ?

## D-006 : MariaDB 12.3 LTS

- **Date :** 2026-10-09
- **Statut :** Acceptée
- **Décision :** MariaDB 12.3, dernière LTS (support jusqu'en juin 2029), `utf8mb4`.
- **Alternatives :** version rolling ; PostgreSQL ; MySQL.
- **Raison :** contrainte de départ, modèle relationnel, disponibilité chez les hébergeurs, support long.

## D-007 : Environnement Docker Compose

- **Date :** 2026-10-09
- **Statut :** Acceptée
- **Décision :** Nginx + PHP-FPM 8.2 + MariaDB 12.3 + Node 24, identifiants dans un `.env` racine non versionné.
- **Alternatives :** Apache + mod_php ; FrankenPHP ; installation locale (WAMP).
- **Raison :** environnement identique pour tous, installation en trois commandes.
- **Dette :** image de production à écrire avant la mise en ligne.

## D-008 : Fichiers lock générés au premier lancement

- **Date :** 2026-10-09
- **Statut :** Réalisée le 2026-10-09 (branche `feature/lock-dependencies`)
- **Contexte :** l'environnement qui a initialisé le dépôt n'avait pas accès à Packagist ni à npm.
- **Décision :** générer `composer.lock`, `symfony.lock` et `package-lock.json` au premier `docker compose up`, puis les commiter dans `feature/lock-dependencies`.
- **Vérification à faire au passage :** relire les fichiers éventuellement ajoutés par Symfony Flex (`git status` dans `backend/`) avant de les commiter.

## D-009 : Postman versionné dans le dépôt

- **Date :** 2026-10-09
- **Statut :** Acceptée
- **Décision :** collection et environnement exportés dans `postman/`, mis à jour dans la même MR que le code.
- **Raison :** historique lié au code ; exécutable en CI avec Newman.

## D-010 : Fichiers ajoutés par Symfony Flex au premier lancement

- **Date :** 2026-10-09
- **Statut :** Acceptée
- **Constat :** au premier `composer install`, Flex a appliqué 14 recettes et ajouté `backend/.editorconfig`, `backend/config/reference.php` et un bloc `symfony/routing` dans `backend/.env`.
- **Décision :** conserver les trois. `.editorconfig` uniformise l'indentation et les fins de ligne dans les éditeurs ; `reference.php` est généré par Symfony 7.4 pour l'autocomplétion de la configuration dans l'IDE.
- **Correction :** `DEFAULT_URI` était déclarée deux fois dans `backend/.env` (une fois par l'initialisation, une fois par Flex avec `http://localhost`, port faux). Une seule déclaration est gardée, dans le bloc de Flex, avec `http://localhost:8080`.
- **Leçon :** un squelette écrit à la main diverge de ce que produisent les outils officiels ; toujours relire `git status` après le premier lancement.
