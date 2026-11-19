# Références officielles Symfony

Utiliser la version de la documentation qui correspond à la version du projet. Les URL `current` suivantes servent de points d'entrée :

- Documentation générale : https://symfony.com/doc/current
- Formulaires : https://symfony.com/doc/current/forms.html
- Relations et base de données Doctrine : https://symfony.com/doc/current/doctrine.html
- Validation : https://symfony.com/doc/current/validation.html
- Tests : https://symfony.com/doc/current/testing.html
- Types de champs : https://symfony.com/doc/current/reference/forms/types.html
- EntityType : https://symfony.com/doc/current/reference/forms/types/entity.html
- CollectionType : https://symfony.com/doc/current/reference/forms/types/collection.html
- Tests de formulaires : https://symfony.com/doc/current/form/unit_testing.html
- Protection CSRF : https://symfony.com/doc/current/security/csrf.html

## Principes à retenir

- Un formulaire fait la correspondance bidirectionnelle entre les données PHP et les données HTML soumises.
- Le flux recommandé consiste à construire le formulaire, l'afficher, traiter la requête, valider les données puis persister si elles sont valides.
- Les contraintes Validator appartiennent généralement au modèle lorsque la règle doit s'appliquer partout.
- Les tests Symfony comprennent des tests unitaires, d'intégration et fonctionnels. Choisir le niveau qui vérifie réellement le comportement créé.
