---
name: symfony-form-creator
description: créer et modifier des formulaires symfony classiques basés sur des formtype pour une ou plusieurs entités doctrine. utiliser lorsque l'utilisateur demande « créer un formulaire pour une ou plusieurs entités nommées », ou demande de produire les fichiers php et twig associés, avec validation, relations doctrine, contrôleurs et tests symfony. adapter le code à la version réellement installée dans le projet et travailler directement dans le dépôt avec claude code.
---

# Symfony Form Creator

Créer directement dans le projet les fichiers PHP et Twig nécessaires à un formulaire Symfony classique. Produire du code prêt à exécuter, cohérent avec l'architecture existante et couvert par des tests.

## Entrée attendue

Interpréter une demande de la forme :

`Créer un formulaire pour le ou les entités <noms des entités>.`

Si les entités existent, les analyser avant toute modification. Ne demander une précision que si une décision fonctionnelle impossible à déduire bloque réellement l'implémentation.

## Procédure obligatoire

1. Inspecter avant d'écrire :
   - `composer.json` et `composer.lock` pour déterminer les versions de PHP, Symfony, Doctrine et PHPUnit ;
   - `src/Entity/` pour les propriétés, types PHP, attributs Doctrine, contraintes Validator, accesseurs et relations ;
   - les contrôleurs, formulaires, templates et tests existants afin de reproduire les conventions du projet ;
   - `config/routes*`, `config/packages/`, `templates/` et la configuration de test utile.
2. Vérifier les composants disponibles : `symfony/form`, `symfony/validator`, Doctrine ORM, Twig et `symfony/test-pack`. Ne pas modifier les dépendances sans nécessité. Si un composant manque, expliquer puis utiliser Composer uniquement si l'environnement permet l'installation.
3. Définir le flux create/edit nécessaire. Pour plusieurs entités, déterminer si elles nécessitent :
   - un `EntityType` pour sélectionner une relation ;
   - un formulaire imbriqué pour une entité possédée ;
   - un `CollectionType` avec `entry_type` pour une relation multiple.
4. Créer ou modifier un `src/Form/<Entity>Type.php` par formulaire réutilisable.
5. Ajouter seulement les contraintes de validation justifiées par le modèle métier. Préférer les attributs PHP existants et ne pas dupliquer inutilement les règles dans le FormType.
6. Créer ou adapter le contrôleur : `createForm()`, `handleRequest()`, `isSubmitted() && isValid()`, persistance Doctrine, redirection après succès et rendu Twig après échec.
7. Créer ou adapter les templates Twig. Utiliser `form_start`, `form_row` ou `form_widget`, `form_errors` si nécessaire, puis `form_end`. Réutiliser un partial `_form.html.twig` lorsque create et edit partagent le formulaire.
8. Créer les tests adaptés :
   - test du FormType dans `tests/Form/` pour mapping, champs, soumission valide et erreurs importantes ;
   - test fonctionnel du contrôleur dans `tests/Controller/` pour affichage, soumission valide, soumission invalide, persistance et redirection ;
   - test des relations ou collections lorsque le formulaire en manipule.
9. Exécuter la vérification disponible dans cet ordre :
   - `php bin/console lint:twig templates` ;
   - `php bin/console lint:container` ;
   - `php bin/phpunit` ou les tests ciblés ;
   - les outils déjà configurés dans le projet, par exemple PHPStan, Psalm, PHP-CS-Fixer ou ECS.
10. Corriger les erreurs causées par les modifications. Ne pas masquer un échec et ne pas prétendre qu'un test a réussi sans l'avoir exécuté.

## Règles de génération

- Respecter la version installée ; ne jamais imposer aveuglément la version `current` de la documentation.
- Utiliser les namespaces, routes, noms de templates, styles et conventions déjà présents.
- Préférer les attributs PHP si le projet les utilise déjà.
- Déduire les types de champs des propriétés et contraintes, puis vérifier les cas ambigus.
- Employer `EntityType` avec `class`, `choice_label` et, si utile, `query_builder` pour les relations Doctrine.
- Pour `CollectionType`, régler explicitement selon le modèle : `entry_type`, `allow_add`, `allow_delete`, `by_reference` et `prototype`.
- Ne pas exposer automatiquement les champs sensibles ou techniques : identifiant, mot de passe haché, rôles, timestamps gérés automatiquement, propriétaire imposé par l'utilisateur connecté.
- Conserver la protection CSRF par défaut. Pour une suppression, utiliser un jeton CSRF distinct et le valider côté contrôleur.
- Appliquer le modèle Post/Redirect/Get après une soumission réussie.
- Ne pas générer de migration si le schéma des entités n'a pas changé. Si une entité doit être modifiée, créer ensuite une migration Doctrine et la signaler clairement.
- Ne pas écraser du code métier existant ni modifier des fichiers sans rapport avec la demande.
- Ne pas utiliser `make:crud` à l'aveugle sur un projet existant. Les générateurs peuvent servir de point de départ uniquement si leurs sorties sont inspectées et adaptées.

## Stratégie de tests

### FormType

Utiliser `TypeTestCase` pour vérifier la soumission et le mapping. Démarrer le kernel ou utiliser les extensions de formulaire nécessaires lorsque `EntityType` dépend de Doctrine. Vérifier les options importantes et les transformations personnalisées.

### Contrôleur

Utiliser `WebTestCase` et le client Symfony. Contrôler au minimum :

- code HTTP de la page ;
- présence du formulaire et de ses champs ;
- soumission avec le bouton réel ;
- redirection après succès ;
- enregistrement en base de test ;
- affichage des erreurs pour des données invalides.

Préserver l'isolation des tests. Employer les fixtures, factories ou mécanismes de réinitialisation déjà présents dans le projet.

## Documentation de référence

Consulter `references/symfony-forms.md` uniquement lorsqu'un choix d'API ou de conception doit être confirmé. Toujours privilégier la documentation correspondant à la version détectée dans `composer.lock`.

## Compte rendu final

Après les modifications, répondre brièvement avec :

1. les fichiers créés et modifiés ;
2. les choix importants concernant relations et validation ;
3. les commandes de test exécutées avec leur résultat ;
4. les éventuelles actions manuelles restantes.
