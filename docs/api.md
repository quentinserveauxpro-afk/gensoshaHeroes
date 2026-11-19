# API

**L'application n'expose pas d'API publique.** Il n'y a ni API Platform, ni authentification par
jeton, ni versionnement d'API. Toutes les interactions passent par des pages HTML rendues côté
serveur.

Il existe un seul point d'accès JSON, **interne**, utilisé par le contrôleur Stimulus `countdown`
pour resynchroniser les comptes à rebours.

## `GET /api/missions/{id}/timer`

| Élément | Valeur |
|---|---|
| Nom de route | `app_api_mission_timer` |
| Contrôleur | `App\Controller\AlerteController::timer()` |
| Méthode | `GET` |
| Paramètre | `id` : entier (`\d+`), identifiant de la mission |
| Authentification | Session (cookie) d'un utilisateur `ROLE_OPERATOR` ou `ROLE_ADMIN` |
| Format | `application/json` |

### Réponse 200

```json
{
    "mission": 12,
    "secondesRestantes": 842,
    "termine": false,
    "libelle": "00:14:02",
    "echeance": "2026-11-19T15:02:00+00:00"
}
```

| Champ | Type | Description |
|---|---|---|
| `mission` | int | Identifiant de la mission |
| `secondesRestantes` | int | Temps restant en secondes, toujours ≥ 0 |
| `termine` | bool | `true` si le temps restant vaut 0 |
| `libelle` | string | `HH:MM:SS`, ou `"Mission terminée"` |
| `echeance` | string \| null | `dateFinPrevue` au format ATOM (ISO 8601), `null` si absente |

### Erreurs

| Cas | Réponse |
|---|---|
| Utilisateur non connecté | Redirection 302 vers `/login`, gérée par le pare-feu, sans JSON |
| Rôle insuffisant (`ROLE_USER` seul) | 403 |
| Mission inexistante | 404 (résolution automatique de l'entité) |
| `id` non numérique | 404 (la route ne correspond pas) |

Couverture de test : `PagesTest::testApiTimerNeRenvoieJamaisDeDureeNegative`.
