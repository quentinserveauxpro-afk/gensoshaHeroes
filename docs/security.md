# Sécurité

## Sommaire

1. [Authentification](#authentification)
2. [Rôles](#rôles)
3. [Règles d'accès](#règles-daccès)
4. [Voters](#voters)
5. [Protection CSRF](#protection-csrf)
6. [Validation serveur](#validation-serveur)
7. [Gestion des secrets](#gestion-des-secrets)
8. [Actions sensibles](#actions-sensibles)
9. [Points d'attention](#points-dattention)

## Authentification

La configuration se trouve dans `config/packages/security.yaml`.

- **Fournisseur** : `app_user_provider`, qui lit l'entité `App\Entity\User` par sa propriété `email`.
  Le fournisseur `users_in_memory` de la recette est encore déclaré, mais n'est pas utilisé.
- **Pare-feu `main`** :
  - `lazy: true` ;
  - authentification `form_login` : `login_path` et `check_path` valent `app_login` (`/login`),
    et `default_target_path` vaut `app_tactical_center` ;
  - `enable_csrf: true`.
- **Déconnexion** : `GET /logout` (`app_logout`), qui redirige vers `/login`.
- **Hachage** : algorithme `auto` pour `PasswordAuthenticatedUserInterface`. En environnement
  `test`, son coût est réduit au minimum.
- **Session** : `User::__serialize()` remplace le hachage du mot de passe par son empreinte CRC32C.
  Ainsi, le hachage n'est jamais stocké en session, et la session est invalidée si le mot de passe change.
- Il n'existe ni authenticator personnalisé, ni « se souvenir de moi », ni réinitialisation de mot
  de passe, ni inscription publique.

## Rôles

| Rôle | Hérite de | Attribution |
|---|---|---|
| `ROLE_USER` | — | Ajouté automatiquement par `User::getRoles()` |
| `ROLE_OPERATOR` | `ROLE_USER` | Formulaire `UserType` (libellé « Opérateur ») |
| `ROLE_ADMIN` | `ROLE_OPERATOR` | Formulaire `UserType` (libellé « Administrateur ») |

Aucun autre rôle n'existe. Un compte avec seulement `ROLE_USER` peut se connecter, mais n'a accès
à aucune page, puisque toutes exigent au moins `ROLE_OPERATOR`.

## Règles d'accès

Les règles s'appliquent à deux niveaux.

**1. `access_control`** (la première règle correspondante s'applique) :

| Chemin | Rôle |
|---|---|
| `^/login$` | `PUBLIC_ACCESS` |
| `^/admin` | `ROLE_ADMIN` |
| `^/` | `ROLE_OPERATOR` |

Le pare-feu `dev` (`/_profiler`, `/_wdt`, `/assets`, `/build`) n'est pas sécurisé.

**2. `#[IsGranted]`** sur les contrôleurs et les actions :

| Portée | Rôle |
|---|---|
| Classes `TacticalCenterController`, `MissionController`, `HeroController`, `RequeteCitoyenneController`, `SquadBuilderController`, `AlerteController` | `ROLE_OPERATOR` |
| Classes `Admin\UserController`, `Admin\VillainController` | `ROLE_ADMIN` |
| Création, modification et suppression d'un héros | `ROLE_ADMIN` |
| Création et modification d'une mission, transitions de statut, ajout et suppression d'objectif | `ROLE_ADMIN` |
| Conversion d'une requête en mission | `ROLE_ADMIN` |

Les templates masquent aussi les actions réservées (`is_granted('ROLE_ADMIN')`). Ce masquage
n'est qu'une commodité : le contrôle effectif reste côté serveur. Le rôle de chaque route est
détaillé dans [routes.md](routes.md).

## Voters

**Aucun voter n'est défini.** Il n'existe pas de dossier `src/Security/`. Les autorisations
reposent uniquement sur les rôles : un opérateur peut agir sur toutes les missions et toutes les
requêtes, sans notion de propriété.

## Protection CSRF

Deux mécanismes coexistent.

**Jetons sans état** (`config/packages/csrf.yaml`) pour les identifiants `submit` (tous les
formulaires Symfony Form), `authenticate` (connexion) et `logout`. Le contrôleur Stimulus
`csrf_protection_controller.js` effectue un *double-submit* par cookie, et `check_header: true`
accepte aussi le jeton envoyé en en-tête par Turbo.

**Jetons de session nommés** pour les formulaires POST écrits à la main, vérifiés par `isCsrfTokenValid()` :

| Action | Identifiant du jeton |
|---|---|
| Acquitter une alerte | `acquitter{id}` |
| Supprimer un héros | `supprimer-hero{id}` |
| Transition de mission | `{action}{id}` (par exemple `cloturer12`) |
| Progression d'un objectif | `objectif{id}` |
| Supprimer un objectif | `supprimer-objectif{id}` |
| Convertir une requête | `convertir{id}` |
| Classer une requête | `classer{id}` |
| Déployer une équipe | `deployer{id}` |

Un jeton invalide renvoie une erreur 403 pour les transitions, les objectifs, la conversion, le
classement et le déploiement. Pour l'acquittement d'alerte et la suppression de héros, l'action
est simplement ignorée et l'utilisateur est redirigé. Le cas est testé par
`SecurityTest::testActionSensibleSansJetonCsrfRefusee`.

Toutes les actions qui modifient des données utilisent `POST`, à l'exception de `GET /logout`.

## Validation serveur

- **Contraintes d'entité** (`#[Assert\...]`, `#[Assert\Callback]`, `UniqueEntity`), détaillées
  dans [domain-model.md](domain-model.md).
- **Contraintes de formulaire** : `NotBlank`, `Length`, `Range`, et mot de passe de 8 caractères minimum.
- **Paramètres de requête typés** par `#[MapQueryParameter]` : `int`, `bool` et enums
  (`MissionStatut`, `HeroRole`, `Difficulte`…). Une valeur hors domaine est rejetée par Symfony.
- **Liste d'identifiants de l'équipe** : `SquadBuilder::parserEquipe()` ne conserve que des entiers
  positifs uniques, 12 au maximum.
- **Liste blanche des filtres** : `SquadBuilder::FILTRES` pour la réserve, et `nom`, `sante` ou
  `niveau` pour le tri du roster.
- **Règles métier** : revalidées côté serveur au moment du déploiement (`DeploymentValidator`),
  dans une transaction avec verrou d'écriture. Le JavaScript n'est jamais fiable.
- **Échappement** : l'échappement automatique de Twig est actif. Le seul `|raw` du projet
  (`components/_tactical_map.html.twig`) s'applique au bloc HUD, déjà rendu et échappé par Twig via
  `{% set %}`. Ce point n'a pas fait l'objet d'un audit exhaustif.

## Gestion des secrets

| Élément | Emplacement | Versionné |
|---|---|---|
| `DATABASE_URL` (identifiants MySQL) | `.env.local`, `.env.test.local` | **Non** (`.gitignore`) |
| `APP_SECRET` (dev) | `.env.dev` | Oui, valeur de développement générée par la recette |
| `APP_SECRET` (test) | `.env.test` | Oui, valeur de test |
| `APP_SECRET` (base) | `.env` | Oui, **valeur vide** |
| Coffre Symfony (`secrets:set`) | `config/secrets/` | **Non utilisé** (le dossier n'existe pas) |

Recommandations, non appliquées à ce jour :

- en production, définir `APP_SECRET` et `DATABASE_URL` via des variables d'environnement réelles
  ou `php bin/console secrets:set` ;
- changer ou supprimer les comptes de démonstration (`admin@genosha.test`, `operateur@genosha.test`)
  et ne jamais charger les fixtures ;
- ne jamais committer `.env.local`.

## Actions sensibles

| Action | Route | Protection |
|---|---|---|
| Déployer une équipe | `app_squad_builder_deploy` | `ROLE_OPERATOR`, CSRF, validation serveur, transaction et verrous |
| Clôturer ou annuler une mission | `app_mission_transition` | `ROLE_ADMIN`, CSRF, `MissionWorkflowException` |
| Convertir une requête | `app_requete_convert` | `ROLE_ADMIN`, CSRF, conversion unique |
| Classer une requête | `app_requete_close` | `ROLE_OPERATOR`, CSRF |
| Supprimer un héros | `app_hero_delete` | `ROLE_ADMIN`, CSRF, refus si le héros est engagé |
| Créer ou modifier un utilisateur et ses rôles | `app_admin_user_*` | `ROLE_ADMIN`, CSRF du formulaire, hachage du mot de passe |

## Points d'attention

Ces constats sont tirés du code. Aucun n'a été corrigé.

- **Déconnexion par `GET`** sans jeton CSRF : un lien externe peut déconnecter un utilisateur.
  L'impact est faible.
- **Auto-rétrogradation** : un administrateur peut retirer son propre `ROLE_ADMIN`. Il n'existe
  aucune protection « dernier administrateur ».
- **Aucune limitation des tentatives de connexion** (`login_throttling` non configuré).
- **`Hero::$image`** accepte un chemin ou une URL libre. Une valeur commençant par `http` est
  affichée telle quelle dans une balise `<img>` (`_hero_avatar.html.twig`), ce qui permet de charger
  une image externe. `Villain::$image` est saisissable, mais n'est affiché nulle part.
- **Le pare-feu `dev`** expose le profiler (comportement normal en développement uniquement).
