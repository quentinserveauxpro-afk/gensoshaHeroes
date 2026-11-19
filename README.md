# Genosha Heroes — Système de commandement

Application Symfony 8.1 / PHP 8.5 / Doctrine ORM 3 / MySQL 8 de gestion de missions de super-héros.

## Installation

```bash
composer install
php bin/console doctrine:migrations:migrate -n
php bin/console doctrine:fixtures:load -n        # données de démonstration
symfony serve   # ou : php -S 127.0.0.1:8000 -t public
```

Comptes de démonstration : `admin@genosha.test` / `admin` (ROLE_ADMIN), `operateur@genosha.test` / `operateur` (ROLE_OPERATOR).

## Tests

```bash
# DATABASE_URL doit être défini dans .env.test.local (non versionné)
php bin/console doctrine:database:create --env=test --if-not-exists
php bin/console doctrine:migrations:migrate --env=test -n
php bin/phpunit
```

## Outils

- `php bin/console app:alertes:simuler --nombre=3` : requêtes citoyennes aléatoires (environnement **dev** uniquement).
- Formule du taux de réussite : voir le docblock de `src/Service/Mission/MissionSuccessCalculator.php`.
- Carte : grille abstraite 100 × 100 km (`src/Service/Map/TacticalGrid.php`), rendue en SVG ; les positions
  sont des coordonnées cartésiennes, convertibles plus tard en lat/lng pour une migration vers Leaflet.

## Limite connue

`Mission::$heroes` (code existant) déclare `mappedBy: 'ManyToOne'`, champ inexistant :
`doctrine:schema:validate` signale ce mapping. Correctif proposé (non appliqué, code existant protégé) :
`mappedBy: 'missions'`. Les affectations passent par l'entité `AffectationMission`.
