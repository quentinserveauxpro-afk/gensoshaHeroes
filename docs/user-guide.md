# Guide utilisateur

Ce guide s'adresse aux opérateurs (`ROLE_OPERATOR`) et aux administrateurs (`ROLE_ADMIN`). Les
actions réservées aux administrateurs sont signalées par **[admin]**.

## Sommaire

1. [Se connecter](#se-connecter)
2. [Consulter le centre tactique](#consulter-le-centre-tactique)
3. [Consulter une mission](#consulter-une-mission)
4. [Surveiller le timer](#surveiller-le-timer)
5. [Consulter et mettre à jour les objectifs](#consulter-et-mettre-à-jour-les-objectifs)
6. [Traiter une requête citoyenne](#traiter-une-requête-citoyenne)
7. [Consulter un héros](#consulter-un-héros)
8. [Constituer une équipe](#constituer-une-équipe)
9. [Déployer une équipe](#déployer-une-équipe)
10. [Clôturer ou annuler une mission](#clôturer-ou-annuler-une-mission)
11. [Parcours non disponibles](#parcours-non-disponibles)

## Se connecter

1. Ouvrez l'application : sans session, vous êtes redirigé vers `/login`.
2. Saisissez votre e-mail et votre mot de passe. En démonstration, utilisez `operateur@genosha.test` / `operateur`.
3. Vous arrivez sur le centre tactique.
4. Pour vous déconnecter, utilisez le bouton rond en haut à droite.

La barre latérale donne accès au Centre Tactique, au Squad Builder, au Roster des Héros, aux
Missions et aux Requêtes Citoyennes. Les administrateurs disposent en plus d'une section
« Administration » (Vilains, Utilisateurs).

## Consulter le centre tactique

1. Cliquez sur **Centre Tactique**.
2. La colonne de gauche liste les missions actives. Vous pouvez :
   - filtrer par statut avec les puces (Actives, En cours, En attente, Brouillon, Terminée) ;
   - affiner par **Difficulté** et **Urgence**, puis cliquer sur **Filtrer**.
3. Cliquez sur une carte de mission pour la sélectionner. Le panneau sur la carte affiche alors
   son timer, ses objectifs et l'accès au Squad Builder.
4. Sur la carte :
   - les cercles colorés indiquent les zones de menace ;
   - les pastilles représentent les héros (un clic ouvre leur fiche) ;
   - les cases « Zones de menace » et « Héros » masquent ces calques.
5. En bas de la colonne, la carte « Statut global de protection » affiche la moyenne des taux de
   réussite des missions engagées.
6. Si une alerte est active, un bandeau rouge s'affiche en haut de la page :
   - **Scramble Unités** ouvre le Squad Builder de la mission concernée ;
   - **Traiter** ouvre la requête concernée ;
   - **✕** acquitte l'alerte.

## Consulter une mission

1. Ouvrez **Missions**, filtrez éventuellement par statut, puis cliquez sur une mission. Vous
   pouvez aussi cliquer sur **Détails** depuis le centre tactique.
2. La fiche présente :
   - la description, la difficulté, l'urgence, la localisation, la cible et les exigences ;
   - les vilains impliqués ;
   - les objectifs ;
   - l'équipe affectée et le pronostic de réussite ;
   - la requête d'origine, s'il y en a une.
3. **[admin]** **Modifier** ouvre le formulaire complet de la mission.

## Surveiller le timer

- Le compte à rebours s'affiche sur les cartes de mission, dans le panneau du centre tactique et
  en grand sur la fiche de la mission.
- Il est calculé par le serveur et se resynchronise automatiquement chaque minute.
- À l'échéance, il affiche **« Mission terminée »**. Cela ne clôture **pas** la mission : un
  administrateur doit la clôturer manuellement.

## Consulter et mettre à jour les objectifs

1. Sur la fiche d'une mission, la section des objectifs les liste dans l'ordre d'affichage :
   - terminé : barré ;
   - en cours : jauge de progression ;
   - en attente.
2. Tant que la mission n'est pas close, chaque objectif a un curseur de progression. Déplacez-le,
   puis cliquez sur **Mettre à jour**. À 100 %, l'objectif est marqué comme terminé.
3. **[admin]** Le formulaire sous la liste ajoute un objectif. L'icône de corbeille supprime un objectif.

## Traiter une requête citoyenne

1. Ouvrez **Requêtes Citoyennes**. Le bouton **Déclarer Urgence Alpha** de la barre supérieure
   mène directement au formulaire.
2. **Enregistrer une requête** : remplissez le formulaire « Saisie opérateur » (origine, faits,
   détails, catégorie, localisation, coordonnées X/Y entre 0 et 100, urgence, demandeur), puis
   validez. Si l'urgence est Haute ou Critique, une alerte est créée.
3. **Trier la liste** : filtrez par catégorie ou par urgence, et triez par urgence ou par date.
4. Pour chaque requête ouverte, deux actions sont possibles :
   - **Classer sans suite** : la requête passe au statut « Classée ».
   - **[admin]** **Convertir en mission** : une mission « En attente » est créée, puis sa fiche
     s'ouvre. Constituez ensuite l'équipe (voir ci-dessous).

## Consulter un héros

1. Ouvrez **Roster des Héros**, ou tapez un nom dans la barre de recherche en haut de page.
2. Filtrez par rôle (Assaut, Contrôle, Soutien, Éclaireur), triez par niveau, nom ou PV, puis
   naviguez entre les pages (5 héros par page).
3. Cliquez sur un héros pour afficher sa fiche :
   - progression d'XP ;
   - santé et fatigue ;
   - bonus actifs et aptitudes ;
   - radar et barres de statistiques effectives ;
   - historique des missions et synergies.
4. **Assigner à une escouade** (si le héros est disponible) ouvre le Squad Builder.
5. **[admin]** **Modifier la fiche**, **Supprimer** (impossible si le héros est en mission),
   **Enrôler un nouveau métahumain** depuis la liste.

## Constituer une équipe

1. Ouvrez **Squad Builder**, ou cliquez sur **Constituer escouade** depuis une mission. Seules les
   missions en **brouillon** ou **en attente** sont proposées. Le sélecteur en haut de page
   permet de changer de mission.
2. Ajustez le nombre d'**emplacements** (entre la taille minimale et la taille maximale de la
   mission), puis cliquez sur **Appliquer**.
3. Dans la **réserve**, filtrez par :
   - Tous, Disponibles, En base, Proximité (< 5 km) ;
   - rôle, niveau minimal ou santé minimale.
4. Cliquez sur **Assigner** sur la carte d'un héros. Le pronostic est recalculé à chaque ajout.
   La suggestion affichée dans l'emplacement vide propose le meilleur candidat éligible.
5. **Changer** sur un membre le retire, pour le remplacer. **Réinitialiser** vide la composition.
6. Le premier héros ajouté sera le **chef d'escouade** : l'ordre d'ajout compte.
7. Avant de déployer, consultez le panneau de validation :
   - les erreurs (en rouge) bloquent le déploiement ;
   - les avertissements (fatigue, distance) ne le bloquent pas.

## Déployer une équipe

1. Quand le rapport de validation ne contient plus d'erreur, cliquez sur **Confirmer l'équipe et déployer**, en bas de page.
2. Le serveur revalide la composition. En cas de refus (par exemple, un héros vient d'être engagé
   ailleurs par un autre opérateur), les erreurs s'affichent et vous restez sur le Squad Builder
   avec la même composition.
3. En cas de succès :
   - la mission passe **En cours** ;
   - les héros passent **En mission** ;
   - vous êtes redirigé vers le centre tactique, sur la mission déployée.

## Clôturer ou annuler une mission

**[admin]** Sur la fiche d'une mission, la barre « Cycle de vie » propose les actions disponibles
selon le statut :

| Statut | Actions |
|---|---|
| Brouillon | Publier, Lancer, Annuler |
| En attente | Lancer, Annuler |
| En cours | Clôturer : succès, Clôturer : échec, Annuler |

> « Lancer » échoue si aucune équipe n'est affectée. Pour démarrer une mission, passez par le
> Squad Builder, qui la lance directement.

Après une clôture, les héros gagnent de l'expérience et de la fatigue, puis redeviennent
disponibles, ou blessés si leur santé est inférieure à 30 %.

## Parcours non disponibles

Ces actions figurent dans les maquettes, mais **ne sont pas implémentées** :

- calques Satellite et Thermique, boutons Ravitaillement et Canal audio ;
- export du roster, arbre de talents, télémétrie biométrique, ordre de repos, rapport de performance ;
- transmission à une brigade, dépêche de secours, synchronisation du flux 112 ;
- simulation de scénarios de combat, affectation en réserviste ;
- remise en forme d'un héros blessé : seul un administrateur peut modifier ses PV ou son statut, via le formulaire ;
- suppression d'une mission, d'un vilain ou d'un utilisateur ;
- gestion des bonus, synergies et aptitudes : ces données ne proviennent que des fixtures.
