---
name: symfony-doctrine-orm
description: gérer une base de données relationnelle dans un projet symfony avec doctrine orm à partir d'entités php existantes et, si disponible, d'un schéma sql. utiliser pour analyser le mapping, créer ou corriger les migrations, produire des fixtures cohérentes et écrire les tests associés sans modifier inutilement le domaine métier.
---
# Symfony Doctrine ORM

## Objectif
Produire des migrations, fixtures et tests fiables à partir des entités PHP existantes et du schéma SQL éventuellement fourni.

## Règles
- Travailler uniquement avec Doctrine ORM et les bases relationnelles.
- Inspecter avant toute modification : `composer.json`, `.env`, `config/packages/doctrine.yaml`, `src/Entity/`, `src/Repository/`, migrations existantes et tests.
- Considérer les entités PHP et leurs attributs Doctrine comme la source métier principale. Comparer au schéma SQL lorsqu'il existe et signaler toute divergence.
- Ne jamais inventer une propriété, une relation, une contrainte ou une donnée métier manquante.
- Préserver le style, les namespaces et les conventions du projet.
- Ne jamais placer de secret réel dans `.env`, les migrations, les fixtures ou les tests.
- Ne jamais exécuter une migration destructive sans l'annoncer explicitement.
- Utiliser seulement les commandes réellement disponibles dans le projet.

## Workflow

### 1. Auditer le projet
1. Identifier les versions de PHP, Symfony, Doctrine ORM, DoctrineBundle, MigrationsBundle, FixturesBundle et PHPUnit.
2. Vérifier `DATABASE_URL` et `server_version` sans afficher les identifiants.
3. Examiner les identifiants, types, longueurs, nullabilité, valeurs par défaut, index, contraintes uniques et relations.
4. Si un schéma SQL est fourni, établir la correspondance table/entité et colonne/propriété.
5. Présenter brièvement les incohérences bloquantes avant de générer du code.

### 2. Créer les migrations
- Préférer `php bin/console make:migration` après validation du mapping.
- Vérifier le fichier généré manuellement : sens de `up()`, réversibilité de `down()`, clés étrangères, index, valeurs par défaut et risques de perte de données.
- Utiliser des migrations explicites et limitées au besoin demandé.
- Ne pas modifier une migration déjà exécutée en environnement partagé ; en créer une nouvelle.
- Proposer les commandes :
  - `php bin/console doctrine:schema:validate`
  - `php bin/console doctrine:migrations:diff`
  - `php bin/console doctrine:migrations:migrate --dry-run`
  - `php bin/console doctrine:migrations:migrate`
- Pour une colonne non nullable ajoutée à une table remplie, prévoir une migration en étapes : colonne nullable ou valeur transitoire, remplissage, puis contrainte `NOT NULL`.

### 3. Créer les fixtures
- Utiliser `DoctrineFixturesBundle` si installé ; sinon indiquer `composer require --dev orm-fixtures` avant toute génération.
- Placer les classes dans `src/DataFixtures/`.
- Générer des données déterministes et minimales adaptées aux contraintes réelles.
- Respecter l'ordre des dépendances avec `DependentFixtureInterface`.
- Utiliser des références pour relier les objets entre fixtures.
- Ne jamais utiliser de données personnelles ou de secrets réels.
- Fournir la commande `php bin/console doctrine:fixtures:load` et avertir qu'elle purge normalement la base ciblée.

### 4. Écrire les tests
- Tester au minimum la migration, le chargement des fixtures et les contraintes importantes.
- Utiliser une base dédiée à `APP_ENV=test`, jamais la base de développement ou de production.
- Préparer le schéma de test avec les migrations.
- Pour les repositories, écrire des tests d'intégration avec `KernelTestCase`, récupérer l'`EntityManagerInterface`, persister, `flush()`, puis vérifier la requête.
- Pour les contraintes d'entité, ajouter des tests du Validator Symfony lorsque pertinent.
- Isoler chaque test : transaction avec rollback ou recréation contrôlée de la base selon les outils déjà présents.
- Ne pas remplacer Doctrine par des mocks pour tester une requête DQL/QueryBuilder réelle.

### 5. Vérifier
Exécuter, selon les dépendances disponibles :
1. `php bin/console doctrine:schema:validate`
2. `php bin/console doctrine:migrations:migrate --dry-run --env=test`
3. Migration réelle sur la base de test
4. Chargement des fixtures dans l'environnement de test
5. `php bin/phpunit`

Ne pas affirmer qu'une commande réussit si elle n'a pas été exécutée. En cas d'échec, fournir l'erreur exacte, sa cause probable et la correction minimale.

## Sortie attendue
Fournir :
1. Un résumé très court de l'audit.
2. La liste des fichiers créés ou modifiés.
3. Le contenu complet des migrations, fixtures et tests.
4. Les commandes d'exécution dans l'ordre.
5. Les risques, notamment suppression de données, purge des fixtures et différences de moteur SQL.

## Référence
Consulter `references/checklist.md` pour la checklist de contrôle.
