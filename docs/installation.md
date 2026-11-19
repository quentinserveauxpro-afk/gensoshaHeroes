# Installation

## Sommaire

1. [Prérequis](#prérequis)
2. [Récupération et dépendances](#récupération-et-dépendances)
3. [Variables d'environnement](#variables-denvironnement)
4. [Base de données](#base-de-données)
5. [Fixtures](#fixtures)
6. [Assets](#assets)
7. [Lancement du serveur](#lancement-du-serveur)
8. [Problèmes fréquents](#problèmes-fréquents)

## Prérequis

### Versions

| Outil | Exigé | Vérifié sur le poste de développement |
|---|---|---|
| PHP | `>=8.4` (`composer.json`) | 8.5.12 |
| Symfony | `8.1.*` (`extra.symfony.require`) | 8.1.11 |
| Composer | 2.x | — |
| MySQL | compatible `serverVersion=8.0.32` | serveur 9.7.4 |
| Symfony CLI | optionnel | installé (`/usr/bin/symfony`) |

> Symfony 8.1 n'est pas une version LTS : `php bin/console about` indique une fin de maintenance en janvier 2027.

### Extensions PHP

- **Exigées par `composer.json`** : `ctype`, `iconv`.
- **Requises par l'usage réel** :
  - `pdo_mysql`, pour Doctrine et MySQL ;
  - `intl`, pour `symfony/intl` et la locale `fr_FR` ;
  - `mbstring`, utilisée par `mb_*` dans les entités et l'extension Twig ;
  - `xml`, `tokenizer`, `sodium`, pour le hachage des mots de passe Symfony.
- **Recommandé** : `opcache`.

Pour vérifier : `php -m | grep -iE "pdo_mysql|intl|mbstring|ctype|iconv"`.

## Récupération et dépendances

```bash
git clone <url-du-dépôt> genoshaHeroes
cd genoshaHeroes
composer install
```

`composer install` exécute ensuite automatiquement `cache:clear`, `assets:install public` et
`importmap:install`, qui télécharge Stimulus, Turbo, Chart.js et `@kurkle/color` dans `assets/vendor/`.

## Variables d'environnement

| Fichier | Versionné | Rôle |
|---|---|---|
| `.env` | oui | Valeurs par défaut : `APP_ENV`, `APP_SECRET`, `APP_SHARE_DIR`, `DEFAULT_URI`, `MESSENGER_TRANSPORT_DSN`, `MAILER_DSN` |
| `.env.dev` | oui | `APP_SECRET` de développement |
| `.env.test` | oui | `KERNEL_CLASS`, `APP_SECRET` de test |
| `.env.local` | **non** | Surcharges locales, dont **`DATABASE_URL`** |
| `.env.test.local` | **non** | `DATABASE_URL` pour les tests |

Il n'existe pas de fichier `.env.example`.

`.env` ne définit **aucune** `DATABASE_URL` active : la ligne PostgreSQL de la recette est commentée.
Il faut donc obligatoirement créer `.env.local` :

```dotenv
# .env.local — ne jamais committer
DATABASE_URL="mysql://UTILISATEUR:MOT_DE_PASSE@127.0.0.1:3306/NOM_BASE?serverVersion=8.0.32&charset=utf8mb4"
```

En environnement `test`, Doctrine ajoute le suffixe `_test` au nom de la base
(`config/packages/doctrine.yaml`, `dbname_suffix`).

### Base partagée

La base de développement est **partagée avec un autre projet**. `config/packages/doctrine.yaml`
déclare un `schema_filter` qui exclut les tables `alimentaire`, `animal`, `fashion`, `loisirs`,
`panier` et `produit`. Ainsi, Doctrine ne génère jamais de `DROP TABLE` à leur sujet. Si vous
utilisez une base dédiée, ce filtre reste sans effet.

## Base de données

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate -n
```

Le projet contient une seule migration, `migrations/Version20261119105151.php`. Elle crée 16 tables :
`affectation_mission`, `alerte`, `aptitude`, `bonus`, `hero`, `hero_aptitude`, `hero_bonus`,
`mission`, `mission_aptitude`, `mission_villain`, `objectif_mission`, `requete_citoyenne`,
`synergie`, `synergie_hero`, `user`, `villain`.

> Sur le poste d'origine, `doctrine:migrations:status` signale 2 migrations « exécutées mais
> indisponibles ». Ce sont d'anciennes migrations dont les fichiers ont été supprimés. Cela
> n'affecte pas une installation neuve.

Pour vérifier que le schéma correspond au mapping :

```bash
php bin/console doctrine:schema:validate
```

La section *Database* doit être `[OK]`. La section *Mapping* est en `[FAIL]` à cause d'une
anomalie connue, décrite dans [domain-model.md](domain-model.md#anomalie-de-mapping-connue).

## Fixtures

```bash
php bin/console doctrine:fixtures:load -n
```

> ⚠️ Cette commande **purge toutes les tables mappées** avant de charger les données. Les tables
> exclues par `schema_filter` ne sont pas concernées. Ne l'exécutez jamais sur une base contenant
> des données réelles.

Les fixtures (`src/DataFixtures/AppFixtures.php`) créent 2 utilisateurs, 7 aptitudes, 6 bonus,
14 héros, 6 synergies, 6 vilains, 10 missions (en cours, en attente, brouillon, terminée,
échouée), 11 requêtes citoyennes et 3 alertes. Les comptes de démonstration sont listés dans le
[README](../README.md#données-de-démonstration).

Le bundle de fixtures n'est activé qu'en `dev` et `test` (`config/bundles.php`).

## Assets

Aucune compilation n'est nécessaire : AssetMapper sert `assets/` directement en développement.

```bash
php bin/console importmap:install      # si assets/vendor/ est absent
php bin/console asset-map:compile      # en production uniquement : écrit public/assets/
```

## Lancement du serveur

```bash
symfony serve -d                       # avec la Symfony CLI
# ou
php -S 127.0.0.1:8000 -t public
```

Ouvrez ensuite `http://127.0.0.1:8000/`. Sans session, vous êtes redirigé vers `/login`.

## Problèmes fréquents

| Symptôme | Cause probable | Solution |
|---|---|---|
| `Environment variable not found: "DATABASE_URL"` | `.env.local` absent | Créer `.env.local` avec `DATABASE_URL` |
| `could not find driver` | Extension `pdo_mysql` absente | Installer et activer `pdo_mysql` |
| Pages sans styles ni icônes | Polices Google indisponibles (hors ligne) | Les icônes Material Symbols et les polices viennent de `fonts.googleapis.com`. La mise en page reste lisible, mais les icônes ne s'affichent pas |
| Erreur JS « Failed to resolve module specifier » | `assets/vendor/` vide | `php bin/console importmap:install` |
| `doctrine:schema:validate` en `[FAIL]` | Anomalie de mapping connue sur `Mission::$heroes` | Attendu. Ne pas « corriger » via `schema:update` |
| Proposition de `DROP TABLE` d'une table étrangère | `schema_filter` modifié ou supprimé | Restaurer le filtre dans `doctrine.yaml` |
| Tests en erreur de connexion | `.env.test.local` absent ou base de test non créée | Voir [testing.md](testing.md#environnement-de-test) |
| Accès refusé (403) sur `/missions/nouvelle` | Compte opérateur | Se connecter avec un compte `ROLE_ADMIN` |
