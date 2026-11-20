# Genosha Heroes — Système de commandement

Application web interne de l'entreprise Genosha Heroes, qui envoie des super-héros en mission
contre le crime. Elle permet de traiter les requêtes des citoyens, de transformer une requête en
mission, de constituer une équipe adaptée à chaque mission (Squad Builder) et de suivre les
missions en cours sur une carte tactique.

Rendu côté serveur (Symfony + Twig), sans API publique.

## Sommaire

1. [Fonctionnalités principales](#fonctionnalités-principales)
2. [Prérequis techniques](#prérequis-techniques)
3. [Installation rapide](#installation-rapide)
4. [Base de données](#base-de-données)
5. [Lancement](#lancement)
6. [Données de démonstration](#données-de-démonstration)
7. [Tests](#tests)
8. [Documentation](#documentation)
9. [État d'avancement](#état-davancement)

## Fonctionnalités principales

| Domaine | Ce qui fonctionne aujourd'hui |
|---|---|
| Centre tactique | Carte réelle de France (Leaflet + OpenStreetMap, outre-mer inclus) mise à jour en temps réel : héros en déplacement, missions, vilains, trajets, santé, ETA ; grille SVG historique conservée en vue secondaire |
| Moteur temps réel | Commande `app:missions:update` : déplacements, arrivées, combat, objectifs, taux de réussite en direct, fin de mission (idempotente, verrous par mission) |
| Demande publique | Formulaire `/demande-intervention` sans compte (CSRF, limitation de débit, anti-robot, pièce jointe sécurisée) |
| Missions | Liste paginée, fiche, création et édition (admin), objectifs avec progression, cycle de vie (publier, lancer, clôturer, annuler), compte à rebours |
| Requêtes citoyennes | Saisie par un opérateur, filtres (urgence, type, secteur, statut), fiche détaillée, analyse, qualification, rejet motivé, classement, conversion transactionnelle en mission géolocalisée, historique |
| Roster des héros | Liste paginée avec recherche, filtre par rôle et tri, fiche détaillée avec radar Chart.js, bonus actifs, historique des missions, synergies |
| Squad Builder | Composition d'équipe, pronostic de réussite recalculé à chaque modification, suggestions classées, validation serveur, déploiement transactionnel |
| Administration | Comptes opérateurs typés (missions / héros / admin), activation, réinitialisation du mot de passe ; vilains |
| Alertes | Générées automatiquement pour les requêtes urgentes et les missions critiques, acquittement |

Le détail, avec l'état de chaque fonctionnalité, est dans [docs/features.md](docs/features.md).

## Prérequis techniques

| Élément | Version vérifiée |
|---|---|
| PHP | 8.5.12 (`composer.json` exige `>=8.4`) |
| Symfony | 8.1.11 |
| Doctrine ORM | 3.8.0 |
| Base de données | MySQL (serveur local 9.7.4) |
| Composer | 2.x |

Il n'y a ni Node.js ni étape de compilation des assets : le projet utilise AssetMapper et un importmap.

## Installation rapide

```bash
composer install
# Créer .env.local avec DATABASE_URL (voir docs/installation.md)
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate -n
php bin/console doctrine:fixtures:load -n
symfony serve
```

La procédure complète et les problèmes fréquents sont décrits dans [docs/installation.md](docs/installation.md).

## Base de données

La base utilisée est MySQL. `DATABASE_URL` n'est défini que dans `.env.local` (non versionné) :

```dotenv
DATABASE_URL="mysql://UTILISATEUR:MOT_DE_PASSE@127.0.0.1:3306/NOM_BASE?serverVersion=8.0.32&charset=utf8mb4"
```

Le fichier `compose.yaml` (PostgreSQL), généré par la recette Doctrine, **n'est pas utilisé** par le projet.

## Lancement

```bash
symfony serve            # ou : php -S 127.0.0.1:8000 -t public
```

Toutes les pages, sauf `/login` et `/demande-intervention`, exigent une connexion.

Pour que les héros se déplacent et que les missions évoluent, lancez le moteur dans un second terminal :

```bash
php bin/console app:missions:update --watch      # un passage toutes les 3 s (Ctrl+C pour arrêter)
```

## Données de démonstration

`php bin/console doctrine:fixtures:load -n` **vide la base**, puis charge 14 héros, 10 missions,
11 requêtes, 6 vilains, 6 synergies, 6 bonus, 7 aptitudes, 3 alertes et 4 comptes. Héros et
missions reçoivent des coordonnées réelles (villes de France métropolitaine, Guadeloupe, La Réunion) :

| Compte | Mot de passe | Rôle |
|---|---|---|
| `admin@genosha.test` | `admin` | `ROLE_ADMIN` |
| `operateur@genosha.test` | `operateur` | `ROLE_OPERATOR` (opérateur polyvalent historique) |
| `missions@genosha.test` | `missions-genosha` | `ROLE_MISSION_OPERATOR` |
| `heros@genosha.test` | `heros-genosha` | `ROLE_HERO_OPERATOR` |

Ces identifiants sont réservés à la démonstration.

## Tests

```bash
php bin/phpunit
```

Il y a 158 tests (unitaires, d'intégration et fonctionnels) et ils passent tous. Ils exigent une base
MySQL de test, décrite dans [docs/testing.md](docs/testing.md).

## Documentation

| Document | Contenu |
|---|---|
| [installation.md](docs/installation.md) | Prérequis, configuration, installation, dépannage |
| [architecture.md](docs/architecture.md) | Organisation du code, couches, diagramme |
| [domain-model.md](docs/domain-model.md) | Entités Doctrine, relations, enums |
| [features.md](docs/features.md) | Fonctionnalités et leur état réel |
| [user-guide.md](docs/user-guide.md) | Parcours utilisateur |
| [design-system.md](docs/design-system.md) | Couleurs, typographie, composants, accessibilité |
| [security.md](docs/security.md) | Authentification, rôles, CSRF, secrets |
| [testing.md](docs/testing.md) | Organisation et exécution des tests |
| [api.md](docs/api.md) | Point d'accès JSON interne |
| [routes.md](docs/routes.md) | Table des routes |
| [commands.md](docs/commands.md) | Commandes utiles |
| [contributing.md](docs/contributing.md) | Règles de contribution, Conventional Commits |
| [carte-temps-reel.md](docs/carte-temps-reel.md) | Carte réelle, déplacements, moteur de simulation, temps réel, demandes publiques, rôles opérateurs |

## État d'avancement

- **Opérationnel** : le parcours complet requête → mission → équipe → déploiement → clôture.
- **Partiel** : la progression des héros (XP gagnée seulement à la clôture), les bonus et les
  synergies (gérés uniquement par les fixtures, sans écran d'administration), le lancement manuel
  d'une mission.
- **Absent** : la récupération de santé ou de fatigue, la suppression des missions, vilains et
  utilisateurs, les calques « Satellite » et « Thermique » de la maquette, la télémétrie biométrique.
- **Anomalie connue** : le mapping `Mission::$heroes` est invalide (`mappedBy: 'ManyToOne'`).
  `doctrine:schema:validate` le signale. Ce code est d'origine et non corrigé ; les affectations
  passent par `AffectationMission`. Voir [docs/domain-model.md](docs/domain-model.md#anomalie-de-mapping-connue).
