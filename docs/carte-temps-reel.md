# Carte réelle, temps réel, demandes publiques et rôles opérateurs

Ce document décrit les évolutions ajoutées sans modifier aucun élément existant des entités
(attributs, types, relations, méthodes, validations, enums) : uniquement des ajouts.

## 1. Carte réelle

- **Leaflet 1.9.4** (importmap) et tuiles **OpenStreetMap** (`MAP_TILE_URL`, `MAP_TILE_ATTRIBUTION`).
  L'attribution est toujours visible. Pour un usage intensif en production, utilisez un fournisseur
  de tuiles dédié (politique d'usage de l'OSMF).
- Aucune borne artificielle : la carte s'ouvre sur la métropole, avec des raccourcis vers la
  Guadeloupe, la Martinique, la Guyane, La Réunion et Mayotte.
- Les coordonnées sont stockées dans l'entité `GeoPosition` en `DECIMAL(10,7)`. Doctrine les renvoie
  sous forme de chaînes, converties par l'objet valeur `App\Geo\Coordonnees`.
- `positionX` et `positionY` (grille abstraite en km) sont inchangés. La grille SVG historique reste
  disponible, repliée, sous la carte du centre tactique.
- **Géocodage** : `GeocoderInterface`, avec deux implémentations.
  - `NominatimGeocoder` en dev et prod : 1 requête par seconde au plus (rate limiter), pays FR et
    DOM, User-Agent configurable via `GEOCODER_USER_AGENT`. Renseignez-y un contact avant toute mise
    en ligne. Les adresses ne sont jamais journalisées.
  - `ReferentielGeocoder` en test : référentiel hors ligne.
  - Le décorateur `CachingGeocoder` met les résultats en cache : 30 jours pour une adresse trouvée,
    1 jour pour une adresse introuvable.
- **Jamais de position aléatoire.** Une adresse introuvable laisse la requête ou la mission « à
  géolocaliser ». L'opérateur peut alors corriger la position : fiche requête → qualification, ou
  fiche mission → « Position réelle de la mission ».

## 2. Déplacements des héros

- Entité `HeroMovement`. Statuts : `EN_ATTENTE`, `EN_DEPLACEMENT`, `ARRIVE`, `INTERROMPU`.
- Le déploiement depuis le Squad Builder fait partir immédiatement les héros vers la position de la
  mission. Le point de départ est leur dernière position connue, ou le QG (Paris) à défaut.
- Calculs :
  - `DistanceCalculator` : formule de Haversine, interpolation sur le grand cercle ;
  - `EstimatedArrivalCalculator` : vitesse = (60 + 2,4 × mobilité) km/h, réduite jusqu'à 30 % par la
    fatigue ; accélération de la simulation via `SIMULATION_FACTEUR_TEMPS`.
- **Le trajet est une simulation en ligne droite, pas un itinéraire routier.**
- La position est une fonction pure du temps : un rechargement de page reprend exactement au bon
  endroit. Seul le moteur acte l'arrivée : il enregistre l'heure réelle, passe l'affectation
  `SUR_PLACE` et place le héros sur la mission. Le navigateur ne décide jamais d'une arrivée.

## 3. Moteur de simulation

```bash
php bin/console app:missions:update                       # un passage (cron, CI)
php bin/console app:missions:update --watch               # worker, toutes les 3 s
php bin/console app:missions:update --watch --intervalle=2 --iterations=100 -v
```

Un passage peut aussi être déclenché par Messenger : `MettreAJourMissions`.

Pour chaque mission `EN_COURS`, sous le verrou `mission-simulation-{id}` (symfony/lock) :

1. démarrage des déplacements manquants, puis avancement et détection des arrivées ;
2. **approche** tant qu'aucun héros n'est sur place ; **engagement** dès la première arrivée ;
3. étapes de 5 s simulées, calculées seulement pour les héros arrivés et en vie :
   - puissance d'équipe : stats effectives, bonus, synergies et niveau (`TeamPowerCalculator`) ;
   - menace : vilains, urgence et difficulté, en baisse avec la progression (`VillainThreatCalculator`) ;
   - progression des objectifs, bornée de 0 à 100 (`ObjectiveProgressCalculator`). Un objectif est
     bloqué si sa compétence requise manque. Une compétence obligatoire de la mission manquante
     divise le gain par 10 ;
   - dégâts : vigueur, combat, menace et aléa injecté (`HeroHealthCalculator`). Les PV sont bornés.
     Un héros à 0 PV ne contribue plus. Sous 25 %, une alerte critique est émise, une seule fois par
     héros ;
   - taux de réussite en direct (`LiveSuccessRateCalculator`) ;
4. fin de mission, via `MissionLifecycleService::cloturer` :
   - succès quand tous les objectifs obligatoires sont atteints ;
   - échec si l'échéance est dépassée ou si plus aucun héros ne peut agir ni n'est en route.

**Idempotence.** L'étape *n* a toujours lieu à l'instant `engagement + n × pas`. Seules les étapes
dues et non encore faites sont calculées : une relance immédiate ne fait rien. L'aléa est dérivé
d'une graine (mission, étape, héros) : `RandomGeneratorInterface`, remplaçable en test par
`FixedRandomGenerator`.

Une mission sans objectif reçoit l'objectif obligatoire « Neutraliser la menace et sécuriser la
zone ». La progression décimale est conservée dans `MissionSimulationState` : la colonne historique
`ObjectifMission::progression` est un entier.

## 4. Temps réel

Mercure n'est pas installé : le transport retenu est **SSE**, avec repli automatique en requêtes
périodiques.

| Point d'accès | Rôle |
|---|---|
| `GET /api/temps-reel/flux` | Server-Sent Events : un événement `etat` toutes les 3 s pendant `REALTIME_SSE_DUREE` secondes (30 par défaut), puis reconnexion automatique (`retry: 3000`). La session est libérée avant le flux. |
| `GET /api/temps-reel/etat` | Même instantané en JSON : repli (polling toutes les 2 à 5 s) et état initial |

- Une seule connexion par page, avec un instantané agrégé : jamais une requête par héros.
- Le polling est suspendu quand l'onglet est masqué.
- `REALTIME_TRANSPORT=polling` force le polling.
- Chaque requête SSE occupe un worker PHP pendant sa durée. Dimensionnez PHP-FPM en conséquence, ou
  réduisez `REALTIME_SSE_DUREE`.
- Éléments mis à jour sans rechargement : marqueurs, positions, trajets, distances, ETA, jauges de
  santé, objectifs, taux, phase, menace, journal et annonces d'arrivée (zone `aria-live`). Une
  version texte de la carte est fournie.

## 5. Demande d'intervention publique

`/demande-intervention` (sans compte) recueille : titre, description, 8 catégories, urgence
estimée, adresse, complément, code postal, ville, nom (facultatif), e-mail ou téléphone, consentement
et pièce jointe (facultative).

**Sécurité**
- jeton CSRF ;
- limitation à 5 envois par adresse IP (hachée) sur 15 minutes, au-delà réponse 429 ;
- champ « pot de miel » et jeton horodaté signé (`ANTIBOT_DELAI_MIN`) ;
- validation serveur complète ;
- pièce jointe : liste blanche JPEG, PNG, WebP et PDF, 5 Mo au plus, stockée sous
  `var/uploads/requetes` avec un nom aléatoire, téléchargeable uniquement par les opérateurs (en
  pièce jointe, `nosniff`) ;
- confirmation neutre ;
- aucune donnée personnelle dans les journaux ni dans les alertes.

**À l'envoi**
- une référence est attribuée et le statut passe à `NOUVELLE` ;
- l'urgence déclarée est conservée à part : l'urgence officielle reste à qualifier ;
- le secteur est déduit du code postal ;
- l'adresse est géocodée ;
- une alerte prévient les opérateurs missions ;
- **aucune mission n'est créée**.

## 6. Traitement par les opérateurs missions

Statuts : `NOUVELLE`, `EN_ANALYSE`, `INFORMATIONS_REQUISES`, `VALIDEE`, `CONVERTIE_EN_MISSION`,
`REJETEE` (motif obligatoire), `CLASSEE_SANS_SUITE`. Le statut historique `RequeteStatut` est
synchronisé automatiquement.

- Filtres de la liste : urgence, type, secteur, statut.
- La fiche `/requetes/{id}` permet l'analyse, la qualification (urgence interne, catégorie,
  secteur, informations, correction de position, re-géocodage), le rejet, le classement et la
  conversion.
- Chaque action enregistre l'opérateur et la date dans `RequeteHistorique`.
- La conversion s'exécute dans une transaction Doctrine : la mission est créée, la position copiée,
  le lien conservé et l'opérateur enregistré, puis l'opérateur est redirigé vers la mission.

## 7. Rôles

| Rôle | Droits |
|---|---|
| `ROLE_MISSION_OPERATOR` | Requêtes citoyennes (consultation et traitement), création, modification, publication, clôture et annulation des missions, position, objectifs. **Ne peut pas déployer.** |
| `ROLE_HERO_OPERATOR` | Squad Builder, déploiement, lancement des missions, suivi des héros. **Ne traite pas les requêtes.** |
| `ROLE_ADMIN` | Tous les droits, dont la gestion des comptes (création, type de compte choisi dans une liste contrôlée côté serveur, activation et désactivation, réinitialisation du mot de passe). |
| `ROLE_OPERATOR` | Rôle historique conservé avec ses droits antérieurs (hérite de `ROLE_HERO_OPERATOR` ; consulte et classe les requêtes). |

`ROLE_STAFF` est un socle commun interne : centre tactique, missions et héros en lecture.

Les contrôles sont faits côté serveur :
- `access_control` ;
- `#[IsGranted]` ;
- les voters `RequeteCitoyenneVoter` (`REQUETE_CONSULTER`, `REQUETE_TRAITER`, `REQUETE_CLASSER`,
  `REQUETE_CONVERTIR`) et `MissionVoter` (`MISSION_GERER`, `MISSION_DEPLOYER`).

Twig ne fait que masquer les boutons. Un compte désactivé ne peut plus se connecter (`UserChecker`)
et sa session en cours est fermée (`CompteDesactiveListener`). Un administrateur ne peut ni se
désactiver lui-même ni se retirer son propre rôle d'administrateur.

## 8. Variables d'environnement ajoutées

`MAP_TILE_URL`, `MAP_TILE_ATTRIBUTION`, `GEOCODER_URL`, `GEOCODER_USER_AGENT`,
`SIMULATION_FACTEUR_TEMPS`, `REALTIME_TRANSPORT`, `REALTIME_SSE_DUREE`, `ANTIBOT_DELAI_MIN`,
`LOCK_DSN`.

## 9. Limites connues

- Le trajet suit une ligne droite simulée : ni routes, ni trafic, ni traversée maritime réaliste
  vers l'outre-mer.
- Le moteur doit tourner en continu (`--watch`, Supervisor ou systemd). Sans lui, les positions
  restent calculées à l'affichage, mais aucune arrivée ni aucun combat ne progresse.
- SSE immobilise un processus PHP par onglet ouvert pendant `REALTIME_SSE_DUREE`.
- Nominatim est un service public à faible débit : pour un volume important, utilisez une instance
  dédiée.
- Aucun e-mail n'est envoyé aux opérateurs : la notification passe par les alertes de
  l'application.
- Anomalie d'origine, non corrigée car il faudrait modifier une relation existante :
  `Mission::$heroes` (`mappedBy: 'ManyToOne'`) fait échouer la partie mapping de
  `doctrine:schema:validate`. Le schéma de la base est, lui, synchronisé.
