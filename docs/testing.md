# Tests

## Sommaire

1. [Outils](#outils)
2. [Organisation](#organisation)
3. [Environnement de test](#environnement-de-test)
4. [Commandes](#commandes)
5. [Tests unitaires](#tests-unitaires)
6. [Tests d'intégration](#tests-dintégration)
7. [Tests fonctionnels](#tests-fonctionnels)
8. [Données de test](#données-de-test)
9. [Écrire un nouveau test](#écrire-un-nouveau-test)
10. [Parties non couvertes](#parties-non-couvertes)

## Outils

| Outil | Version | Rôle |
|---|---|---|
| PHPUnit | 13.4.7 | Exécution des tests (`bin/phpunit`) |
| `symfony/browser-kit`, `symfony/css-selector` | 8.1 | Client HTTP simulé et sélecteurs pour `WebTestCase` |
| `symfony/clock` (`MockClock`) | 8.1.9 | Contrôle du temps dans les tests |
| `doctrine/data-fixtures` (`ORMPurger`) | 2.3.0, via fixtures-bundle 4.3.1 | Purge de la base entre deux tests |

Aucun outil de couverture (Xdebug ou PCOV), d'analyse statique (PHPStan) ou de style
(php-cs-fixer) n'est installé.

`phpunit.dist.xml` fait **échouer** la suite en cas de dépréciation, de notice ou d'avertissement
(`failOnDeprecation`, `failOnNotice` et `failOnWarning` valent `true`).

## Organisation

```
tests/
├── bootstrap.php                 # charge .env via Dotenv::bootEnv()
├── Unit/                         # 8 classes, 43 méthodes (46 cas) — services purs, sans base de données
├── Integration/                  # 4 classes, 14 méthodes (14 cas) — KernelTestCase + MySQL
├── Functional/                   # 4 classes, 23 méthodes (29 cas) — WebTestCase + MySQL
│   └── AppWebTestCase.php        # classe de base : client, purge, connecter()
└── Support/
    ├── Builder.php               # fabriques : hero(), mission(), villain(), aptitude(), bonus()
    ├── ServiceFactory.php        # calculator() : MissionSuccessCalculator monté à la main
    └── ResetDatabaseTrait.php    # viderBase(), persister()
```

Au total, 80 méthodes de test donnent 89 cas, certaines étant alimentées par des *data providers*.
Les 89 cas et leurs 257 assertions passent tous au 19/11/2026. Dans les tableaux ci-dessous, le
nombre entre parenthèses est le nombre de méthodes.

## Environnement de test

Les tests d'intégration et fonctionnels exigent une **base MySQL dédiée**. Doctrine ajoute le
suffixe `_test` au nom de la base (`dbname_suffix` dans `doctrine.yaml`).

1. Créez `.env.test.local` (non versionné) :

   ```dotenv
   DATABASE_URL="mysql://UTILISATEUR:MOT_DE_PASSE@127.0.0.1:3306/NOM_BASE?serverVersion=8.0.32&charset=utf8mb4"
   ```

   La base effectivement utilisée sera `NOM_BASE_test`.

2. Créez le schéma :

   ```bash
   php bin/console doctrine:database:create --env=test --if-not-exists
   php bin/console doctrine:migrations:migrate --env=test -n
   ```

Chaque test d'intégration ou fonctionnel **vide toutes les tables mappées** dans son `setUp()`
(`ResetDatabaseTrait::viderBase()`, qui désactive temporairement `FOREIGN_KEY_CHECKS`). N'utilisez
jamais la base de développement comme base de test.

## Commandes

```bash
php bin/phpunit                                   # toute la suite
php bin/phpunit tests/Unit                        # un dossier
php bin/phpunit tests/Unit/MissionTimerTest.php   # un fichier
php bin/phpunit --filter testDeploiementValide    # un test par son nom
php bin/phpunit --testdox                         # sortie lisible
```

## Tests unitaires

Ces tests n'utilisent ni conteneur ni base de données. Les services sont instanciés à la main,
avec `MockClock`.

| Classe | Ce qui est vérifié |
|---|---|
| `MissionSuccessCalculatorTest` (10) | Équipe vide, formule sur un cas calculé à la main, bornes, pénalités de taille, difficulté, menace des vilains, bonus, exigences et aptitudes, synergie |
| `DeploymentValidatorTest` (9) | Équipe valide, taille, doublon, indisponibilité, double engagement, santé, exigences, mission close, avertissements |
| `HeroProgressionServiceTest` (7) | Gain sans montée de niveau, montée simple et multiple, plafond des statistiques, niveau max, XP négative refusée, pourcentage |
| `HeroRecommendationServiceTest` (4) | Classement, motif d'inéligibilité, rôle, aptitude et synergie, exclusion des membres déjà dans l'équipe |
| `MissionTimerTest` (4) | Calcul depuis `dateFinPrevue`, décroissance, jamais négatif, format au-delà de 24 h |
| `EffectiveStatsResolverTest` (3) | Cumul des bonus, bonus expiré, futur ou inactif ignoré, borne à 100 |
| `SynergyResolverTest` (3) | Activation, nombre minimal, difficulté minimale et inactivité |
| `PositionDistanceCalculatorTest` (3) | Distance euclidienne, distance héros–mission, présence à la base |

## Tests d'intégration

Ces tests utilisent `KernelTestCase`, avec les services réels du conteneur et MySQL.

| Classe | Ce qui est vérifié |
|---|---|
| `MissionDeploymentServiceTest` (4) | Création des affectations et changement des statuts, rollback si un héros est invalide, double affectation refusée, verrou pendant le déploiement |
| `MissionLifecycleServiceTest` (4) | Clôture (libération et XP), annulation et transitions interdites, lancement sans équipe, alerte sur mission critique |
| `RequeteCitoyenneServiceTest` (3) | Alerte sur requête urgente, lien requête–mission, conversion unique |
| `EntityPersistenceTest` (3) | Persistance d'un héros, d'une mission avec objectifs ordonnés, contraintes de validation |

## Tests fonctionnels

Ces tests héritent de `AppWebTestCase` : requêtes HTTP simulées et connexion via `loginUser()`.

| Classe | Ce qui est vérifié |
|---|---|
| `SecurityTest` (6) | Redirection des anonymes, connexion par formulaire, 403 opérateur sur les pages d'administration, accès administrateur, 403 opérateur sur la conversion et l'annulation, 403 sans jeton CSRF |
| `FormsTest` (7) | Création, validation et modification d'un héros, création et modification d'une mission, saisie d'une requête, conversion depuis l'interface, ajout d'objectif |
| `SquadBuilderTest` (5) | Recalcul du pronostic à l'ajout et au retrait, déploiement valide, formulaire falsifié refusé, aptitude manquante bloquante, filtres et suggestion |
| `PagesTest` (5) | Pages principales en 200, données Doctrine sur le centre tactique, filtres, API timer jamais négative, pagination du roster |

## Données de test

Les tests **n'utilisent pas** `AppFixtures`. Chaque test construit ses propres données avec
`tests/Support/Builder.php` :

```php
use App\Tests\Support\Builder;

$hero = Builder::hero(['nom' => 'Apex', 'combat' => 90, 'x' => 50.0, 'y' => 50.0]);
$mission = Builder::mission(['difficulte' => Difficulte::B, 'combatRequis' => 80]);
$villain = Builder::villain(menace: 6, combat: 85);
Builder::bonus($hero, Statistique::COMBAT, 10, cumulable: false, expiration: '+1 day');
```

Les clés acceptées sont définies dans `Builder::hero()` et `Builder::mission()` ; consultez le
fichier avant de les utiliser.

## Écrire un nouveau test

| Vous testez… | Classe de base | Dossier |
|---|---|---|
| Un calcul ou un service sans base de données | `PHPUnit\Framework\TestCase` | `tests/Unit/` |
| Un service qui persiste | `KernelTestCase` + `ResetDatabaseTrait` | `tests/Integration/` |
| Une page ou une action HTTP | `App\Tests\Functional\AppWebTestCase` | `tests/Functional/` |

Exemple de test fonctionnel :

```php
namespace App\Tests\Functional;

final class AlerteTest extends AppWebTestCase
{
    public function testAcquittementDUneAlerte(): void
    {
        $this->connecter('ROLE_OPERATOR');
        // … créer une Alerte, puis la persister via self::persister($this->em, $alerte)
        $crawler = $this->client->request('GET', '/');
        $this->client->submit($crawler->filter('.urgent-banner form')->form());
        self::assertResponseRedirects();
    }
}
```

Conventions observées dans le projet :

- noms de méthodes en français, préfixés par `test` (`testUneRequeteNePeutEtreConvertieQuUneFois`) ;
- `MockClock` pour tout ce qui dépend du temps, sans jamais appeler `sleep()` ;
- un test fonctionnel vérifie le comportement HTTP (code, redirection, contenu), pas seulement l'absence d'exception.

## Parties non couvertes

Constat établi par recherche des routes et services dans `tests/` :

| Élément | Couverture |
|---|---|
| `AlerteController::acquitter()` | ⛔ Aucun test |
| `RequeteCitoyenneController::classer()` et `RequeteCitoyenneService::classer()` | ⛔ Aucun test |
| `HeroController::delete()`, notamment le refus si le héros est engagé | ⛔ Aucun test |
| Progression et suppression d'objectif (`app_mission_objectif_progress`, `_delete`) | ⛔ Aucun test |
| Transitions `publier` et `cloturer` via HTTP | 🟡 Couvertes au niveau du service seulement |
| `Admin\UserController` et `Admin\VillainController` (création et modification) | 🟡 Accès testé, formulaires non testés |
| `TacticalMapProvider` (marqueurs et zones) | 🟡 Indirectement, via la page du centre tactique |
| `AlerteService` | 🟡 Indirectement, via les services de mission et de requête |
| `App\Twig\AppExtension` | 🟡 Indirectement, via le rendu des pages |
| `SimulerAlertesCommand` | ⛔ Aucun test |
| `AppFixtures` (chargement sans erreur) | ⛔ Aucun test |
| Contrôleurs Stimulus (JavaScript) | ⛔ Aucun test |
| Accessibilité et rendu responsive | ⛔ Aucun test automatisé |
