---
name: symfony-twig-templating
description: créer, modifier, organiser, sécuriser et tester des templates twig dans un projet symfony. utiliser pour des pages html.twig, layouts, héritage, blocs, composants réutilisables, formulaires, routes, assets, traductions, extensions twig et tests de rendu.
---
# Symfony Twig Templating

## Objectif
Produire des templates Twig propres, réutilisables, accessibles et sûrs, intégrés aux conventions du projet Symfony existant.

## Déclencheurs
Utiliser ce skill lorsqu'une demande concerne :
- un fichier `*.html.twig` ou un autre format Twig ;
- la création ou la modification d'une page Symfony ;
- un layout, un bloc, un `include`, un macro ou un composant Twig ;
- l'affichage d'un formulaire Symfony ;
- une extension, fonction ou filtre Twig personnalisé ;
- une erreur de rendu, de variable, de route ou de syntaxe Twig ;
- des tests de templates ou de réponses HTML.

## Règles impératives
- Inspecter le projet avant de modifier : `composer.json`, `config/packages/twig.yaml`, `templates/`, contrôleurs, routes, assets, traductions et tests concernés.
- Respecter l'architecture, le style CSS, les conventions et les versions déjà utilisées.
- Ne pas créer de contrôleur, route, variable, service, filtre ou dépendance sans vérifier son existence.
- Garder la logique métier en PHP. Twig ne doit contenir que de la logique de présentation simple.
- Utiliser des noms de fichiers, dossiers et variables en `snake_case`.
- Conserver la double extension, par exemple `product/index.html.twig`.
- Générer les URL avec `path()` ou `url()`, jamais en dur.
- Laisser l'autoéchappement actif. N'utiliser `|raw` que pour un contenu explicitement fiable et déjà assaini.
- Ne pas afficher de secret, donnée sensible ou détail technique interne.
- Produire du HTML sémantique, utilisable au clavier et avec des libellés accessibles.
- Ne pas modifier les fichiers hors périmètre sans nécessité démontrée.

## Workflow

### 1. Auditer
1. Identifier les versions de Symfony, Twig et `symfony/twig-bundle`.
2. Examiner `config/packages/twig.yaml`, notamment `default_path`, `strict_variables`, les chemins et les globals.
3. Examiner le layout principal, les templates voisins et les conventions CSS/JavaScript.
4. Identifier précisément les variables transmises par le contrôleur ou le service de rendu.
5. Vérifier les noms de routes, paramètres, traductions, formulaires et assets réellement disponibles.
6. Signaler brièvement les éléments manquants qui empêchent un rendu correct.

### 2. Choisir la structure
- Page complète : étendre le layout principal avec `{% extends 'base.html.twig' %}`.
- Zone remplaçable : créer ou surcharger un `{% block %}`.
- Fragment simple sans état propre : utiliser `include()`.
- Petit rendu paramétrable sans dépendance au contexte : utiliser une macro.
- Interface réutilisable complexe : suivre le système de composants déjà installé dans le projet ; ne pas ajouter UX TwigComponent sans demande ou dépendance existante.
- E-mail ou format texte/XML : employer la double extension correspondant au format final.

### 3. Écrire le template
- Utiliser `{{ ... }}` pour afficher une expression, `{% ... %}` pour la logique de présentation et `{# ... #}` pour les commentaires non rendus.
- Prévoir explicitement les états : chargement si applicable, liste vide, valeur absente, succès et erreur.
- Utiliser `for ... else` pour les collections susceptibles d'être vides.
- Employer `is defined`, `default` ou `??` uniquement lorsque l'absence est réellement acceptable ; ne pas masquer une erreur de contrat entre PHP et Twig.
- Limiter les accès imbriqués risquant de rencontrer `null`.
- Utiliser les filtres et fonctions natifs ou Symfony avant de créer une extension personnalisée.
- Pour un formulaire Symfony, utiliser `form_start`, `form_row`, `form_errors` et `form_end`, puis personnaliser le thème seulement si nécessaire.
- Pour les assets, utiliser la fonction prévue par le projet, par exemple `asset()` ou les fonctions AssetMapper/Encore déjà configurées.
- Pour les traductions, utiliser `trans` ou le filtre `|trans` avec les domaines existants.

### 4. Sécuriser
- Ne concaténer aucun HTML avec une donnée utilisateur.
- Échapper selon le contexte ; ne pas supposer que l'échappement HTML protège JavaScript, CSS ou une URL.
- Éviter les scripts inline contenant des données dynamiques. Préférer des attributs `data-*` échappés et lus par JavaScript.
- N'utiliser `|raw` qu'après vérification de la chaîne complète de confiance et d'assainissement côté serveur.
- Conserver la protection CSRF des formulaires et actions sensibles fournie par Symfony.
- Ne pas rendre un nom de template contrôlé directement par l'utilisateur.

### 5. Extensions Twig
Créer une extension PHP seulement si le besoin est transversal et ne peut pas être satisfait clairement par Twig ou un ViewModel.
- Placer l'extension selon les conventions du projet, généralement dans `src/Twig/`.
- Étendre `AbstractExtension`.
- Déclarer explicitement les `TwigFilter` et `TwigFunction`.
- Garder les fonctions pures si possible.
- Injecter les services par constructeur.
- Ne marquer une sortie comme HTML sûre qu'avec une justification de sécurité vérifiable.
- Ajouter des tests unitaires pour chaque filtre ou fonction.

### 6. Vérifier et tester
Exécuter uniquement les commandes disponibles dans le projet :
1. `php bin/console lint:twig templates/`
2. `php bin/console debug:twig`
3. `php bin/console debug:router`
4. `php bin/phpunit`

Tests recommandés :
- test fonctionnel avec `WebTestCase` pour le statut HTTP, le contenu, les liens et les formulaires ;
- assertions DOM avec le Crawler, par exemple titre, texte, nombre d'éléments et attributs ;
- test unitaire d'une extension Twig personnalisée ;
- test des états vide, nominal et erreur ;
- vérification que les données utilisateur sont échappées.

Ne jamais annoncer qu'une commande réussit sans l'avoir exécutée.

## Format de sortie
Fournir dans cet ordre :
1. résumé très court de l'audit ;
2. fichiers créés ou modifiés ;
3. contenu complet des fichiers ;
4. commandes de validation ;
5. risques ou hypothèses restant à vérifier.

## Ressources
Consulter :
- `references/checklist.md` pour le contrôle qualité ;
- `references/patterns.md` pour les structures Twig recommandées.
