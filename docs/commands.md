# Commandes

Utilisez `php bin/console …`, ou `symfony console …` si la Symfony CLI est installée.

Les commandes marquées ⚠️ **modifient ou suppriment des données**.

## Commande personnalisée

| Commande | Environnement | Description |
|---|---|---|
| ⚠️ `php bin/console app:alertes:simuler [--nombre=3]` | `dev` uniquement (`#[When(env: 'dev')]`) | Crée N requêtes citoyennes aléatoires via `RequeteCitoyenneService` ; les requêtes urgentes génèrent des alertes persistées |

C'est la seule commande définie dans `src/Command/`.

## Doctrine

| Commande | Effet |
|---|---|
| `php bin/console doctrine:database:create --if-not-exists` | Crée la base indiquée par `DATABASE_URL` |
| ⚠️ `php bin/console doctrine:migrations:migrate -n` | Applique les migrations |
| `php bin/console doctrine:migrations:status` | État des migrations (lecture seule) |
| `php bin/console make:migration` | Génère une migration à partir du mapping (maker-bundle, `dev`) |
| ⚠️ `php bin/console doctrine:fixtures:load -n` | **Purge** les tables mappées, puis charge `AppFixtures` (`dev` et `test`) |
| `php bin/console doctrine:schema:validate` | Vérifie le mapping et la synchronisation du schéma. *Mapping* en `[FAIL]` attendu, voir [domain-model.md](domain-model.md#anomalie-de-mapping-connue) |
| `php bin/console doctrine:mapping:info` | Liste les 12 entités mappées |
| `php bin/console dbal:run-sql "SELECT …"` | Requête SQL ponctuelle (réservez-la aux lectures) |

N'utilisez pas `doctrine:schema:update --force` : les changements de schéma passent par les migrations.

## Tests

| Commande | Effet |
|---|---|
| `php bin/console doctrine:database:create --env=test --if-not-exists` | Crée la base de test (suffixe `_test`) |
| ⚠️ `php bin/console doctrine:migrations:migrate --env=test -n` | Crée le schéma de test |
| `php bin/phpunit` | Lance la suite complète ; **vide la base de test** à chaque test |
| `php bin/phpunit tests/Unit` | Tests unitaires seulement (sans base de données) |
| `php bin/phpunit --filter NomDuTest` | Un test précis |

## Validation et diagnostic

| Commande | Effet |
|---|---|
| `php bin/console lint:twig templates` | Syntaxe des templates (35 fichiers, OK) |
| `php bin/console lint:yaml config` | Syntaxe YAML (24 fichiers, OK) |
| `php bin/console lint:container` | Cohérence des types injectés (OK) |
| `composer validate` | Validité de `composer.json`. Signale l'absence de `name` et `description` : c'est sans incidence pour un projet, mais bloquant pour publier un paquet |
| `php bin/console about` | Versions et environnement |
| `php bin/console debug:router [--show-controllers]` | Liste des routes |
| `php bin/console debug:container <service>` | Définition d'un service |
| `php bin/console debug:config <bundle>` | Configuration résolue d'un bundle |
| `php bin/console cache:clear` | Vide le cache |

## Assets

| Commande | Effet |
|---|---|
| `php bin/console importmap:install` | Télécharge les paquets de `importmap.php` dans `assets/vendor/` |
| `php bin/console importmap:require <paquet>` | Ajoute une dépendance JavaScript |
| `php bin/console importmap:outdated` | Liste les paquets JavaScript obsolètes |
| `php bin/console debug:asset-map` | Liste les assets servis |
| `php bin/console asset-map:compile` | Compile les assets dans `public/assets/` (production) |

Aucune commande npm ou Webpack Encore : le projet n'utilise pas Node.js.

## Serveur

| Commande | Effet |
|---|---|
| `symfony serve -d` | Serveur local de la Symfony CLI, en arrière-plan |
| `symfony server:stop` | Arrête ce serveur |
| `php -S 127.0.0.1:8000 -t public` | Alternative sans Symfony CLI |
