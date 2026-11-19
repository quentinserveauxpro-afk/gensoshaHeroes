# Routes

Cette table est générée à partir de `php bin/console debug:router --show-controllers`
(19/11/2026, environnement `dev`). Les routes de débogage `_profiler*`, `_wdt*` et
`_preview_error` ne sont pas listées.

Le **rôle requis** combine `access_control` (`security.yaml`) et les attributs `#[IsGranted]`.
`ROLE_ADMIN` inclut `ROLE_OPERATOR`.

## Pages et actions

| Nom | URL | Méthode | Contrôleur | Rôle | Description |
|---|---|---|---|---|---|
| `app_login` | `/login` | GET, POST | `SecurityController::login` | Public | Formulaire de connexion ; redirige vers `/` si déjà connecté |
| `app_logout` | `/logout` | GET | `SecurityController::logout` | Connecté | Déconnexion (interceptée par le pare-feu) |
| `app_tactical_center` | `/` | GET | `TacticalCenterController::index` | `ROLE_OPERATOR` | Centre tactique ; paramètres `statut`, `difficulte`, `urgence`, `mission` |
| `app_mission_index` | `/missions` | GET | `MissionController::index` | `ROLE_OPERATOR` | Liste paginée ; paramètres `page`, `statut` |
| `app_mission_show` | `/missions/{id}` | GET | `MissionController::show` | `ROLE_OPERATOR` | Fiche de mission et pronostic |
| `app_mission_new` | `/missions/nouvelle` | GET, POST | `MissionController::new` | `ROLE_ADMIN` | Création (brouillon) |
| `app_mission_edit` | `/missions/{id}/modifier` | GET, POST | `MissionController::edit` | `ROLE_ADMIN` | Modification |
| `app_mission_objectif_add` | `/missions/{id}/objectifs` | POST | `MissionController::ajouterObjectif` | `ROLE_ADMIN` | Ajout d'un objectif |
| `app_mission_objectif_progress` | `/missions/{id}/objectifs/{objectif}/progression` | POST | `MissionController::progressionObjectif` | `ROLE_OPERATOR` | Mise à jour de la progression (champs `progression`, `termine`, `_token`) |
| `app_mission_objectif_delete` | `/missions/{id}/objectifs/{objectif}/supprimer` | POST | `MissionController::supprimerObjectif` | `ROLE_ADMIN` | Suppression d'un objectif |
| `app_mission_transition` | `/missions/{id}/{action}` | POST | `MissionController::transition` | `ROLE_ADMIN` | `action` ∈ `publier`, `lancer`, `cloturer`, `annuler` ; `resultat=succes` pour une clôture réussie |
| `app_squad_builder` | `/squad-builder` | GET | `SquadBuilderController::choisir` | `ROLE_OPERATOR` | Redirige vers la mission demandée (`?mission=`) ou la première mission en brouillon ou en attente |
| `app_squad_builder_mission` | `/squad-builder/{id}` | GET | `SquadBuilderController::composer` | `ROLE_OPERATOR` | Composition ; paramètres `equipe`, `taille`, `filtre`, `role`, `niveau`, `sante` |
| `app_squad_builder_deploy` | `/squad-builder/{id}/deployer` | POST | `SquadBuilderController::deployer` | `ROLE_OPERATOR` | Déploiement (champs `equipe`, `lead` facultatif, `_token`) |
| `app_hero_index` | `/heros` | GET | `HeroController::index` | `ROLE_OPERATOR` | Roster ; paramètres `page`, `q`, `role`, `tri` |
| `app_hero_show` | `/heros/{id}` | GET | `HeroController::index` | `ROLE_OPERATOR` | Roster avec la fiche du héros `id` |
| `app_hero_new` | `/heros/nouveau` | GET, POST | `HeroController::new` | `ROLE_ADMIN` | Enrôlement |
| `app_hero_edit` | `/heros/{id}/modifier` | GET, POST | `HeroController::edit` | `ROLE_ADMIN` | Modification |
| `app_hero_delete` | `/heros/{id}/supprimer` | POST | `HeroController::delete` | `ROLE_ADMIN` | Suppression, refusée si le héros est engagé |
| `app_requete_index` | `/requetes` | GET, POST | `RequeteCitoyenneController::index` | `ROLE_OPERATOR` | Liste et saisie ; paramètres `categorie`, `urgentes`, `tri` |
| `app_requete_convert` | `/requetes/{id}/convertir` | POST | `RequeteCitoyenneController::convertir` | `ROLE_ADMIN` | Conversion en mission |
| `app_requete_close` | `/requetes/{id}/classer` | POST | `RequeteCitoyenneController::classer` | `ROLE_OPERATOR` | Classement sans suite |
| `app_alerte_ack` | `/alertes/{id}/acquitter` | POST | `AlerteController::acquitter` | `ROLE_OPERATOR` | Acquittement d'une alerte ; redirige vers la page précédente |
| `app_api_mission_timer` | `/api/missions/{id}/timer` | GET | `AlerteController::timer` | `ROLE_OPERATOR` | JSON du timer (voir [api.md](api.md)) |
| `app_admin_user_index` | `/admin/utilisateurs` | GET | `Admin\UserController::index` | `ROLE_ADMIN` | Liste des comptes |
| `app_admin_user_new` | `/admin/utilisateurs/nouveau` | GET, POST | `Admin\UserController::form` | `ROLE_ADMIN` | Création d'un compte |
| `app_admin_user_edit` | `/admin/utilisateurs/{id}/modifier` | GET, POST | `Admin\UserController::form` | `ROLE_ADMIN` | Modification d'un compte |
| `app_admin_villain_index` | `/admin/vilains` | GET | `Admin\VillainController::index` | `ROLE_ADMIN` | Liste des vilains (par menace décroissante) |
| `app_admin_villain_new` | `/admin/vilains/nouveau` | GET, POST | `Admin\VillainController::form` | `ROLE_ADMIN` | Création d'un vilain |
| `app_admin_villain_edit` | `/admin/vilains/{id}/modifier` | GET, POST | `Admin\VillainController::form` | `ROLE_ADMIN` | Modification d'un vilain |

Tous les paramètres `{id}` et `{objectif}` sont contraints à `\d+`. Les contrôleurs sont dans
l'espace de noms `App\Controller`.

## Remarques

- **Ordre de déclaration** : `app_mission_new` (`/missions/nouvelle`) n'entre pas en conflit avec
  `app_mission_show`, car `{id}` n'accepte que des chiffres. Il en va de même pour `/heros/nouveau`.
- **Routes absentes** : il n'existe aucune route pour supprimer une mission, un vilain ou un
  utilisateur, ni pour gérer les bonus, synergies et aptitudes.
- Pour régénérer cette liste : `php bin/console debug:router --show-controllers`.
