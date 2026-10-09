# Annexe : justification des choix techniques

Projet **Penderie**, initialisation du dépôt (octobre 2026).

Ce document explique **pourquoi** le projet est construit ainsi. Chaque choix suit la même structure : ce qui a été retenu, les alternatives écartées, la raison, et le prix à payer. Un choix sans inconvénient identifié est un choix qui n'a pas été examiné.

Les dates de support citées ont été vérifiées le 9 octobre 2026 sur les sites officiels (liens en fin de document).

---

## Sommaire

1. [Symfony 7.4 LTS plutôt que 8.x](#1-symfony-74-lts-plutôt-que-8x)
2. [PHP 8.2 : une contrainte imposée, avec une échéance proche](#2-php-82--une-contrainte-imposée-avec-une-échéance-proche)
3. [API Platform pour construire l'API](#3-api-platform-pour-construire-lapi)
4. [Front découplé en Vue.js](#4-front-découplé-en-vuejs)
5. [MariaDB 12.3 LTS](#5-mariadb-123-lts)
6. [Docker](#6-docker)
7. [Postman](#7-postman)
8. [Git et Gitflow](#8-git-et-gitflow)
9. [Ce qui n'est volontairement pas installé](#9-ce-qui-nest-volontairement-pas-installé)
10. [Écart assumé avec le cadre du module](#10-écart-assumé-avec-le-cadre-du-module)
11. [Questions probables à la soutenance](#11-questions-probables-à-la-soutenance)

---

## 1. Symfony 7.4 LTS plutôt que 8.x

**Retenu :** Symfony **7.4 LTS**.
**Écarté :** la branche 8.x courante (8.1, puis 8.2 prévue en novembre 2026).

| Critère | Symfony 7.4 LTS | Symfony 8.1 (8.x courante) |
|---|---|---|
| Fin des corrections de bugs | novembre 2028 | janvier 2027 |
| Fin des correctifs de sécurité | novembre 2029 | janvier 2027 |
| PHP minimum | 8.2 | 8.4 |
| Bundles tiers | compatibles depuis un an | certains encore en cours de migration |

**Durée de vie du projet.** Le module se termine en janvier 2027. Si je pars sur 8.1, sa maintenance s'arrête **pendant le module** : il faudrait migrer vers 8.2 en cours de route, puis vers 8.3, et ainsi de suite tous les six mois. Avec 7.4, la version installée aujourd'hui reçoit des correctifs de sécurité jusqu'en 2029, bien après la soutenance.

**Qui maintiendra l'application.** La cliente n'est pas technique et n'aura pas d'équipe de développement à demeure. Une application qui doit être mise à jour tous les six mois pour rester supportée est une application qui finira non maintenue. Une LTS limite les montées de version majeures à une tous les deux ans.

**Disponibilité des bundles.** Les bundles dont le projet a besoin (API Platform, Doctrine, NelmioCors, et plus tard l'authentification, Faker, les fixtures) supportent tous 7.4. Sur une version majeure récente, le risque est de tomber sur un bundle pas encore compatible et de devoir attendre ou le remplacer.

**Version de PHP requise.** 7.4 fonctionne avec PHP 8.2, imposé pour ce projet. Symfony 8.x exige PHP 8.4 : il était donc **incompatible** avec la contrainte PHP 8.2. Ce point seul suffisait à trancher.

**Le prix à payer.** Je n'aurai pas les nouveautés de la branche 8.x. Le passage de 7.4 à 8.x sera à faire un jour ; Symfony le facilite en signalant dans 7.4 toutes les dépréciations qui casseront en 8.0. Garder les journaux sans avertissement de dépréciation rend cette migration mécanique.

---

## 2. PHP 8.2 : une contrainte imposée, avec une échéance proche

**Retenu :** PHP **8.2** (image Docker `php:8.2-fpm-alpine`).

PHP 8.2 fait partie des contraintes de départ du projet. Je l'applique, mais je dois signaler un risque concret :

| Version | Fin du support actif | Fin des correctifs de sécurité |
|---|---|---|
| **PHP 8.2** | 31 déc. 2024 | **31 déc. 2026** |
| PHP 8.3 | 31 déc. 2025 | 31 déc. 2027 |
| PHP 8.4 | 31 déc. 2026 | 31 déc. 2028 |

**PHP 8.2 ne recevra plus aucun correctif de sécurité à partir du 1er janvier 2027**, c'est-à-dire avant la fin du module. Une application qui part en production sur une version de PHP non supportée hérite de toutes les failles découvertes ensuite.

**Ce que j'ai mis en place pour que ce soit réversible :**

- la version de PHP est déclarée à **un seul endroit** pour l'exécution (`docker/php/Dockerfile`, ligne `FROM`) ;
- `composer.json` fixe `config.platform.php` à `8.2.0` : Composer ne choisit que des paquets compatibles 8.2. Le jour du passage à 8.4, il suffit de changer ces deux lignes puis de lancer `composer update` ;
- Symfony 7.4 supporte PHP 8.2 **et** 8.4 : la montée de version de PHP ne force pas de montée de version de Symfony.

**Recommandation :** passer à PHP 8.4 avant la mise en production, et idéalement avant le pitch 2. Je l'inscris comme décision à revoir dans le journal (D-004).

---

## 3. API Platform pour construire l'API

**Retenu :** **API Platform 4** au-dessus de Symfony, exposé sous `/api`.
**Écarté :** des contrôleurs Symfony écrits à la main qui renvoient du JSON.

**Pourquoi.**

- Le module exige un **contrat d'API en OpenAPI**. API Platform le **génère** à partir du code (`/api/docs.jsonopenapi`) : le contrat et l'implémentation ne peuvent pas diverger sans que ce soit visible.
- Pagination, filtres, validation et format d'erreur standard sont fournis. Une liste de dressing de plusieurs centaines de pièces doit être paginée : c'est un réglage (`pagination_items_per_page: 30`), pas du code à écrire.
- La sécurité se déclare par ressource et par opération (`security: "object.owner == user"`), ce qui répond directement au risque du module « accès à la ressource d'un autre utilisateur en changeant l'identifiant dans l'URL ».
- Le contrat OpenAPI s'importe dans Postman.

**Formats.** J'ai activé `jsonld` (format par défaut d'API Platform, qui porte les liens de pagination) et `json` (plus simple à consommer depuis Vue quand on n'a pas besoin des métadonnées).

**Le prix à payer.** API Platform a une courbe d'apprentissage : il faut comprendre les groupes de sérialisation, sans quoi on **expose des champs internes** dans les relations, l'un des quatre défauts cités par le module. Je devrai définir des groupes `read` / `write` explicites sur chaque ressource plutôt que de laisser tout passer.

**Point d'attention.** Le module demande d'écrire le contrat OpenAPI **avant** l'implémentation. API Platform génère le contrat à partir du code : je rédigerai donc le contrat cible pendant le cadrage, puis je vérifierai que le contrat généré lui correspond.

---

## 4. Front découplé en Vue.js

**Retenu :** option 2 du module, une **application découplée** en **Vue 3** (Vite, TypeScript, Vue Router, Pinia) qui consomme l'API.
**Écartés :** Twig + Stimulus + Turbo (option 1) ; rendu serveur sans framework JS (option 3).

### Conséquences, option par option

| Conséquence | Vue découplé (retenu) | Twig + Stimulus/Turbo | Rendu serveur sans JS |
|---|---|---|---|
| **API** | Indispensable, et complète : tout passe par elle | Facultative | Facultative |
| **Responsive** | Entièrement en CSS, comme les autres options ; navigation sans rechargement, proche d'une app mobile | Bon, Turbo évite les rechargements complets | Bon, mais chaque action recharge la page |
| **Référencement** | Faible : le HTML initial est vide, le contenu arrive en JavaScript | Bon | Bon |
| **Partage public sans compte** | À traiter à part (voir ci-dessous) | Natif | Natif |
| **Authentification** | Sans état (jeton) ou cookie avec CORS : à concevoir | Session Symfony classique | Session Symfony classique |

### Pourquoi Vue malgré tout

- **La cliente vit sur son téléphone.** Une application à navigation instantanée (filtrer son dressing, composer une tenue en glissant des pièces) se rapproche d'une application mobile. Ces interactions riches sont plus naturelles avec un framework à composants qu'avec Stimulus.
- **L'API est obligatoire de toute façon.** Avec un front découplé, l'API n'est pas un ajout : c'est le seul chemin. Elle est donc testée en permanence par l'usage réel, et la collection Postman couvre tout ce que fait l'application.
- **Évolution vers une application mobile.** Si la cliente veut plus tard une application installable (PWA), le front Vue s'y prête directement, sans toucher à l'API.
- **Vue plutôt que React ou Angular** : syntaxe des composants proche du HTML, documentation officielle en français, outillage officiel cohérent (Vite, Vue Router, Pinia maintenus par l'équipe Vue). Angular est plus lourd que le besoin ; React demande de choisir soi-même chaque brique.

### Le prix à payer, et ce que je ferai

- **Référencement et partage public.** Une application Vue envoie une page vide aux robots et aux aperçus de lien (WhatsApp, Instagram, Messenger). Si la cliente veut partager une tenue par lien à quelqu'un qui n'a pas de compte, l'aperçu sera vide. **Solution prévue :** pour les quelques pages publiques partageables, une route Symfony qui renvoie un HTML minimal avec les balises Open Graph (titre, image), puis charge l'application. À confirmer avec la cliente : le partage public fait-il partie de la V1 ?
- **CORS.** Le front (port 5173) et l'API (port 8080) n'ont pas la même origine. NelmioCorsBundle est configuré pour n'autoriser que `localhost` en développement ; en production, la variable `CORS_ALLOW_ORIGIN` ne listera que le domaine du front.
- **Deux projets à maintenir** (deux gestionnaires de dépendances, deux jeux de tests). C'est le coût principal de l'option 2.

### Détails du front

- **Vite** : outil de build officiel de Vue, rechargement à chaud instantané.
- **TypeScript** : le contrat OpenAPI permet de générer les types des réponses de l'API. Une faute de nom de champ est alors détectée à la compilation, pas par la cliente. Coût : une syntaxe supplémentaire à maîtriser.
- **Vue Router** : une URL par écran, donc des écrans partageables et le bouton « retour » du téléphone qui fonctionne.
- **Pinia** : magasin d'état officiel de Vue (utilisateur connecté, filtres du dressing).
- **`VITE_API_URL`** : l'adresse de l'API est une variable d'environnement, jamais écrite en dur. Rien de secret ne doit y figurer, puisque toute variable `VITE_*` se retrouve dans le JavaScript téléchargé par le navigateur.

---

## 5. MariaDB 12.3 LTS

**Retenu :** **MariaDB 12.3**, la dernière version **LTS** (maintenue jusqu'en juin 2029).
**Écartés :** une version « rolling » de MariaDB, MySQL, PostgreSQL, SQLite.

**Pourquoi une LTS et pas la toute dernière version.** MariaDB publie des versions intermédiaires (« rolling ») maintenues seulement jusqu'à la suivante. La 12.3 est la dernière version à support long : le même raisonnement que pour Symfony, la base ne doit pas devenir non maintenue pendant le projet.

**Pourquoi MariaDB.**

- Exigence de départ du projet : une base SQL MariaDB.
- Données fortement relationnelles (une utilisatrice possède des vêtements, une tenue regroupe plusieurs vêtements, un vêtement appartient à plusieurs tenues) : une base relationnelle avec clés étrangères garantit les cardinalités et les invariants du modèle.
- Supportée nativement par Doctrine et proposée par la quasi-totalité des hébergeurs mutualisés, ce qui compte pour une cliente qui n'aura pas d'administrateur système.
- Licence libre (GPL), sans dépendance à un éditeur unique, contrairement à MySQL (Oracle).

**PostgreSQL** aurait été un excellent choix technique (types plus riches, recherche plein texte), mais n'apporte rien de décisif ici et ne respecte pas la contrainte. **SQLite** ne convient pas à une application multi-utilisateurs en production.

**Réglages.**

- Encodage `utf8mb4` dans `DATABASE_URL` : accents, et émojis que la cliente mettra forcément dans les noms de ses tenues.
- `serverVersion=12.3.2-MariaDB` : Doctrine sait quelle syntaxe SQL générer sans interroger le serveur.
- Port **3307** exposé sur la machine (et non 3306) pour ne pas entrer en conflit avec un MySQL ou un MariaDB déjà installé (WAMP, XAMPP).

---

## 6. Docker

**Retenu :** **Docker Compose** avec quatre services.

| Service | Image | Rôle |
|---|---|---|
| `database` | `mariadb:12.3` | Base de données |
| `php` | `php:8.2-fpm-alpine` (Dockerfile maison) | Exécute Symfony |
| `nginx` | `nginx:1.28-alpine` | Serveur web, transmet les requêtes à PHP-FPM |
| `frontend` | `node:24-alpine` | Serveur de développement Vite |

**Pourquoi Docker.** Exigence du projet, et surtout : **même environnement pour tout le monde**. Le README tient en trois commandes, sans installer PHP, Composer, Node ni MariaDB sur la machine. Un tiers (l'enseignant, un correcteur) lance le projet sans poser de question, ce que demande le module.

**Choix détaillés.**

- **Nginx + PHP-FPM** plutôt qu'Apache (`php:8.2-apache`) : c'est l'architecture la plus répandue en production pour Symfony, et chaque conteneur n'a qu'un rôle. FrankenPHP a été écarté : plus récent, moins documenté, et une brique de plus à défendre sans bénéfice pour ce projet.
- **Images Alpine** : plus légères (téléchargement et démarrage plus rapides sur les machines de l'IUT).
- **`install-php-extensions`** (mlocati) : installe `pdo_mysql`, `intl`, `zip`, `opcache` et `apcu` en gérant leurs dépendances système. Sans lui, le Dockerfile serait une longue liste de paquets Alpine à maintenir.
- **Composer copié depuis l'image officielle** `composer:2` : pas d'installation par script téléchargé.
- **Point d'entrée** (`docker-entrypoint.sh`) : lance `composer install` si `vendor/` est absent. Le projet démarre avec un simple `docker compose up`.
- **Healthcheck sur MariaDB** et `depends_on: condition: service_healthy` : PHP ne démarre que quand la base accepte les connexions, ce qui évite les erreurs au premier lancement.
- **`node_modules` dans un volume Docker** : les modules compilés pour Linux ne se mélangent pas avec Windows, et `npm` est beaucoup plus rapide qu'à travers un dossier partagé Windows.
- **`usePolling` dans Vite** : sous Windows, les modifications de fichiers ne sont pas toujours signalées au conteneur ; le polling garantit le rechargement à chaud.
- **Limite d'envoi à 10 Mo** (PHP et Nginx) : une photo de vêtement prise au téléphone dépasse souvent les 2 Mo par défaut.
- **Node 24** : version LTS active (maintenue jusqu'en avril 2028), compatible avec Vite 7.

**Secrets.** Les identifiants de la base sont dans un fichier `.env` **à la racine, non versionné**. Le dépôt ne contient que `.env.example`, avec des valeurs d'exemple. Si `.env` manque, `docker compose` s'arrête avec un message explicite (`${VAR:?…}`) plutôt que de démarrer avec un mot de passe vide.

**Le prix à payer.** Docker Desktop sous Windows est gourmand en mémoire, et les dossiers partagés sont plus lents que sous Linux. Cette configuration est faite pour le **développement** : une image de production (code copié dans l'image, sans Composer ni outils de développement) sera à écrire avant la mise en ligne.

---

## 7. Postman

**Retenu :** une **collection Postman versionnée** dans `postman/`, avec un fichier d'**environnement** séparé.

- **Versionnée dans le dépôt** plutôt que seulement dans un espace de travail Postman en ligne : elle évolue avec le code, dans la même merge request, et l'historique Git montre quand chaque appel a changé.
- **Environnement séparé** (`baseUrl`, `frontUrl`) : la même collection servira en local et sur un serveur de recette en changeant uniquement d'environnement.
- **Tests dans chaque requête** (statut, format, contenu) : la collection se rejoue d'un clic avec le *Collection Runner*, et pourra tourner en intégration continue avec **Newman** (l'exécutable en ligne de commande de Postman) à partir de la séance 17.
- Requêtes de départ : point d'entrée de l'API, contrat OpenAPI, et **pré-requête CORS** qui simule l'appel du front Vue. Ce dernier test vérifie la configuration CORS sans ouvrir de navigateur.

**À venir :** pour chaque ressource, trois cas au minimum, comme l'exige le module pour les tests : cas nominal, cas d'erreur (données invalides) et **cas d'autorisation** (accéder à la ressource d'un autre utilisateur doit renvoyer 403 ou 404).

---

## 8. Git et Gitflow

### Branches

- `main` : version livrée. Ne reçoit **que** des merges depuis `release/*` ou `hotfix/*`. **Aucun commit direct.**
- `develop` : intégration. Ne reçoit que des merges depuis `feature/*`.
- `feature/…`, `release/…`, `hotfix/…` : conformément à Gitflow.

### Initialisation du dépôt

Un dépôt Git a besoin d'un premier commit pour que des branches existent. Ce commit racine est **vide** (`git commit --allow-empty`, message `chore: initialize repository`) : il ne contient aucun fichier. `main` et `develop` pointent toutes les deux sur ce commit vide. Ensuite, **tout le contenu arrive par des branches `feature/…` fusionnées dans `develop` par merge request** :

| Branche | Contenu |
|---|---|
| `feature/documentation` | README, `.gitattributes`, modèle de merge request, annexe, journal de décisions |
| `feature/docker` | `compose.yaml`, images PHP et Nginx, `.gitignore` et `.env.example` racine |
| `feature/backend-symfony` | Squelette Symfony 7.4, Doctrine, API Platform, CORS, MakerBundle |
| `feature/frontend-vue` | Application Vue 3 (Vite, TypeScript, Router, Pinia) |
| `feature/postman` | Collection et environnement Postman |

`main` ne bougera qu'à la première version (`release/0.1.0`).

### Commits

- **Commits conventionnels** à l'impératif présent (`chore: add …`, `docs: add …`).
- **Un commit par intention** : le squelette Symfony est découpé en cinq commits (squelette, Doctrine, API Platform, CORS, MakerBundle) plutôt qu'un bloc. Chaque commit se comprend seul et peut être annulé seul.
- Les commits produits avec l'assistant de code portent la mention `Co-Authored-By`. C'est volontaire : le module autorise le code généré à condition de le comprendre, et l'historique dit honnêtement comment il a été produit.

### Fichiers de configuration Git

- **`.gitignore`** : `vendor/`, `node_modules/`, `var/`, tous les `.env` locaux. Le module l'exige, et un secret poussé une fois est compromis même s'il est supprimé ensuite.
- **`.gitattributes`** avec `eol=lf` : sous Windows, Git convertit par défaut les fins de ligne en CRLF. Un script shell (`docker-entrypoint.sh`) ou `bin/console` en CRLF **ne s'exécute plus** dans un conteneur Linux (`/bin/sh^M: not found`). Ce fichier empêche cette panne classique.
- **Modèle de merge request** (`.github/pull_request_template.md`) avec une liste d'auto-relecture, puisque les MR sont obligatoires même seul.

### Fichiers lock

`composer.lock`, `symfony.lock` et `package-lock.json` **doivent être versionnés** : ils garantissent que tout le monde installe exactement les mêmes versions. Ils ne sont pas encore dans le dépôt, car l'environnement qui a servi à initialiser le projet n'avait pas accès aux registres de paquets. Ils sont générés au premier `docker compose up` et doivent être commités dans une branche `feature/lock-dependencies` (voir le journal, D-008).

---

## 9. Ce qui n'est volontairement pas installé

Ne pas installer quelque chose est aussi une décision. Ces briques attendent une décision de cadrage, pas un choix par défaut :

| Brique | Pourquoi pas maintenant | Quand |
|---|---|---|
| **Authentification** (SecurityBundle, JWT ou session) | Le choix dépend du modèle d'autorisation (qui voit quoi, partage public sans compte), pas encore écrit | Cadrage, avant le pitch 1 |
| **Stockage des images** | Disque local, S3 ou autre : dépend du volume de photos et de l'hébergement, inconnus | Cadrage |
| **Envoi d'e-mails** (Mailer, Mailpit) | Aucun besoin exprimé par la cliente pour l'instant | Si un parcours l'exige |
| **Fixtures + Faker** | Écrire un générateur avant le modèle de données reviendrait à inventer le modèle | Après validation du modèle (attendu au pitch 2) |
| **PHPStan, tests, CI GitHub Actions, Snyk** | Exigés à partir de la séance 17 | Séance 17 |
| **Composants d'interface** (Tailwind, Vuetify…) | Dépend des maquettes, pas encore faites | Après les maquettes |

---

## 10. Écart assumé avec le cadre du module

Le document du module indique : *« Aucun code applicatif n'est autorisé avant la validation du pitch 1. Le dépôt ne contient, jusque-là, que de la documentation. »*

Ce dépôt contient des **squelettes** (Symfony, Vue) et la configuration Docker, mais **aucune entité, aucun écran, aucune règle métier** : rien qui présuppose le modèle de données. Malgré tout, un squelette est du code au sens du dépôt. Initialiser l'infrastructure dès maintenant est **mon choix**, consigné dans le journal de décisions (D-001), avec les risques suivants :

- l'enseignant peut considérer ces commits comme du code avant le pitch 1 ;
- l'infrastructure prépare un front découplé alors que le cadrage n'est pas validé ; si le pitch 1 conduit à choisir Twig, la branche `frontend/` sera supprimée.

**Argument en faveur :** vérifier dès maintenant que la stack démarre sur les machines de l'IUT élimine un risque technique sans figer la moindre décision métier. Le modèle de données, le modèle d'autorisation et le contrat d'API restent entièrement à écrire.

---

## 11. Questions probables à la soutenance

Questions auxquelles je dois savoir répondre sans ce document :

1. Pourquoi pas Symfony 8 ? *Fin de support en janvier 2027 pour 8.1, PHP 8.4 obligatoire, incompatible avec PHP 8.2.*
2. Que se passe-t-il pour PHP 8.2 le 1er janvier 2027 ? *Plus de correctifs de sécurité ; migration vers 8.4 prévue, deux lignes à changer.*
3. Comment une page Vue partagée par lien s'affiche-t-elle dans WhatsApp ? *Mal par défaut ; route Symfony avec balises Open Graph pour les pages publiques.*
4. Pourquoi CORS, et que met-on en production ? *Origines différentes front/API ; uniquement le domaine du front.*
5. Comment empêchez-vous un utilisateur de lire le dressing d'un autre en changeant l'ID dans l'URL ? *Règle `security` par opération API Platform + test Postman et test fonctionnel du cas.*
6. Pourquoi le commit racine est-il vide ? *Il faut un commit pour créer des branches ; vide, il n'introduit aucun contenu hors merge request.*
7. Pourquoi `.gitattributes` ? *Fins de ligne CRLF sous Windows qui cassent les scripts dans Docker.*
8. Où sont les mots de passe de la base ? *Dans `.env` racine, non versionné ; `.env.example` documente les variables.*
9. Pourquoi `node_modules` dans un volume ? *Binaires Linux / Windows et performance.*
10. Pourquoi une LTS de MariaDB plutôt que la dernière version ? *Même logique de durée de vie que Symfony.*

---

## Sources

- [Symfony 7.4 : dates de support et version de PHP requise](https://symfony.com/releases/7.4)
- [Symfony : calendrier de toutes les versions](https://symfony.com/releases)
- [PHP : versions supportées](https://www.php.net/supported-versions.php)
- [MariaDB 12.3 LTS, support jusqu'en juin 2029](https://www.linuxtoday.com/blog/mariadb-12-3-lts-debuts-with-support-until-june-2029/)
- [API Platform : documentation](https://api-platform.com/docs/)
- [Vue.js : documentation officielle](https://fr.vuejs.org/)
