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

---

## 2026-10-23 — Modification d'un article et caméra du scan en plein écran

- **Outil** : Claude Code (Claude Opus 5.5)
- **Branches** : `feature/scan-plein-ecran` et `feature/modification-article`

**Prompt :**

> Modifie la fonctionnalité 'Modifier un article déjà enregistré' pour que le crayon de modification fonctionne. Pour cette page http://localhost:5173/scan mets un full screen en gardant le meme overlay, modifie juste la couleur du rond centrale plein en blanc

Le prompt est consigné une seule fois ici, pour éviter un conflit entre les deux branches.

**Résultat retenu (`feature/modification-article`) :**

- Le crayon de la fiche article ouvre le formulaire d'ajout en mode modification (`/articles/:id/modifier`), pré-rempli avec les valeurs de l'article
- Route `PUT /api/articles/{id}` : titre, catégorie (le détail change si la famille change), emplacement, étiquettes, détail ; réservée à celui qui a ajouté l'article ou au propriétaire du logement ; seul l'auteur peut changer « Article personnel »
- Correctif trouvé en testant : après le retrait d'une étiquette, la liste était renvoyée en objet JSON au lieu d'un tableau

**Résultat retenu (`feature/scan-plein-ecran`) :**

- Image de la caméra en plein écran, sous l'habillage inchangé (en-tête, cadre, consigne, onglets, boutons) ; le cadre devient transparent quand la caméra est active
- Ombre légère sous les textes pour rester lisibles sur n'importe quelle image
- Rond central du déclencheur en blanc plein

---

## 2026-11-02 — Point d'étape

- **Outil** : Claude Code (Claude Opus 5.5)
- **Branche** : aucune (question)

**Prompt :**

> Cela fait maintenant plus d'une semaine que je n'ai pas travaillé sur le projet, je ne me rappel plus ou en suis-je. Dit moi ce qu'on a fait depuis le début et si je suis à jour par rapport aux PR etc, et lance le serveur pour que je vois le rendu actuel.

**Résultat retenu :**

- Récapitulatif des PR #1 à #9 et des deux branches restées non poussées (modification d'article, scan en plein écran), serveur relancé avec un aperçu local des deux branches

---

## 2026-11-02 — Fiche ouverte dès la photo prise, remplie par l'IA en arrière-plan

- **Outil** : Claude Code (Claude Opus 5.5)
- **Branche** : `feature/scan-fiche-immediate`

**Prompt :**

> c'est bon, j'ai fais le git pull. Maintenant, je veux modifier le scan. Je vex qu'une fois que la photo soit prise, on se redirige directement sur sa fiche produit. Je ne veux plus que l'analyse ce fasse avac la caméra lancé. Je veux aussi pour le scan de vetement qu'il y ai plusieurs option d'encadré, c'est à idre, un haut (t-shirt), un bas ( pantalon) pour accompagné l'utilisateur à mettre l'artcile dans cet encadré. Je veux aussi une option sans encadré pour tout autre objet.

**Prompt de suivi :**

> Je ne fais pas encore le git push, je viens de regarder le rendu et ça ne me convient pas. Pour les encardé, je veux pas une forme noire, je veux un cadre de pointillé pour montrer visuellement à l'utilisateur ou placer l'article pour que l'analyse soit optimisé.

**Prompt de suivi :**

> Je viens de regarder le rendu, est il n'est toujours pas bon. Retire le cadrage haut, bas, etc et remet celui d'avant. Je n'ai pas fait le git push donc ca devrait être plus simple. Garde la redirection sur la fiche de l'article par contre mais laisse affiché les champs. La quand j'ai fait le test, ils n'étaient visibles, c'était l'analyse de l'IA qui la fait apparaitre au bout d'un certain temps. Ce que je veux : le scan, quand la photo est prise, direction la fiche de l'article avec les champs, la note, l'emplacement à remplir manuellement. L'IA rempli les champs qu'elle peux a travaers l'image prise, délai souhaité : 6s.

**Prompt de suivi** (réponse à la question sur le délai de 6 s) :

> On fait cette méthode : POC ( Proof of concept ) de la reconnaissance d'images. Utilisation OPEN ROUTER Bonne pratiques : L'image doit être réduite L'image doit être convertie en JPG L'image doit être transformée en B64 Le prompt envoyé au modèle doit être précis et reformulé

**Résultat écarté :**

- Cadres de visée « Haut », « Bas » et « Sans cadre » (silhouettes, puis cadres en pointillés) : abandonnés, le cadre d'origine est conservé

**Résultat retenu :**

- La photo prise, la fiche s'ouvre aussitôt avec tous ses champs, dont un nouveau champ « Note » et l'emplacement ; l'envoi de la photo puis l'analyse se poursuivent en arrière-plan et l'IA ne remplit que les champs restés vides
- API découpée en `POST /api/scans/photo` (enregistrement) et `POST /api/scans/analyse` (suggestions)
- Cause de l'affichage tardif des champs : la session PHP restait verrouillée pendant l'analyse et bloquait le chargement de la fiche ; elle est libérée dès le début de l'analyse
- Modèles gratuits OpenRouter conservés (bonnes pratiques inchangées : image réduite, JPEG, base64, prompt précis) ; le modèle le plus rapide (`gemma-4-26b-a4b-it`, 2 à 4 s) passe en premier, `gemma-4-31b-it` en secours ; analyse limitée à 20 s pour signaler vite une panne du fournisseur gratuit

**Prompt de suivi :**

> C'est mieux, mais retire les étiquettes, ajoute un champ couleur qui sera rempli par l'ia et également un champ marque

**Résultat retenu :**

- Les champs Marque et Couleur existaient déjà, mais n'apparaissaient qu'une fois la catégorie choisie : après la photo d'un vêtement, ils s'affichent dès l'ouverture de la fiche et l'IA les remplit
- Étiquettes retirées du formulaire ; en modification, celles déjà posées sur l'article sont conservées

**Prompt de suivi :**

> Jsp. Pour  le scan, j'ai toujours un problème. Ce projet est un projet universitaire, donc nous sommes plusieurs dizaines d'étudiant à faire la même chose, c'est pour cela que je te dit de passer par openrouteur, redimenssionner, etc. Pourquoi mon scan ne fonctionne t'il pas en autoomie alors que tout les autres étudiant on leurs champs qui se remplissent en 5s environ. Est ce que il y a une soluton pour reprendre le code des autres ou je ne sais quoi pour comparer, si je t'envoie un dépot github jsp.

**Résultat retenu :**

- Diagnostic d'après les journaux : la clé fonctionne (quota gratuit non atteint), mais les modèles Gemma gratuits, partagés par tous, répondent tantôt en 2 s, tantôt par une erreur 504 au bout de 13 à 27 s ; le modèle de secours de l'époque (Gemma 31B) dépendait du même fournisseur
- Si le modèle principal n'a pas répondu en 3 s, un second modèle gratuit hébergé ailleurs (`nvidia/nemotron-3.5-super-vl:free`) est interrogé en parallèle ; la première fiche valide l'emporte
- Bug trouvé en simulant OpenRouter en local (réponses lentes, 500, 504, JSON invalide) : une erreur 5xx faisait planter l'analyse au lieu de passer au modèle suivant
- L'envoi de la photo et son analyse partent en même temps au lieu de l'un après l'autre
- Autre cause de lenteur mesurée : sous Windows, chaque requête Symfony en mode debug coûte environ 2 s, à cause du dossier partagé avec Docker
