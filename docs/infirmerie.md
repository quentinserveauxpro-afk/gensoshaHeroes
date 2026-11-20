# Infirmerie

Rapatriement automatique ou manuel des héros, retour au QG, récupération en temps réel et
indisponibilité médicale dans le Squad Builder. Toutes les données médicales sont **fictives**
et décrivent des personnages.

## Cycle d'un séjour

```
          admission (auto / manuelle / après mission)
                         │
   position connue hors QG ──► RETOUR_QG ──(arrivée à l'heure prévue)──┐
   déjà au QG / position inconnue ─────────────────────────────────────┤
                                                                       ▼
                                                               EN_TRAITEMENT
                                                                       │ santé ≥ cible et fatigue ≤ cible
                                                                       ▼
                                                                   RETABLI
                                    sortie automatique (config) ou │ validation opérateur héros
                                                                       ▼
                                                                    SORTI
```

`RETOUR_EN_ATTENTE` et `ANNULE` sont prévus dans l'enum `StatutSejour` (préparation du retour,
annulation administrative) ; `SejourInfirmerie::annuler()` existe, aucune interface ne l'expose.

Un héros n'a jamais plus d'un séjour actif : contrôle applicatif (`HeroRepatriationService`,
y compris pour un séjour créé dans le même passage et pas encore écrit), puis index unique en
base sur `sejour_infirmerie.cle_active` (identifiant du héros tant que le séjour est actif,
`NULL` ensuite). L'historique n'est jamais supprimé.

## Déclencheurs du rapatriement (serveur uniquement)

| Moment | Où |
|---|---|
| Après les dégâts et la progression de chaque étape de combat | `MissionSimulationService::evaluerMedicalement()` |
| Fin de mission (fatigue + 15 de la clôture) | `MissionSimulationService::terminer()` → admission `POST_MISSION` |
| Passage périodique (tous les héros, y compris en route ou au repos) | `InfirmaryUpdateRunner` |
| Décision d'un opérateur héros | fiche héros → `POST /heros/{id}/envoyer-infirmerie` |

Conséquences d'une admission (`HeroRepatriationService::admettre()`) :

1. séjour créé avec le motif et l'état initial (santé, fatigue) ;
2. affectation active → `RETIRE` (conservée), contribution figée à partir des contributions du moteur ;
3. le héros ne fait plus partie de `herosOperationnels()` : il ne contribue plus aux objectifs ;
4. déplacement vers la mission interrompu, retour vers le QG démarré (position = fonction pure du temps) ;
5. statut héros existant `BLESSE` (ou `REPOS` pour l'épuisement) : non déployable ;
6. taux de réussite recalculé, aptitudes obligatoires désormais manquantes journalisées et signalées ;
7. alertes « Santé critique » / « Épuisement critique », « Rapatriement déclenché », « Compétence manquante ».

## Seuils et règles (variables d'environnement)

| Variable | Défaut | Rôle |
|---|---|---|
| `INFIRMERIE_SANTE_CRITIQUE` | 20 | santé (%) ≤ seuil → rapatriement |
| `INFIRMERIE_FATIGUE_CRITIQUE` | 90 | fatigue ≥ seuil → rapatriement |
| `INFIRMERIE_SANTE_SORTIE` | 100 | santé (%) à atteindre |
| `INFIRMERIE_FATIGUE_SORTIE` | 20 | fatigue maximale de sortie |
| `INFIRMERIE_SANTE_POINTS` / `INFIRMERIE_FATIGUE_POINTS` / `INFIRMERIE_PERIODE_MINUTES` | 2 / 3 / 5 | 2 PV et −3 fatigue toutes les 5 minutes (avant modificateurs) |
| `INFIRMERIE_FACTEUR_TEMPS` | 1 | accélération (60 : une minute réelle vaut une heure) |
| `INFIRMERIE_BONUS_SOUTIEN` | 0.5 | +50 % si un héros de soutien est disponible au QG |
| `INFIRMERIE_APTITUDE_SOUTIEN` | `SUPPORT_MEDICAL` | code d'aptitude du soutien (jamais un nom de héros) |
| `INFIRMERIE_CAPACITE` | 6 | lits ; au-delà, récupération ralentie |
| `INFIRMERIE_SORTIE_AUTOMATIQUE` | 0 | 1 : sortie automatique ; 0 : validation par un opérateur héros |
| `INFIRMERIE_FATIGUE_PAR_ETAPE` | 0.1 | fatigue gagnée par étape de combat de 5 s simulées |
| `QG_LATITUDE` / `QG_LONGITUDE` / `QG_NOM` | 48.8566 / 2.3522 / QG Genosha — Paris | destination des rapatriements (source unique, aussi utilisée par la carte et les déplacements) |

Santé (%) = `pointsDeVie / pointsDeVieMaximum × 100`, bornée à [0, 100] ; maximum nul ou valeurs
manquantes → 0 %. La santé reste dans [0, PV max], la fatigue dans [0, 100].

Taux de récupération (par minute simulée) :

```
santé   = SANTE_POINTS / PERIODE × vigueur × niveau × gravité × soutien × capacité
fatigue = FATIGUE_POINTS / PERIODE × vigueur × soutien × capacité
  vigueur  = 0,75 + 0,5 × vigueur effective / 100   (bonus actifs inclus)
  niveau   = 1 + min(niveau, 100) / 200
  gravité  = 0,6 si admis à 0 PV ; 0,8 si santé critique ou blessure ; 1 sinon
  soutien  = 1 + BONUS si un autre héros possédant l'aptitude de soutien est disponible au QG
             (à moins de 1 km du QG, ou sans position) ; 1 + BONUS / 2 si seul le patient la possède
  capacité = min(1, max(0,5, lits / patients))
```

Aucune récupération pendant le retour. Le calcul porte sur l'intervalle
`[dernierCalculLe, maintenant]` (à la seconde entière) : relancer ne change rien, un arrêt du
serveur est rattrapé au passage suivant, l'arrivée et le rétablissement sont datés exactement.

## Commande périodique

```bash
php bin/console app:infirmary:update              # un passage (cron, CI)
php bin/console app:infirmary:update --watch      # worker dédié, toutes les 5 s
php bin/console app:missions:update --watch       # le worker des missions exécute aussi l'infirmerie
php bin/console app:missions:update --watch --sans-infirmerie
```

- **cron** : `* * * * * php /chemin/bin/console app:infirmary:update` ;
- **Messenger** : le message existant `MettreAJourMissions` exécute aussi l'infirmerie ;
- **Symfony Scheduler** : non installé dans le projet (aucune dépendance ajoutée).

Verrou global `app-infirmary-update` ; un héros engagé sur une mission n'est traité que si le
verrou `mission-simulation-{id}` est libre (pas de concurrence avec le moteur).

## Temps réel

Pas de Mercure dans le projet : le flux SSE existant (`/api/temps-reel/flux`, repli
`/api/temps-reel/etat`) contient une clé `infirmerie` (indicateurs, séjours) et, pour chaque héros,
son état d'infirmerie et son trajet de retour (tracé sur la carte jusqu'au QG). La page Infirmerie
écoute l'événement `genosha:etat` de l'abonnement unique de la page (`infirmary_live_controller`) :
aucune boucle par héros. Entre deux passages du worker, santé et fatigue sont projetées à l'instant
de la requête avec les taux enregistrés, sans jamais dépasser les cibles ; les transitions sont
actées uniquement par le worker. Une admission ou une sortie affiche un message proposant
d'actualiser (pas de rechargement automatique).

## Droits

| Action | Opérateur héros | Opérateur missions | Admin |
|---|---|---|---|
| Page `/infirmerie`, historique, détail temps réel | oui | non | oui |
| Envoyer à l'infirmerie (`INFIRMERIE_ADMETTRE`) | oui | non (403) | oui |
| Valider une sortie (`INFIRMERIE_SORTIE`) | oui | non (403) | oui |
| État général d'un héros (fiche, statut, rapatriement sur la carte), alertes | oui | oui | oui |

`ROLE_OPERATOR` (historique) hérite de `ROLE_HERO_OPERATOR`. Droits vérifiés par `InfirmerieVoter`
dans le contrôleur ; actions en POST avec jeton CSRF.

## Squad Builder

`DisponibiliteMedicale` rend un héros inéligible (motif affiché) s'il a un séjour actif
(« Retour vers le QG », « Traitement médical », « Rétabli, sortie à valider ») ou si sa santé ou sa
fatigue est critique. Un héros en séjour présent dans l'URL est retiré de la composition (pas de
synergie ni de pronostic). `DeploymentValidator` revalide au déploiement, même si le statut du
héros a été remis à « disponible » à la main.

## Modèle de données (ajouts)

- entité `SejourInfirmerie` (table `sejour_infirmerie`) et enums `TypeAdmission`, `MotifInfirmerie`, `StatutSejour` ;
- colonne JSON `mission_simulation_state.fatigues` (fatigue décimale pendant le combat) ;
- migration `Version20261120062618`.

Le trajet de retour est porté par le séjour : `HeroMovement` exige une mission non nulle et
cette relation existante n'est pas modifiée.
