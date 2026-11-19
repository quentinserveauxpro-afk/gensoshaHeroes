# Checklist Controller Symfony

## Route
- Chemin, nom et méthodes déclarés
- Paramètres typés et contraints
- Aucun conflit avec une autre route

## Entrées
- Paramètres validés
- JSON invalide géré
- Formulaire soumis et valide avant traitement
- Fichier contrôlé avant stockage

## Architecture
- Controller court
- Logique métier dans un service
- Dépendances injectées
- Accès aux données cohérent avec le projet

## Réponse
- Type de `Response` explicite
- Code HTTP adapté
- Template, route cible ou structure JSON vérifiés
- Erreurs 404 et 403 gérées

## Sécurité
- Autorisation vérifiée
- CSRF sur les mutations concernées
- Aucune redirection ouverte
- Aucune fuite de données ou d'exception

## Tests
- Cas nominal
- Entrée invalide
- Introuvable
- Accès interdit
- Écriture vérifiée en base si applicable
