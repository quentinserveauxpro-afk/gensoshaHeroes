# Contribuer

## Sommaire

1. [Procédure de travail](#procédure-de-travail)
2. [Conventions Symfony et PHP](#conventions-symfony-et-php)
3. [Règles de nommage](#règles-de-nommage)
4. [Code d'origine à préserver](#code-dorigine-à-préserver)
5. [Migrations](#migrations)
6. [Tests](#tests)
7. [Validation Twig et YAML](#validation-twig-et-yaml)
8. [Vérifications avant commit](#vérifications-avant-commit)
9. [Conventional Commits](#conventional-commits)

## Procédure de travail

1. Partez à jour de `main` : `git pull`.
2. Créez une branche par sujet, par exemple `git switch -c feat/filtre-heros-disponibles`.
3. Développez par petites étapes et ajoutez ou adaptez les tests en même temps.
4. Lancez les [vérifications avant commit](#vérifications-avant-commit).
5. Committez par changement logique, en suivant les [Conventional Commits](#conventional-commits).
6. Poussez, puis ouvrez une demande de fusion.

Les consignes destinées aux agents IA se trouvent dans `AGENTS.md` et `.claude/skills/`. Le skill
`git-conventional-commit` y décrit le même processus de commit.

## Conventions Symfony et PHP

Ces règles s'appliquent déjà dans le code et sont reprises de `AGENTS.md`.

- **Attributs PHP** pour toutes les métadonnées : `#[Route]`, `#[IsGranted]`,
  `#[MapQueryParameter]`, `#[Assert\...]`, `#[ORM\...]`, `#[AsCommand]`, `#[AsTwigFunction]`.
  N'utilisez pas de routage YAML.
- **Autowiring** : injectez les dépendances par le constructeur, avec des propriétés promues
  `private readonly`. N'ajoutez pas de définition dans `services.yaml`, sauf nécessité.
- **Contrôleurs** `final`, qui étendent `AbstractController` et restent minces. Ils délèguent la
  logique aux services de `src/Service/<Domaine>/`.
- **Objets de valeur** en `final readonly class` (`SuccessPrognosis`, `TimerSnapshot`, `DeploymentReport`…).
- **Temps** : injectez `Psr\Clock\ClockInterface` dans les services, jamais `new \DateTimeImmutable()`.
- **Exceptions métier** : dérivez-les de `\DomainException` (`MissionWorkflowException`,
  `DeploymentException`). Le contrôleur les transforme en message flash.
- **Formulaires** : utilisez Symfony Form pour toute saisie, et Validator pour les règles. Les
  règles métier de déploiement restent dans `DeploymentValidator`, jamais en JavaScript.
- **Redirections après POST** : `Response::HTTP_SEE_OTHER` (303), compatible avec Turbo.
- **Style** : standard Symfony (dérivé de PSR-12). php-cs-fixer n'est pas installé.
- **Nouvelle capacité** : installez-la avec `composer require <paquet>` et laissez la recette Flex
  la configurer. Ne modifiez pas `config/bundles.php` à la main.

## Règles de nommage

| Élément | Convention observée | Exemple |
|---|---|---|
| Domaine (classes, méthodes, propriétés) | Français, camelCase | `convertirEnMission()`, `seuilSanteMinimal` |
| Classes techniques Symfony | Suffixes anglais standard | `HeroController`, `MissionType`, `HeroRepository` |
| Services | `<Concept><Rôle>` dans `src/Service/<Domaine>/` | `Mission/MissionSuccessCalculator` |
| Enums | Nom singulier, cas en MAJUSCULES, valeur égale au nom | `MissionStatut::EN_COURS` |
| Routes | `app_<domaine>_<action>` | `app_mission_objectif_add` |
| URL | Françaises, au pluriel | `/missions/{id}/modifier`, `/heros/nouveau` |
| Templates | `templates/<domaine>/<page>.html.twig` ; partiels préfixés par `_` | `squad_builder/_slot.html.twig` |
| Contrôleurs Stimulus | `snake_case_controller.js`, utilisés en `kebab-case` | `radar_chart_controller.js` → `data-controller="radar-chart"` |
| Jetons CSRF | `<action><id>` | `deployer12` |
| Tests | `test` suivi d'une phrase en français | `testUneRequeteNePeutEtreConvertieQuUneFois` |

## Code d'origine à préserver

Certaines parties de `Hero` et `Mission` sont antérieures aux ajouts, qui sont signalés par un
commentaire « Ajouts (additifs) ». Le code d'origine comprend notamment les colonnes `stat*`
en `DECIMAL`, `timer`, `niveauDeDifficulte`, ainsi que la relation `heroes` / `missions`.

- Ne renommez pas ces colonnes : passez par les accesseurs ajoutés (`getCombat()`, `getDateFinPrevue()`, `getDifficulte()`).
- N'utilisez pas `Mission::getHeroes()` : son mapping est invalide (voir
  [domain-model.md](domain-model.md#anomalie-de-mapping-connue)). Utilisez `getHerosAffectes()`.
- Le correctif de ce mapping doit faire l'objet d'une décision explicite et d'une migration dédiée.

## Migrations

1. Modifiez les attributs `#[ORM\...]` de l'entité.
2. Générez la migration : `php bin/console make:migration`.
3. **Relisez le SQL généré.** La base de développement est partagée : il ne doit y avoir aucun
   `DROP TABLE` sur `alimentaire`, `animal`, `fashion`, `loisirs`, `panier` ou `produit`. Le
   `schema_filter` les exclut ; ne le retirez pas.
4. Appliquez la migration en développement, puis en test :
   ```bash
   php bin/console doctrine:migrations:migrate -n
   php bin/console doctrine:migrations:migrate --env=test -n
   ```
5. Committez la migration avec le changement d'entité.

N'utilisez jamais `doctrine:schema:update --force` et n'écrivez pas de SQL à la main hors migration.

## Tests

- Toute fonctionnalité ou correction est livrée avec un test qui l'exerce comme le ferait un
  appelant : une requête HTTP pour un contrôleur, un appel de méthode pour un service.
- Choisissez le niveau de test et la classe de base selon le tableau de [testing.md](testing.md#écrire-un-nouveau-test).
- Construisez les données avec `tests/Support/Builder.php`, jamais avec les fixtures.
- Toute logique dépendant du temps se teste avec `MockClock`.
- La suite échoue sur toute dépréciation, notice ou avertissement : corrigez-les, ne les masquez pas.

## Validation Twig et YAML

```bash
php bin/console lint:twig templates
php bin/console lint:yaml config
php bin/console lint:container
```

Règles propres aux templates :

- aucun calcul métier dans Twig : passez par un service, éventuellement exposé dans `App\Twig\AppExtension` ;
- `path()` pour toutes les URL, sans chemin codé en dur ;
- réutilisez les partiels de `templates/components/` et les tons `ok`, `warn`, `danger`, `muted` ;
- accessibilité : `aria-labelledby` sur les sections, un libellé (visible ou `.sr-only`) sur chaque champ, icônes décoratives en `aria-hidden` (voir [design-system.md](design-system.md#accessibilité)) ;
- tout formulaire POST écrit à la main contient un `_token` vérifié côté contrôleur.

## Vérifications avant commit

```bash
git status --short && git diff                 # relire les changements
php bin/console lint:twig templates
php bin/console lint:yaml config
php bin/console lint:container
php bin/console doctrine:schema:validate       # « Database » doit rester [OK]
php bin/phpunit
```

Avant de committer, vérifiez aussi les points suivants :

- aucun secret ni fichier local n'est inclus : `.env.local`, `.env.test.local`, `var/`, `vendor/`, `.phpunit.cache/` ;
- les fichiers générés sont exclus, sauf les migrations et `composer.lock` ou `symfony.lock` s'ils ont changé ;
- un commit ne contient qu'un seul changement logique.

## Conventional Commits

Format : `type(portée optionnelle): description`, à l'impératif, en moins de 72 caractères, sans
point final. L'historique du dépôt est rédigé en français.

| Type | Usage |
|---|---|
| `feat` | Nouvelle fonctionnalité |
| `fix` | Correction de bug |
| `docs` | Documentation seule |
| `style` | Mise en forme sans changement de comportement |
| `refactor` | Restructuration sans changement de comportement |
| `perf` | Performance |
| `test` | Ajout ou modification de tests |
| `build` | Dépendances, outillage de build |
| `ci` | Intégration continue |
| `chore` | Maintenance |

Exemples :

```text
feat: ajout du filtre des héros disponibles
fix: correction du calcul du temps restant
docs: mise à jour de la documentation des missions
test: ajout des tests du Squad Builder
feat(requetes): ajout du classement en lot des requêtes
refactor(squad-builder): extraction du calcul des compteurs de réserve
build(deps): ajout de doctrine-fixtures-bundle
```

Portées déjà utilisées dans l'historique : `heros`, `missions`, `requetes`, `squad-builder`,
`tactical-center`, `admin`, `security`, `ui`, `entity`, `enum`, `service`, `fixtures`, `doctrine`, `readme`.
