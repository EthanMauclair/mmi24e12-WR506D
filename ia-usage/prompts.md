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
