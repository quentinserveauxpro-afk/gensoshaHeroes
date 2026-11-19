---
name: symfony-controller-management
description: créer, modifier, auditer et tester des controllers symfony. utiliser pour les routes par attributs, Request et Response, rendu Twig, JSON, redirections, paramètres, injection de services, fichiers, erreurs HTTP, sessions, flash messages et tests fonctionnels.
---
# Symfony Controller Management

## Objectif
Produire des controllers Symfony courts, typés, sécurisés et testables, conformes aux conventions du projet.

## Règles
- Inspecter avant modification : `composer.json`, `src/Controller/`, routes, services, entités, formulaires, templates et tests concernés.
- Respecter la version de Symfony et les conventions déjà présentes.
- Un controller lit une `Request`, appelle la logique applicative, puis retourne une `Response`.
- Garder les controllers minces. Déplacer la logique métier complexe dans un service dédié.
- Utiliser l'injection de dépendances, jamais le conteneur global.
- Typer les paramètres, retours et dépendances.
- Utiliser les attributs `#[Route]` si le projet les utilise déjà.
- Donner un nom unique et stable à chaque route, généralement préfixé par `app_`.
- Ne jamais faire confiance aux paramètres de route, query string, formulaire ou JSON.
- Ne pas exposer de secret, trace, chemin interne ou détail de base de données.
- Ne modifier aucun fichier hors périmètre sans nécessité.

## Workflow

### 1. Auditer
1. Identifier les versions de PHP, Symfony et des bundles concernés.
2. Examiner les controllers voisins, la configuration des routes et les conventions de nommage.
3. Vérifier les services, repositories, formulaires, templates et serializers réellement disponibles.
4. Identifier la méthode HTTP, les entrées, le format de sortie, les droits requis et les erreurs attendues.
5. Signaler brièvement toute dépendance manquante ou ambiguïté bloquante.

### 2. Définir la route
- Déclarer explicitement le chemin, le nom et les méthodes HTTP.
- Ajouter des `requirements` pour contraindre les paramètres simples lorsque pertinent.
- Utiliser les paramètres de route typés.
- Éviter qu'une route dynamique masque une route statique.
- Vérifier avec `php bin/console debug:router`.
- Respecter les usages HTTP : GET lit, POST crée ou déclenche, PUT remplace, PATCH modifie partiellement, DELETE supprime.

### 3. Lire la requête
- Utiliser `Request` pour `query`, `request`, `headers`, `files` et le corps JSON.
- Valider et normaliser toute entrée avant usage.
- Pour du JSON, gérer le corps vide, le JSON invalide, les champs absents et les types incorrects.
- Pour un formulaire Symfony, utiliser `handleRequest()`, puis vérifier `isSubmitted()` et `isValid()`.
- Pour un fichier, vérifier erreur d'upload, taille, type réel, nom généré et dossier cible.

### 4. Appeler l'application
- Injecter les services dans le constructeur ou dans l'action selon les conventions du projet.
- Utiliser un repository pour la lecture et l'`EntityManagerInterface` lorsqu'une persistance directe est réellement adaptée.
- Ne pas mettre de requête SQL ou de logique métier complexe dans le controller.
- Encadrer les opérations atomiques nécessitant plusieurs écritures par une transaction dans la couche appropriée.
- Vérifier l'autorisation avant de lire ou modifier une ressource sensible.

### 5. Retourner la réponse
Choisir une réponse explicite :
- HTML : `$this->render()` ;
- JSON : `$this->json()` ou une `JsonResponse` ;
- redirection : `$this->redirectToRoute()` ;
- fichier : `BinaryFileResponse` ou `file()` ;
- contenu diffusé : `StreamedResponse` ;
- absence de ressource : `createNotFoundException()` ;
- accès interdit : mécanismes Security, voter ou `denyAccessUnlessGranted()`.

Utiliser les constantes `Response::HTTP_*`. Pour une création REST, retourner normalement `201 Created`; pour une suppression sans corps, `204 No Content`; pour une entrée invalide, `400` ou `422` selon le contrat de l'application.

### 6. Sécuriser
- Protéger les actions sensibles avec le composant Security et, si nécessaire, un voter.
- Protéger les mutations issues d'un formulaire contre la CSRF.
- Ne pas utiliser GET pour supprimer ou modifier une ressource.
- Éviter les redirections vers une URL fournie directement par l'utilisateur.
- Ne jamais utiliser un chemin de fichier utilisateur sans validation et résolution sûre.
- Limiter les données sérialisées pour éviter les fuites et références circulaires.
- Ne pas retourner directement les messages d'exception internes dans une API.

### 7. Tester
Créer des tests fonctionnels avec `WebTestCase` couvrant au minimum :
- route et méthode HTTP ;
- statut et type de réponse ;
- rendu ou structure JSON ;
- cas nominal ;
- entrée invalide ;
- ressource absente ;
- accès anonyme ou interdit ;
- redirection et message flash si applicables ;
- persistance réelle pour les actions d'écriture.

Utiliser le Crawler pour le HTML et décoder le JSON pour contrôler sa structure. Ne pas surcharger les tests de mocks lorsque le comportement HTTP et l'intégration Symfony sont visés.

### 8. Vérifier
Exécuter selon les outils disponibles :
1. `php -l src/Controller/NomController.php`
2. `php bin/console lint:container`
3. `php bin/console debug:router`
4. `php bin/phpunit`

Ne jamais annoncer qu'une commande réussit sans l'avoir exécutée.

## Format de sortie
1. Résumé très court de l'audit.
2. Fichiers créés ou modifiés.
3. Contenu complet des fichiers.
4. Commandes de validation.
5. Risques et hypothèses restant à vérifier.

## Ressource
Consulter `references/checklist.md` avant de terminer.
