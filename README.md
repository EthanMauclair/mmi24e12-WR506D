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
| `admin@exemple.fr` | Super admin (propriétaire du site) : accès au back office |

Léa possède un article personnel (« Montre de ma grand-mère ») que les autres membres ne voient pas.

Léa a prêté sa veste en jean à Camille (membre de l'application) et sa chemise rayée à Marc (simple nom), dont le retour est en retard ; Inès lui a déjà rendu son jean.

Sur le marché : Léa vend le vinyle « Random Access Memories » (Sam lui a écrit, sa dernière réponse n'est pas encore lue), Tom son sweat zippé, et Inès ses baskets (annonce réservée).

Côté réseau social, le profil de Léa (`lea.chine`) est privé :
- Tom et Camille la suivent et font partie de ses amis : ils peuvent commenter ses articles ;
- Inès et Jules la suivent sans être ses amis ;
- Sam a envoyé une demande d'abonnement qui attend la réponse de Léa ;
- Léa a partagé une tenue (« Tenue Travail »), aimée par Tom, Camille et Inès, et commentée par Camille.

Vous pouvez aussi créer votre propre compte depuis l'écran d'inscription.

Pour donner un rôle d'administration à un compte existant (par exemple sur une base créée avant ce compte de démonstration) :

```bash
docker compose exec php bin/console app:admin:nommer admin@exemple.fr                 # super admin
docker compose exec php bin/console app:admin:nommer tom@exemple.fr --role=admin      # administrateur
docker compose exec php bin/console app:admin:nommer tom@exemple.fr --role=membre     # simple membre
```

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

- **Fil des tenues** (onglet Tenues › Fil des tenues) :
  - les tenues partagées par les membres suivis et les siennes, les plus récentes d'abord, par pages de 10 ;
  - likes, aperçu du dernier commentaire, et page de chaque tenue avec tous ses commentaires (seuls son créateur et ses amis commentent) ;
  - **partage vers Instagram** : Instagram ne permet pas de publier depuis un site. Le bouton génère une image de la tenue (1080 × 1350, le format portrait d'Instagram), que le téléphone partage directement vers l'application et que l'ordinateur télécharge, et propose de copier le lien de la tenue (`/tenues/{id}`, ouvert sans compte pour une tenue publique d'un profil public).

- **Suivi des prêts** (bouton « Prêter » de la fiche article, onglet « Prêts » de son profil) :
  - prêter un article à un membre de l'application (recherche par pseudo) ou à un simple nom, avec la date de prêt, la date de retour prévue et une note ; celui qui a ajouté l'article ou le propriétaire du logement peut le prêter ;
  - l'article passe « Prêté » (il n'est plus proposé par le générateur de tenue ni mis en vente) ; sa fiche indique à qui et jusqu'à quand, pour les membres du logement uniquement ;
  - écran « Prêts » : prêts en cours (le retour le plus proche d'abord, « En retard » une fois la date dépassée) et rendus ; « Marquer rendu » enregistre la date de retour et rend l'article disponible ;
  - un prêt saisi par erreur peut être supprimé.

- **Place de marché** (onglet Marché) :
  - mettre en vente un de ses articles depuis sa fiche (prix, description) : il passe « En vente » ; un article personnel ou prêté ne peut pas être vendu ;
  - le marché montre les annonces en ligne et réservées de tous les membres, avec une recherche et des filtres (vêtements, vinyles, livres et BD, mes annonces) ; jamais l'emplacement des articles ;
  - l'acheteur écrit au vendeur depuis l'annonce : une conversation privée par acheteur et par annonce, mise à jour toutes les 5 secondes, avec un badge des messages non lus sur le marché ;
  - le vendeur change le prix, réserve l'annonce ou la remet en ligne, la marque vendue (à un membre qui lui a écrit, ou à quelqu'un hors de l'application) ou la retire. Vendu, l'article quitte le dressing ; retiré, il redevient disponible ;
  - aucun paiement : acheteur et vendeur s'arrangent entre eux.
- **Trois langues : français (langue principale), anglais et portugais du Portugal** :
  - au premier passage, l'application suit la langue du navigateur (français par défaut) ; un sélecteur FR / EN / PT sur la connexion, l'inscription et les paramètres du profil permet de la changer, et le choix est mémorisé ;
  - l'interface est traduite avec [vue-i18n](https://vue-i18n.intlify.dev/) (`frontend/src/i18n/{fr,en,pt}.json`), dates et prix compris (« 12 nov. », « 12 Nov », « 12/11 » ; « 18 € », « €18 ») ;
  - l'API répond dans la langue envoyée par le front dans l'en-tête `Accept-Language` : messages d'erreur, catégories, couleurs, états, formats et météo (`backend/translations/`, le texte français servant de clé) ;
  - au scan, l'IA propose le titre de l'article dans la langue de l'utilisateur ;
  - les textes saisis par les membres (titres, notes, noms de pièces, étiquettes, commentaires) restent tels qu'ils ont été écrits. La recherche porte sur les noms français des catégories et des couleurs.

- **Mon espace** (bouton « Mon espace » de son profil) : les chiffres de son inventaire et de son activité, pour chaque membre :
  - articles (dont personnels), ventes (montant, articles vendus et en vente), prêts en cours et en retard, abonnés ;
  - articles ajoutés chaque mois sur un an ; répartition par famille, statut, logement et mode d'ajout (photo analysée par l'IA, code-barres, saisie manuelle) ;
  - couleurs et marques les plus présentes ; tenues créées, j'aime et commentaires reçus.
- **Back office** (`/admin`, bouton « Back office » du profil, visible seulement des administrateurs), sur grand écran comme sur téléphone :
  - **tableau de bord** (super admin) : membres inscrits, nouveaux et actifs sur 30 jours, comptes bloqués ; inscriptions et articles ajoutés par jour sur 30 jours ; articles par famille, statut et mode d'ajout, part des articles ajoutés par un scan ; catégories et marques les plus utilisées ; volume et prix moyen des ventes, annonces en ligne, conversations ; prêts en cours et en retard ; tenues, j'aime, commentaires et abonnements ;
  - **membres** (super admin) : recherche, bloquer ou débloquer un compte (il ne peut plus se connecter et sa session en cours est fermée), changer son rôle, supprimer son compte après avoir vu tout ce que la suppression efface (ses articles, ses logements avec les articles que d'autres y ont rangés, ses tenues, ses commentaires). Personne ne modifie son propre compte depuis le back office ;
  - **référentiels** : ajouter, renommer ou supprimer une catégorie (sous une catégorie existante, dont elle prend la famille) et changer sa zone de tenue, utilisée par le générateur ; ajouter, modifier ou supprimer une couleur ; renommer ou supprimer une marque. Une catégorie ou une couleur utilisée par des articles ne peut pas être supprimée ; une marque supprimée est retirée des articles qui la portaient. Les noms se saisissent en français : leurs traductions anglaise et portugaise s'ajoutent dans `backend/translations/referentiels.{en,pt}.yaml` ;
  - **modération** : supprimer un commentaire ou une tenue publique, retirer une annonce du marché (l'article redevient disponible).

| Rôle | Accès |
|---|---|
| Membre | L'application et « Mon espace » |
| Administrateur (`ROLE_ADMIN`) | En plus : référentiels et modération |
| Super admin (`ROLE_SUPER_ADMIN`, propriétaire du site) | En plus : tableau de bord et gestion des membres et de leurs rôles |

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
| Aimer une tenue | Tout membre connecté qui la voit |
| Commenter une tenue | Le membre qui l'a créée et ses amis |
| Créer une étiquette | Le propriétaire et les co-propriétaires du logement |
| Mettre un article en vente, gérer son annonce | Le membre qui a ajouté l'article |
| Voir les annonces du marché (et l'article en vente) | Tout membre connecté, même si le profil du vendeur est privé |
| Lire et écrire dans une conversation | L'acheteur et le vendeur uniquement |

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
| POST    | `/api/articles/{id}/prets`              | Prête un article (`emprunteur` (pseudo) ou `emprunteurNom`, `datePret`, `dateRetourPrevue`, `note`) |
| GET     | `/api/prets`                            | Ses prêts, en cours et rendus                  |
| POST    | `/api/prets/{id}/retour`                | Marque un prêt comme rendu aujourd'hui         |
| DELETE  | `/api/prets/{id}`                       | Supprime un prêt saisi par erreur              |
| GET     | `/api/annonces`                         | Annonces du marché (`q`, `famille` : vetements, vinyles, livres ; `mes=1`) et messages non lus |
| POST    | `/api/articles/{id}/annonce`            | Met un article en vente (`prix`, `description`) |
| GET     | `/api/annonces/{id}`                    | Une annonce et ses conversations (le vendeur les voit toutes, l'acheteur la sienne) |
| PATCH   | `/api/annonces/{id}`                    | Prix, description ou statut (`RESERVEE`, `EN_LIGNE`, `VENDUE` avec `acheteur`, `RETIREE`) |
| POST    | `/api/annonces/{id}/conversations`      | Écrit au vendeur (`contenu`) : crée la conversation au premier message |
| GET     | `/api/conversations`                    | Ses conversations et le nombre de messages non lus |
| GET     | `/api/conversations/{id}`               | Une conversation ; marque les messages reçus comme lus |
| POST    | `/api/conversations/{id}/messages`      | Envoie un message (`contenu`)                  |
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
| GET     | `/api/tenues/fil`                       | Fil des tenues (`avant` : page suivante)       |
| GET     | `/api/tenues/{id}`                      | Une tenue, avec ses likes et son dernier commentaire |
| PUT     | `/api/tenues/{id}/like`                 | Aime une tenue                                 |
| DELETE  | `/api/tenues/{id}/like`                 | N'aime plus une tenue                          |
| GET     | `/api/tenues/{id}/commentaires`         | Commentaires d'une tenue                       |
| POST    | `/api/tenues/{id}/commentaires`         | Commente une tenue (`contenu`), réservé aux amis |
| GET     | `/api/moi/statistiques`                 | Statistiques de « Mon espace »                 |
| GET     | `/api/admin/indicateurs`                | Indicateurs du site (super admin)              |
| GET     | `/api/admin/membres`                    | Membres, 20 par page (`q`, `page`) (super admin) |
| GET     | `/api/admin/membres/{id}`               | Un membre et ce que supprimer son compte effacerait (super admin) |
| PATCH   | `/api/admin/membres/{id}`               | Bloque ou débloque (`estBloque`), change le rôle (`role` : `MEMBRE`, `ADMIN`, `SUPER_ADMIN`) (super admin) |
| DELETE  | `/api/admin/membres/{id}`               | Supprime un compte (super admin)               |
| GET     | `/api/admin/referentiels`               | Catégories, couleurs et marques avec leur nombre d'articles |
| POST    | `/api/admin/categories`                 | Ajoute une catégorie (`parentId`, `nom`, `zoneTenue`) |
| PUT     | `/api/admin/categories/{id}`            | Renomme une catégorie ou change sa zone de tenue |
| DELETE  | `/api/admin/categories/{id}`            | Supprime une catégorie inutilisée et sans sous-catégorie |
| POST    | `/api/admin/couleurs`                   | Ajoute une couleur (`nom`, `codeHex`)          |
| PUT     | `/api/admin/couleurs/{id}`              | Modifie une couleur                            |
| DELETE  | `/api/admin/couleurs/{id}`              | Supprime une couleur inutilisée                |
| PUT     | `/api/admin/marques/{id}`               | Renomme une marque                             |
| DELETE  | `/api/admin/marques/{id}`               | Supprime une marque et la retire des articles  |
| GET     | `/api/admin/moderation`                 | Commentaires, annonces en ligne et tenues publiques récents |
| DELETE  | `/api/admin/moderation/commentaires/{id}` | Supprime un commentaire                      |
| DELETE  | `/api/admin/moderation/annonces/{id}`   | Retire une annonce du marché                   |
| DELETE  | `/api/admin/moderation/tenues/{id}`     | Supprime une tenue                             |

Les routes de consultation du réseau social (`GET` sur les profils, les membres, un article ou une tenue et leurs commentaires) sont ouvertes aux visiteurs sans compte ; l'API ne renvoie que ce qu'ils ont le droit de voir.

Les routes `/api/admin/…` demandent le rôle administrateur (403 sinon) ; les indicateurs et la gestion des membres, le rôle super admin.

Les erreurs sont renvoyées en JSON : `{"message": "..."}`, avec un objet `violations` (`{"champ": "message"}`) pour les erreurs de validation (422).

L'API répond en français, en anglais ou en portugais selon l'en-tête `Accept-Language` de la requête (`fr` par défaut, `en`, `pt`) ; la réponse indique la langue utilisée dans `Content-Language`. Les codes (statut d'un article, type de stockage, zone d'une tenue…) ne changent jamais : les clients les traduisent eux-mêmes. Dans `/api/referentiels`, chaque catégorie a un champ `reference` (son chemin en français), identique dans toutes les langues.

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

Importez `postman/penderie.postman_collection.json` dans Postman. Lancez d'abord « Authentification › Connexion » : Postman garde le cookie de session pour les requêtes suivantes, et les requêtes d'inventaire remplissent elles-mêmes les identifiants (logement, pièce, article) à réutiliser. Le dossier « Back office » se connecte avec le compte super admin de démonstration.

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
