# Échelle temporelle accélérée

Les trajets et les soins sont des **simulations accélérées** : la distance affichée sur la carte
reste la distance réelle, seule la durée est compressée.

| Opération | Minimum | Maximum | Service |
|---|---|---|---|
| Trajet vers une mission, retour au QG | 30 s (0 s si déjà sur place) | 600 s (10 min) | `App\GameTime\HeroTravelTimeCalculator` |
| Soin à l'infirmerie | 60 s | 900 s (15 min) | `App\GameTime\InfirmaryRecoveryTimeCalculator` |

## Configuration

Source unique : `config/packages/game_time.yaml` (paramètres `app.game_time.*`, lus par
`App\GameTime\GameTimeConfiguration`). Les bornes et la distance de référence peuvent être
surchargées dans `.env.local` :

| Paramètre | Variable | Défaut |
|---|---|---|
| `app.game_time.hero_travel_min_seconds` | `GAME_TIME_HERO_TRAVEL_MIN_SECONDS` | 30 |
| `app.game_time.hero_travel_max_seconds` | `GAME_TIME_HERO_TRAVEL_MAX_SECONDS` | 600 |
| `app.game_time.hero_travel_reference_km` | `GAME_TIME_HERO_TRAVEL_REFERENCE_KM` | 1000 |
| `app.game_time.infirmary_min_seconds` | `GAME_TIME_INFIRMARY_MIN_SECONDS` | 60 |
| `app.game_time.infirmary_max_seconds` | `GAME_TIME_INFIRMARY_MAX_SECONDS` | 900 |
| `app.game_time.flight_modifier` / `flight_aptitude` | — | 0,75 / `VOL` |
| `app.game_time.medical_support_modifier` | — | 0,85 |
| `app.game_time.saturated_capacity_modifier` | — | 1,10 |
| `app.game_time.critical_severity_modifier` / `injury_severity_modifier` | — | 1,35 / 1,10 |

Les seuils médicaux (santé critique, sortie…) restent dans les variables `INFIRMERIE_*`
(voir [infirmerie.md](infirmerie.md)). `SIMULATION_FACTEUR_TEMPS` ne règle plus que le rythme
des étapes de combat ; il n'influe plus sur la durée des trajets.

## Trajets

```
ratio     = min(distance / distanceReference, 1)
base      = min + ratio × (max − min)
mobilité  = 1,20 − mobilité effective / 100 × 0,40     (bonus de vitesse actifs inclus)
fatigue   = 1 + fatigue / 100 × 0,30
santé     = 1 + (100 − santé %) / 100 × 0,20
vol       = 0,75 si le héros possède l'aptitude VOL, 1 sinon
durée     = clamp(round(base × mobilité × fatigue × santé × vol), 30, 600)
```

Déjà sur place (≤ 50 m d'une mission, ≤ 1 km du QG) : arrivée immédiate. Exemple : Paris → Lyon
(392 km), mobilité 50, en pleine forme : 30 + 0,392 × 570 = 253 s. La durée est calculée au départ
avec l'état du héros à cet instant, puis enregistrée (date de départ, date d'arrivée prévue).
Le projet ne modélise pas de moyen de transport : seule l'aptitude de vol est prise en compte.

## Soins

```
gravité   = (100 − santé %) / 100 × 0,60 + fatigue / 100 × 0,40     (état à l'admission)
base      = 60 + gravité × (900 − 60)
vigueur   = 1,10 − vigueur effective / 100 × 0,20                    (bonus actifs inclus)
admission = 1,35 si santé critique ou 0 PV ; 1,10 pour une blessure ; 1 sinon
soutien   = 0,85 si un autre héros possédant SUPPORT_MEDICAL est disponible au QG ;
            0,925 si seul le patient la possède ; 1 sinon
capacité  = 1,10 si l'infirmerie dépasse sa capacité ; 1 sinon
durée     = clamp(round(base × vigueur × admission × soutien × capacité), 60, 900)
```

| Situation (vigueur 80) | Durée |
|---|---|
| 80 % de santé, fatigue 10 | ≈ 3 min |
| 100 % de santé, fatigue 95 | ≈ 6 min |
| 15 % de santé, fatigue 30 | ≈ 12 min 30 |
| 0 PV, fatigue 100 | 15 min (plafond) |

La durée est fixée à l'arrivée au QG et répartie entre la santé et la fatigue. Les cibles sont
toujours **PV max et fatigue 0** : c'est la règle de sortie (voir [infirmerie.md](infirmerie.md)).

```
poids santé   = (100 − santé %) / 100 × 0,60 × admission     (0 si la santé est déjà maximale)
poids fatigue = fatigue / 100 × 0,40                         (0 si la fatigue est déjà nulle)
durée santé   = durée × poids santé   / max(poids santé, poids fatigue)
durée fatigue = durée × poids fatigue / max(poids santé, poids fatigue)
sortie prévue = arrivée + la plus longue des deux (= durée)
```

Chaque composante progresse linéairement à son rythme, enregistré dans les colonnes `taux_*`
existantes, et s'arrête à sa cible :

```
santé   = min(PV max, santé enregistrée   + rythme santé   × (maintenant − dernier calcul))
fatigue = max(0,      fatigue enregistrée − rythme fatigue × (maintenant − dernier calcul))
```

- Si la santé est au maximum avant la fatigue, la santé s'arrête et la fatigue continue de
  baisser. Si la fatigue est nulle avant la santé, c'est l'inverse.
- Exemple : santé restante 2 min et fatigue restante 8 min donnent 8 min avant la sortie.
- Le rythme effectif n'est jamais inférieur à celui qui atteint la cible à la sortie prévue.
- La santé ne baisse jamais et la fatigue n'augmente jamais ; les PV restent dans [0, max] et la
  fatigue dans [0, 100].
- À la date prévue, les cibles sont appliquées exactement et le séjour passe « rétabli ».

## Source de vérité et mises à jour

- Les dates enregistrées côté serveur font foi ; positions, santé et fatigue affichées entre deux
  passages sont des projections pures de ces dates (aucune écriture à la lecture).
- `app:missions:update` (toutes les 3 s en `--watch`) acte les arrivées à l'heure prévue ;
  il exécute aussi `app:infirmary:update`, qui acte les arrivées au QG et les rétablissements.
  Les deux sont idempotents, verrouillés, et rattrapent une interruption au passage suivant.
- Le flux SSE existant (3 s, repli `fetch`) diffuse les secondes restantes et les heures prévues ;
  la carte, la fiche mission et l'infirmerie les affichent au format `MM:SS` (« Arrivé »,
  « Rétabli » à l'échéance). Le navigateur ne décide jamais d'une arrivée ni d'un rétablissement.

## Données existantes

```bash
php bin/console app:game-time:normalize --dry-run   # aperçu, aucune écriture
php bin/console app:game-time:normalize             # application
```

- trajets et retours en cours : progression acquise conservée, nouvelle durée recalculée,
  arrivée avancée (jamais retardée) ;
- soins en cours : valeurs acquises conservées, temps restant = (1 − part acquise) × nouvelle durée ;
- échéance déjà dépassée : opération clôturée immédiatement (arrivée, rétablissement) ;
- rien n'est supprimé ; une seconde exécution ne change rien. Les écritures se font sous les
  verrous des workers (elles attendent la fin du passage en cours).
