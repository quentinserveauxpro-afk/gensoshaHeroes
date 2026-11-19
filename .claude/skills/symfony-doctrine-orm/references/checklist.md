# Checklist Doctrine ORM

## Mapping
- Identifiant et stratégie de génération cohérents
- Types SQL compatibles avec les types Doctrine
- Longueurs, précision et échelle explicites
- Nullabilité et valeurs par défaut cohérentes
- Côté propriétaire correct pour chaque relation
- `mappedBy` et `inversedBy` cohérents
- Suppression en cascade et `orphanRemoval` justifiés
- Index et contraintes uniques présents

## Migration
- `up()` limité au besoin
- `down()` réaliste ou limite expliquée
- Aucune suppression involontaire
- Traitement des données existantes avant `NOT NULL`
- Clés étrangères et index dans un ordre compatible
- SQL portable ou dépendance au moteur annoncée

## Fixtures
- Données déterministes
- Relations valides
- Contraintes uniques respectées
- Dépendances explicites
- Aucune donnée réelle sensible

## Tests
- Base dédiée à l'environnement `test`
- Schéma obtenu par migrations
- Test du chargement des fixtures
- Test des repositories avec Doctrine réel
- Nettoyage ou rollback assuré
