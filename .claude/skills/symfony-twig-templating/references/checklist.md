# Checklist Twig

## Intégration Symfony
- Le template existe sous `templates/`
- Le chemin passé à `render()` est correct
- Toutes les variables utilisées sont transmises
- Les routes et paramètres existent
- Les assets et traductions correspondent à la configuration du projet

## Qualité
- Héritage et blocs cohérents
- Aucun HTML dupliqué sans raison
- États vide, erreur et absence prévus
- Logique métier absente du template
- HTML valide et sémantique
- Libellés, focus et navigation clavier utilisables

## Sécurité
- Autoéchappement conservé
- Aucun `|raw` non justifié
- Aucune donnée utilisateur injectée dans du JavaScript inline
- Formulaires et actions sensibles protégés contre la CSRF
- Aucun secret ou détail interne rendu

## Vérification
- `lint:twig` réussit
- Tests fonctionnels couvrent les principaux états
- Extensions personnalisées testées unitairement
- Sortie utilisateur hostile correctement échappée
