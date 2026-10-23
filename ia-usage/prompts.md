# Suivi des prompts IA

Chaque utilisation d'un assistant IA est consignée ici : date, outil, prompt, et ce qui a été retenu.

---

## 2026-10-20 — Création de la structure du projet

- **Outil** : Claude Code (Claude Opus 5.5)
- **Branche** : `feature/project-setup`

**Prompt :**

> Tu es dans le dossier de ma futur application, c'est une application de pendeire.
> Crée la structure :
>
> Les choix techniques :
> Symfony 7.4 LTS
> Vue.js
> SQL, dernière version de MariaDB
>
> Utilisation de docker desktop
> Utilisation de POSTMAN
> Github :
> Gitflow main reste toujours déployable, develop intègre, une branche par fonctionnalité nommée feature/…, un correctif urgent en hotfix/…. Aucun commit direct sur main ni sur develop
> PR Obligatoires même en travaillant seul. Vous rédigez la description, vous vous relisez, vous fusionnez
> Commits conventionnels (feat:, fix:, docs:, refactor:, test:, chore:), au présent de l'impératif, un commit par intention. Ni « wip », ni « ça marche », ni un commit de 4 000 lignes généré d'un bloc
> Rien de superflu dans le dépôt, pas de vendor/, pas de node_modules/, pas de .env local, aucun secret. Un secret poussé une fois est compromis, même supprimé au commit suivant
> Read.md permettant à un tiers d'installer et de lancer le projet sans vous poser de question
> Aucun commit sur la branche "main"
>
> Prompts IA suivis dans un fichier ia-usage/prompts.md
> Ne signe pas tes commits (retire les mentions co-authored-by, etc) - pareil pour les futurs commits.
>
> Nom du repo Github : mmi24e12-WR506D

**Résultat retenu :**

- Squelette Symfony 7.4 dans `backend/` (Doctrine, Serializer, Validator, CORS, MakerBundle) avec une route `/api/health`
- Projet Vue 3 (Vite, Vue Router, Pinia, ESLint, Prettier) dans `frontend/`
- Environnement Docker : MariaDB 13.0, PHP 8.4-FPM, Nginx, Node 24
- Collection Postman, modèle de PR, README d'installation

---

## 2026-10-20 — Rédaction des pull requests

- **Outil** : Claude Code (Claude Opus 5.5)
- **Branche** : `feature/project-setup`

**Prompt :**

> Pour la PR, dnne moi le titre conventionnel à mettre ainsi que la description, et ça pour chaque PR qu'on va faire pendant le projet.

**Résultat retenu :**

- Titre et description de la PR `feature/project-setup` → `develop`, rédigés selon le modèle `.github/pull_request_template.md`

---

## 2026-10-20 — Fusion bloquée par la protection de branche

- **Outil** : Claude Code (Claude Opus 5.5)
- **Branche** : `feature/project-setup`

**Prompt :**

> Je ne peux pas Merge pull request dû au fait des règles de protection sur les branches

**Résultat retenu :**

- Réglage de la protection de `develop` et `main` pour un projet en solo : PR obligatoire, mais 0 approbation requise (on ne peut pas approuver sa propre PR)

---

## 2026-10-20 — Récupération des commits non poussés

- **Outil** : Claude Code (Claude Opus 5.5)
- **Branche** : `feature/ia-usage-log`

**Prompt :**

> J'ai fusionné sans puh :/

**Résultat retenu :**

- Les commits non poussés de `feature/project-setup` sont repris sur une nouvelle branche `feature/ia-usage-log`, créée depuis `develop`, puis fusionnés par une nouvelle PR

---

## 2026-10-20 — Modèle de données

- **Outil** : Claude Code (Claude Opus 5.5)
- **Branche** : `feature/modele-donnees`

**Prompt :**

> Je viens d'ajouter un dossier annexe dans le projet. Il n'est pas à poussé sur github, il est simplement ici pour accompagner au développement de l'application. Prends connaissance du fichier contexte-projet.md et ensuite crée le modèle de données

Le prompt s'appuie sur deux documents de cadrage locaux (non versionnés) : le contexte du projet et la spécification du modèle de données.

**Résultat retenu :**

- 11 énumérations PHP et 22 entités Doctrine avec leurs repositories
- Validations Symfony (règles des mineurs, articles personnels, tenues, abonnements, prêts, annonces, conversations)
- Migration MariaDB avec les contraintes CHECK
- Fixtures des catégories (arborescence à 3 niveaux) et des couleurs

---

## 2026-10-23 — Écrans de connexion et d'inscription

- **Outil** : Claude Code (Claude Opus 5.5)
- **Branche** : `feature/ecrans-authentification`

**Prompt :**

> Reproduis en Vue.js l'écran docs/maquettes/Inscription.dc.html docs/maquettes/Connexion.dc.html. Reprends exactement les couleurs, tailles, espacements et textes. Les balises sc-for, sc-if et {{ }} viennent de l'outil de maquette : traduis-les en v-for, v-if et données réactives. Mets les couleurs dans des variables CSS communes.

Le prompt s'appuie sur des maquettes HTML locales (non versionnées).

**Résultat retenu :**

- Couleurs et police dans des variables CSS communes (`frontend/src/assets/variables.css`), styles de formulaire partagés dans `main.css`
- Vues `ConnexionView` et `InscriptionView`, routes `/connexion` et `/inscription`
- Affichage du mot de passe et choix de la visibilité du profil en données réactives (`ref`, `computed`, `v-for`)

---

## 2026-10-23 — Ajustements de l'écran d'inscription

- **Outil** : Claude Code (Claude Opus 5.5)
- **Branche** : `feature/ajustements-inscription`

**Prompts :**

> lance le serveur pour voir le rendu

> On va ajuster quelques éléments. Pour la page inscription :
> -dans le champs de 'nom d'utilisateur', enlèves le @ lea.chine et mets Nom d'utilisateur à la place.
> -dans le champs  'E-mail', enlèves vous@exemple.fr et mets E-mail à la place.
> -pour la date de naissance, ajoute un calendrier pour seléctionner une date facilement. Et garde la structure conventionnelle. Je m'explique, quand j'ai rentré une date, ça a supprimer les / et ça a juste mis les chiffres en suivant.
> -retire aussi le 100% gatuit en haut de l'écran

**Résultat retenu :**

- Serveur Vite lancé en local pour vérifier le rendu
- Textes d'aide « Nom d'utilisateur » et « E-mail », préfixe @ retiré
- Champ date natif (`type="date"`) : calendrier, format JJ/MM/AAAA conservé, date future impossible
- Pastille « 100 % gratuit » retirée

**Prompt de suivi :**

> Je viens de regarder les modifs. C'est pas mal, supprime la flèche retour en haut à gauche, il y a déjà  'Déjà inscrit ? Se connecter' en bas de la page, ajoute également un bandeau bordeaux pour habiller la page qui englobe Créer un compte et Indexez votre dressing et partagez vos tenues. je n'est pas fait :git push -u origin feature/ajustements-inscription. Je peux le faire une fois les modifs faites ou je dois en faire un nouveau ?

**Résultat retenu :**

- Flèche de retour retirée (le lien « Se connecter » en bas de page suffit)
- Bandeau bordeaux autour du titre et du sous-titre, dans le style de l'écran de connexion
- Les nouveaux commits restent sur la même branche, pas encore poussée : un seul push suffit

**Prompts de suivi (abandonnés) :**

> C'est mieux, avant que je pousse, fait une dernière modif, réduit l'espace entre 'J'accepte les conditions d'utilisation et la politique de confidentialité.' et le bouton créer son compte. Il y a u ntrop grand espace blanc.

> Je n'ai toujours pas poussé, je n'aime pas cette nouvelle version. remet le bouton comme avant mais réduit la taille du formulaire ci possible

**Résultat retenu :**

- Aucun : le rapprochement du bouton ne convenait pas. Retour à la version avec le bandeau bordeaux, bouton de création de compte en bas de l'écran

---

## 2026-10-23 — Écrans du dressing, d'une pièce et d'un article

- **Outil** : Claude Code (Claude Opus 5.5)
- **Branche** : `feature/ecrans-inventaire`

**Prompt :**

> Reproduis en Vue.js l'écran docs/maquettes/Main.dc.html. docs/maquettes/Stockage.dc.html et docs/maquettes/Article.dc.html. Reprends exactement les couleurs, tailles, espacements et textes. Les balises sc-for, sc-if et {{ }} viennent de l'outil de maquette : traduis-les en v-for, v-if et données réactives. Mets les couleurs dans des variables CSS communes.

Le prompt s'appuie sur des maquettes HTML locales (non versionnées).

**Résultat retenu :**

- Nouvelles couleurs ajoutées aux variables CSS communes (pastels des pièces, vignettes, éléments sur photo, succès, interrupteur)
- Composant `BarreNavigation` partagé
- Vues `DressingView` (`/dressing`), `PieceView` (`/pieces/:id`) et `ArticleView` (`/articles/:id`), avec des données d'exemple en attendant l'API
- `sc-for` → `v-for` (pièces, articles, onglets, étiquettes, indicateurs de photo), `sc-if` → `v-if` (badge « Prêté »), onglet de stockage et interrupteur « Article personnel » en données réactives
- Deux défauts des maquettes corrigés : onglets qui passaient sur deux lignes, éléments de la photo masqués par le contenu

---

## 2026-10-23 — Ordre de développement front / back

- **Outil** : Claude Code (Claude Opus 5.5)
- **Branche** : aucune (question)

**Prompt :**

> Selon toi, est-il mieux de faire toutes les pages en front et après relier le back ou bien mixer les 2 en même temps ?

**Résultat retenu :**

- Avancer fonctionnalité par fonctionnalité (API, puis écran qui l'utilise), en commençant par l'authentification puis l'inventaire

---

## 2026-10-23 — Authentification et inventaire de bout en bout

- **Outil** : Claude Code (Claude Opus 5.5)
- **Branche** : `feature/authentification-inventaire`

**Prompt :**

> Termine la partie Authentification et Inventaire. Relie le back avec le front pour que ces 2 partie soit finit

**Résultat retenu :**

- API d'authentification par session Symfony (`json_login`) : inscription avec rattachement des mineurs, connexion limitée à 5 tentatives, déconnexion, utilisateur connecté
- Droits d'accès par voters : membres du logement, gestion réservée au propriétaire, articles personnels visibles par leur seul auteur
- API d'inventaire : logements, pièces, stockages, articles, recherche « Où est rangé… ? »
- Données de démonstration reprenant les maquettes, et rechargement des fixtures corrigé (catégories auto-référencées)
- Front : client API, store de session, routes protégées, écrans reliés à l'API avec gestion des erreurs
- `vendor/` et `var/` du backend dans des volumes Docker (requêtes environ 5 fois plus rapides sous Windows)
- Projet renommé « penderie » (README, Postman, `.env.example`), collection Postman complétée et vérifiée avec Newman

---

## 2026-10-23 — Ajout d'article par scan

- **Outil** : Claude Code (Claude Opus 5.5)
- **Branche** : `feature/ajout-article-scan`

**Prompts :**

> C'est propre, on reviendra plus tard pour modifier le front. Continuons sur la fonctionnalité du scan.

Choix du service de reconnaissance des vêtements (question posée par l'assistant) :

> On va utiliser OpenRouteur avec les modèle Gemma free google/gemma-4-31b-it:free

Consignes ajoutées en cours de route :

> L'image doit être réduite
> L'image doit être convertie en JPG
> L'image doit être transformée en B64
> Le prompt envoyé au modèle doit être précis et reformulé

Le prompt s'appuie sur des maquettes HTML locales (non versionnées) : caméra, vérification du vêtement, vinyle trouvé.

**Résultat retenu :**

- Photo réduite à 1024 px et convertie en JPEG dans le navigateur puis à nouveau côté serveur (GD), encodée en base64 pour le modèle
- Reconnaissance des vêtements via OpenRouter (`google/gemma-4-31b-it:free`) : prompt système (rôle, règles, format JSON exact) et demande reformulée avec les catégories et couleurs autorisées ; chaque valeur renvoyée est vérifiée en base ; 30 analyses par heure et par utilisateur ; repli sur la saisie manuelle sans clé ou en cas d'échec
- Codes-barres lus dans le navigateur (ZXing en WebAssembly, servi par l'application) ; fiches cherchées dans Open Library, Google Books et MusicBrainz (réessai en cas de limite, correspondance exacte du code exigée)
- Création d'article avec son détail (vêtement, livre, vinyle), sa photo ou sa couverture, son emplacement et ses étiquettes
- Écrans caméra, vérification du vêtement, livre ou vinyle trouvé, saisie manuelle
- Correctif : `backend/.env` (valeurs par défaut, sans secret) n'avait jamais été versionné

**Prompts de suivi :**

> Expliuqe moi en détail comment faire, je ne comprends pas Ajoute ta clé dans .env (OPENROUTER_API_KEY=sk-or-...), puis lance docker compose up -d --build php. Le --build est nécessaire cette fois-ci, à cause de la nouvelle extension GD.
> Dis-moi quand c'est fait, sans coller la clé ici : je lancerai un vrai test de reconnaissance.

> c'est fait, il mereste le push à faire

**Résultat retenu :**

- Procédure pas à pas : création de la clé OpenRouter, ajout dans le `.env` racine (jamais dans `backend/.env`), recréation du conteneur PHP, vérification sans afficher la clé
- Test réel : une veste en jean reconnue (« Vestes en jean », Bleu, « Veste en jean », aucune marque inventée) ; les échecs du fournisseur gratuit (429, 500, 504) retombent proprement sur la saisie manuelle
- Correctifs trouvés pendant ce test : réessai de l'analyse après une surcharge, et droits d'écriture de PHP-FPM sur `var/` (la limite de 30 analyses par heure n'était jamais enregistrée)

---

## 2026-10-23 — Modèle d'IA de secours

- **Outil** : Claude Code (Claude Opus 5.5)
- **Branche** : `feature/ia-modele-secours`

**Prompt :**

> ajoute google/gemma-4-26b-a4b-it:free. Ensuite dit moi si le front pour le scan est fait et est-ce que la fonctionnalitée du scan est terminé ou on doit encore faire des choses

**Résultat retenu :**

- `google/gemma-4-26b-a4b-it:free` ajouté comme modèle de secours (paramètre `models` d'OpenRouter, variable `OPENROUTER_MODELE_SECOURS`)
- Cause des échecs trouvée en testant avec de vraies photos : le modèle raisonne avant de répondre (jusqu'à ~1 500 jetons, impossible à désactiver chez le fournisseur gratuit) et épuisait la limite de 400 jetons ; limite portée à 4 000, et budget global de 80 s (sous les 90 s de Nginx) avec un nouvel essai seulement s'il reste au moins 20 s
- Bilan de la fonctionnalité de scan et liste de ce qui reste à faire
