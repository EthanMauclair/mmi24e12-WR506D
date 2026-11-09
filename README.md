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
| `camille@exemple.fr` | Aucun logement : membre du réseau social             |

Léa possède un article personnel (« Montre de ma grand-mère ») que les autres membres ne voient pas.

Côté réseau social, le profil de Léa (`lea.chine`) est privé :
- Tom et Camille la suivent et font partie de ses amis : ils peuvent commenter ses articles ;
- Inès et Jules la suivent sans être ses amis ;
- Sam a envoyé une demande d'abonnement qui attend la réponse de Léa ;
- Léa a partagé une tenue (« Tenue Travail »).

Vous pouvez aussi créer votre propre compte depuis l'écran d'inscription.

## Fonctionnalités disponibles

- **Authentification** : inscription (un mineur indique l'e-mail du compte majeur qui le rattache), connexion, déconnexion. La session est un cookie géré par Symfony ; 5 tentatives de connexion échouées bloquent temporairement le compte.
- **Inventaire** :
  - créer un logement, choisir le logement affiché, voir ses chiffres clés ;
  - ajouter des pièces et des stockages (propriétaire du logement uniquement) ;
  - parcourir les articles d'un stockage, ouvrir la fiche d'un article ;
  - rechercher « Où est rangé… ? » par titre, catégorie, marque ou couleur ;
  - rendre un article personnel (visible uniquement par la personne qui l'a ajouté).
  - modifier un article avec le crayon de sa fiche (celui qui l'a ajouté, ou le propriétaire du logement).
- **Ajout d'article par scan** (bouton central de la barre de navigation) :
  - **vêtement** : dès la photo prise, la fiche de l'article s'ouvre avec tous ses champs (titre, catégorie, couleur, marque, note, emplacement…) à remplir à la main. En arrière-plan, la photo est réduite (1024 px), convertie en JPEG puis envoyée en base64 à un modèle de vision d'OpenRouter (`google/gemma-4-26b-a4b-it:free` par défaut, modifiable avec `OPENROUTER_MODELE`), qui propose la catégorie, la couleur, la marque si elle est lisible et un titre. L'IA ne remplit que les champs encore vides. S'il n'a pas répondu au bout de 3 s, un second modèle, hébergé ailleurs, est interrogé en parallèle : `nvidia/nemotron-3.5-super-vl:free` (`OPENROUTER_MODELE_SECOURS`, vide pour désactiver), et la première réponse valide est retenue. Une analyse prend en général 3 à 6 s ; si aucun modèle gratuit ne répond en 20 s, la fiche l'indique et se remplit à la main. 30 analyses par heure et par utilisateur ;
  - **livre ou vinyle** : le code-barres est lu dans le navigateur, puis la fiche est cherchée dans Open Library (et Google Books en secours) pour les livres, MusicBrainz pour les disques ;
  - **saisie manuelle**, aussi proposée quand un code-barres n'est dans aucune base ;
  - tous les champs restent modifiables avant l'enregistrement ; la photo du scan est conservée.

La caméra du navigateur n'est accessible que sur une page sécurisée : `http://localhost` fonctionne, mais depuis un téléphone sur le réseau local il faut une adresse en HTTPS. À défaut, le bouton « Importer depuis la galerie » permet de choisir ou de prendre une photo.

- **Réseau social** (onglet Profil de la barre de navigation) :
  - profil avec bio, nombre d'articles, d'abonnés et d'abonnements, et ses articles (sans les articles personnels ni vendus) ;
  - recherche de membres par pseudo, prénom ou nom ;
  - abonnement à un profil public, ou demande d'abonnement à un profil privé, que le membre accepte ou refuse ;
  - choix des **amis** parmi ses abonnés, comme les « amis proches » d'Instagram : eux seuls commentent ses articles ;
  - commentaires sous les articles ;
  - modification du profil : pseudo, prénom, nom, bio, profil privé ou public. Un profil qui devient public accepte les demandes en attente.

- **Générateur de tenue** (onglet Tenues) :
  - propose un haut et un bas (ou une robe, une combinaison), des chaussures et une veste, parmi ses propres vêtements disponibles du logement affiché (jamais un article personnel, prêté ou vendu) ;
  - **météo en direct** : le navigateur demande la position, l'API lit la température et la pluie sur [Open-Meteo](https://open-meteo.com/) et le nom de la ville sur [OpenStreetMap](https://nominatim.org/) (gratuits, sans clé). S'il pleut, la veste imperméable ou à capuche passe devant ; s'il fait chaud et sec, pas de veste. Sans localisation, la tenue suit la saison ;
  - **occasion** : avec une étiquette (« Travail »…), les vêtements qui ne portent que d'autres étiquettes (« Soirée »…) sont écartés ; le propriétaire et les co-propriétaires créent les étiquettes avec le bouton « + » ;
  - « Autre idée » propose d'autres vêtements quand il y en a ; « Partager » enregistre la tenue, publique ou privée, dans l'onglet Tenues du profil.

La position est arrondie à environ 1 km par l'API avant d'être envoyée à Open-Meteo et OpenStreetMap, et n'est pas enregistrée. Le générateur utilise la saison, le niveau de chaleur, la capuche et l'imperméabilité des vêtements : ces informations sont renseignées dans les données de démonstration.

Les autres écrans (fil des tenues, marché, prêts) arrivent dans les prochaines versions.

### Qui voit quoi dans le réseau social

| Action | Qui peut le faire |
|---|---|
| Voir un profil (nom, bio, compteurs) | Tout le monde, même sans compte |
| Voir les articles, les tenues, les abonnés et les abonnements d'un profil public | Tout le monde, même sans compte |
| Voir ceux d'un profil privé | Le membre et ses abonnés acceptés |
| Voir où un article est rangé | Les membres du logement uniquement, jamais un visiteur ni un abonné |
| Voir un article personnel | Uniquement le membre qui l'a ajouté |
| Commenter un article | Le membre qui l'a ajouté et ses amis ; jamais un article personnel |
| Supprimer un commentaire | Son auteur, ou le membre qui a ajouté l'article |
| Savoir qui sont les amis d'un membre | Le membre uniquement |
| Voir une tenue privée | Uniquement le membre qui l'a créée |
| Créer une étiquette | Le propriétaire et les co-propriétaires du logement |

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
| POST    | `/api/scans/photo`                      | Enregistre la photo d'un article (`photo`, multipart) et renvoie son chemin |
| POST    | `/api/scans/analyse`                    | Suggestions de l'IA pour une photo : le fichier (`photo`, multipart) ou le chemin d'une photo enregistrée (`{"photo": "…"}`) |
| GET     | `/api/scans/code-barres/{code}`         | Fiche d'un livre (ISBN) ou d'un vinyle (EAN)   |
| POST    | `/api/articles`                         | Ajoute un article avec son détail et sa photo  |
| GET     | `/api/articles/{id}`                    | Fiche d'un article                             |
| PUT     | `/api/articles/{id}`                    | Modifie toutes les informations d'un article   |
| PATCH   | `/api/articles/{id}`                    | Rend un article personnel ou non               |
| GET     | `/api/articles/{id}/commentaires`       | Commentaires d'un article                      |
| POST    | `/api/articles/{id}/commentaires`       | Commente un article (`contenu`), réservé aux amis |
| DELETE  | `/api/commentaires/{id}`                | Supprime un commentaire                        |
| GET     | `/api/membres?q=…`                      | Recherche de membres                           |
| GET     | `/api/profils/{pseudo}`                 | Profil d'un membre et relation avec lui        |
| GET     | `/api/profils/{pseudo}/articles`        | Articles d'un profil, sans leur emplacement    |
| GET     | `/api/profils/{pseudo}/abonnes`         | Abonnés d'un membre                            |
| GET     | `/api/profils/{pseudo}/abonnements`     | Membres qu'il suit                             |
| POST    | `/api/profils/{pseudo}/abonnement`      | S'abonne, ou envoie une demande (profil privé) |
| DELETE  | `/api/profils/{pseudo}/abonnement`      | Se désabonne, ou annule la demande             |
| PUT     | `/api/moi/profil`                       | Modifie son profil                             |
| GET     | `/api/moi/demandes`                     | Demandes d'abonnement reçues                   |
| POST    | `/api/moi/demandes/{id}/acceptation`    | Accepte une demande                            |
| DELETE  | `/api/moi/demandes/{id}`                | Refuse une demande                             |
| PUT     | `/api/moi/abonnes/{pseudo}`             | Choisit un abonné comme ami ou non (`estAmi`)  |
| DELETE  | `/api/moi/abonnes/{pseudo}`             | Retire un abonné                               |
| POST    | `/api/logements/{id}/etiquettes`        | Crée une étiquette (`nom`)                     |
| GET     | `/api/logements/{id}/tenue-proposee`    | Propose une tenue (`etiquette`, `lat`, `lon`, `eviter` facultatifs) |
| POST    | `/api/tenues`                           | Enregistre une tenue (`titre`, `description`, `estPrivee`, `articleIds`) |
| GET     | `/api/profils/{pseudo}/tenues`          | Tenues d'un profil                             |
| DELETE  | `/api/tenues/{id}`                      | Supprime une de ses tenues                     |

Les routes de consultation du réseau social (`GET` sur les profils et leurs tenues, les membres, un article et ses commentaires) sont ouvertes aux visiteurs sans compte ; l'API ne renvoie que ce qu'ils ont le droit de voir.

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
