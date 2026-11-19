# Design system « Tactical Field Operations »

## Sources

Deux sources décrivent l'interface. Ce document les compare.

- **Spécification** : `DESIGN.md`, situé dans le dossier parent du dépôt
  (`../DESIGN.md`, hors versionnement). Il contient des tokens YAML et une description en prose.
- **Implémentation** : `assets/styles/app.css` (variables `:root`) et les partiels
  `templates/components/`.

Dans les tableaux, **Utilisé** désigne une règle présente dans le CSS ou les templates, et
**Prévu** une règle seulement décrite dans `DESIGN.md`.

> **Incohérence interne de `DESIGN.md`.** Les tokens YAML (par exemple `surface: #edffe0`,
> `primary: #34462a`) ne correspondent pas aux couleurs citées dans la prose (canvas `#F4F6F2`,
> secondary `#38462F`, neutral `#607954`). Le CSS suit les **tokens YAML**. De la prose, il ne
> reprend que l'ambre `#F59E0B` et ponctuellement `#607954`.

## Sommaire

1. [Couleurs](#couleurs)
2. [Typographie](#typographie)
3. [Espacements et grille](#espacements-et-grille)
4. [Bordures, rayons et élévation](#bordures-rayons-et-élévation)
5. [Composants](#composants)
6. [Graphiques](#graphiques)
7. [Responsive](#responsive)
8. [Accessibilité](#accessibilité)

## Couleurs

| Variable CSS | Valeur | Token `DESIGN.md` | Usage réel |
|---|---|---|---|
| `--surface` | `#edffe0` | `surface` / `background` | Fond de page (`body`) |
| `--surface-lowest` | `#ffffff` | `surface-container-lowest` | Cartes (`.card`) |
| `--surface-low` | `#e0fcce` | `surface-container-low` | Champs, objectifs, cartes teintées |
| `--surface-container` | `#daf6c9` | `surface-container` | Panneaux, puces, pagination |
| `--surface-high` | `#d4f1c4` | `surface-container-high` | Survol de la navigation, pistes de jauge |
| `--primary` | `#34462a` | `primary` | Texte fort, bouton sombre, anneaux |
| `--primary-container` | `#4b5e40` | `primary-container` | Bouton principal, navigation active, jauges « ok » |
| `--secondary-container` | `#d7e8c8` | `secondary-container` | Badges « ok », flash succès |
| `--tertiary` / `--tertiary-container` | `#5d3900` / `#7d4e00` | idem | Bouton ambre, timer, badges « warn » |
| `--tertiary-fixed` | `#ffddb8` | `tertiary-fixed` | Fond des badges « warn » |
| `--amber` | `#f59e0b` | prose « Tactical Amber » | Jauges et anneaux « warn », halo `--glow-amber` |
| `--error` / `--error-container` | `#ba1a1a` / `#ffdad6` | idem | Danger, urgence critique |
| `--map-bg` | `#1d2a17` | — (ajout) | Fond de la carte tactique |

Les composants partagent quatre tons sémantiques : `ok`, `warn`, `danger` et `muted`. Le ton est
calculé côté serveur, par les méthodes `tone()` des enums et par la fonction Twig `jauge_tone()` :
moins de 40 % donne `danger`, moins de 70 % donne `warn`, au-delà `ok`.

Les tokens `inverse-*`, `*-fixed-dim`, `on-*-fixed` et `surface-tint` de `DESIGN.md` sont **prévus**
mais **non utilisés**.

## Typographie

Les polices **Plus Jakarta Sans** (titres), **Inter** (texte) et **JetBrains Mono** (timers, codes)
sont chargées depuis Google Fonts dans `base.html.twig`.

| Classe | Spécification `DESIGN.md` | Implémentation |
|---|---|---|
| `.display` | `display-lg` 40/48, 800 | ✅ Utilisé ; 30/38 sous 768 px (`display-lg-mobile`) |
| `.headline-lg` | 32/40, 700 | ✅ Utilisé ; 24/32 sous 768 px |
| `.headline-md` | 22/28, 600 | ✅ Utilisé |
| `.headline-sm` | 18/24, 600 | ✅ Utilisé |
| `body` | `body-md` 14/20 | ✅ Utilisé |
| `.label-md` | 12/16, 600 | ✅ Utilisé |
| `.label-sm` | 10/14, 700, majuscules | ✅ Utilisé, avec un espacement de lettres de 0,08em |
| `body-lg`, `body-sm`, `label-lg` | définis | ⛔ Pas de classe dédiée |
| `.mono` | — | Ajout : JetBrains Mono pour les timers et les codes d'alerte |

## Espacements et grille

| Élément | Prévu (`DESIGN.md`) | Utilisé |
|---|---|---|
| Échelle | `space-xs` 0,25rem à `space-xl` 2,5rem | Valeurs en `rem` codées en dur (0,25 à 2rem), sans variables d'espacement |
| Marges du contenu | `margin-lg` 2rem sur ordinateur, `margin` 1rem sur mobile | `.app__content` : 1,5rem 2rem ; 1rem horizontal sous 768 px |
| Gouttières | `gutter-lg` 1,5rem | `.grid` : `gap` de 1,5rem |
| Grille | 12 colonnes (ordinateur), 8 (tablette), 4 (mobile) | ⛔ Non implémentée : grilles spécifiques par écran (`.tc-grid`, `.roster`, `.sb-layout`…) et utilitaires `.grid-2/3/4` |
| Disposition en trois volets | navigation, carte ou roster, panneau latéral | ✅ Barre latérale de 18rem, contenu principal, panneau HUD ou colonne latérale |

## Bordures, rayons et élévation

| Élément | Prévu | Utilisé |
|---|---|---|
| Rayons | `sm` 0,5rem, `DEFAULT` 1rem, `md` 1,5rem, `lg` 2rem, `xl` 3rem, `full` | `--r-sm`, `--r`, `--r-md`, `--r-lg`, `--r-full` ✅ ; `xl` ⛔ non utilisé |
| Niveau 1 (tuiles) | `#FFF`, bordure `rgba(96,121,84,.18)`, ombre `0 4px 20px -4px …` | ✅ `.card` (`--border-soft`, `--shadow-1`, rayon `--r-lg`) |
| Niveau 2 (HUD verre dépoli) | `rgba(255,255,255,.82)` + `blur(12px)` | ✅ `.card--hud` (opacité 0,88 + `blur(12px)`) ; barre d'outils de la carte en `blur(8px)` |
| Niveau 3 (alertes ambre) | lueur intérieure + halo ambre | 🟡 Halo ambre seul (`--glow-amber`), sur `.btn--amber` ; le bandeau d'urgence utilise une pulsation rouge |
| Canvas `#F4F6F2` | prose | ⛔ Remplacé par `--surface` `#edffe0` |

## Composants

Les partiels se trouvent dans `templates/components/`.

| Composant | Partiel / classe | Paramètres | Conformité à `DESIGN.md` |
|---|---|---|---|
| Badge de statut | `_badge.html.twig`, `.badge--{ok,warn,danger,muted,solid,solid-danger,solid-warn}` | `label`, `tone`, `dot`, `class` | ✅ Pilule, majuscules, point lumineux de 6 px |
| Badge de mission | `_mission_badge.html.twig` | mission | ✅ Rang et urgence |
| Puces de filtre | `.chip`, `.chip-group`, `[aria-current="true"]` | — | ✅ Pilules ; état actif olive |
| Bouton | `.btn--primary`, `--dark`, `--amber`, `--danger`, `--secondary`, `--soft`, `--sm`, `--lg`, `--block` | — | ✅ Pilule, `scale(.98)` à l'appui, bordure secondaire de 1,5px `#607954` et survol teinté à 8 % ; ⛔ pas de halo pulsé sur le bouton d'urgence |
| Carte | `.card`, `.card--tinted`, `.card--hud`, `.panel` | — | ✅ ; rayon de 2rem au lieu de `rounded-3xl` |
| Carte de mission | `_mission_card.html.twig` | `mission`, `selected`, `url` | ✅ Lien couvrant toute la carte, boutons au premier plan |
| Jauge linéaire | `_gauge.html.twig` | `value`, `max`, `label`, `aria_label`, `display`, `tone`, `thin` | ✅ Piste en pilule |
| Anneau de progression | `_ring.html.twig` | `value`, `size`, `sublabel` | Ajout : pronostic de réussite |
| Barre de statistique | `_stat_bar.html.twig` | `label`, `icon`, `value`, `bonus` | Ajout |
| Avatar héros | `_hero_avatar.html.twig` | `hero`, `size`, `square`, `status` | Initiales si pas d'image, pastille de statut |
| Timer | `_timer.html.twig` | `mission`, `big` | ✅ Police monospace, ambre en grand format |
| Bandeau d'urgence | `_urgent_banner.html.twig` | `alerte` | ✅ |
| Carte tactique | `_tactical_map.html.twig` | `carte`, `selection`, `hud` | 🟡 SVG abstrait ; ⛔ « Hero Threat Radar Card » |
| Pagination | `_pagination.html.twig` | — | ✅ Pilule |
| Messages flash | `_flashes.html.twig`, `.flash--{success,danger,warning}` | — | Ajout |
| Icône | `_icon.html.twig` | `name`, `label` | Material Symbols Outlined |
| Formulaires | `form_theme.html.twig` (global), `.field`, `.form-grid`, `.choice-grid` | — | Voir ci-dessous |

### Formulaires

| Élément | Prévu | Utilisé |
|---|---|---|
| Champs texte | Pilule, fond `#F4F6F2`, ombre intérieure, contour de focus de 1,5px olive | ✅ Pilule, fond `--surface-low`, ombre intérieure, bordure olive au focus |
| Zone de texte | — | Rayon `--r-md` |
| Curseurs | Piste de 6 px avec poignée rainurée | 🟡 Natifs, avec `accent-color` |
| Cases à cocher | Carrés arrondis avec glyphe personnalisé | 🟡 Natives, avec `accent-color` |
| Contrôle segmenté | Capsule avec curseur coulissant | ⛔ Remplacé par des boutons radio en pilules (`.choice-grid`) |
| Erreurs | — | `.form-errors`, `.field--error` |

### Composants prévus, absents

- « Live Biometric Telemetry Strip » (sparkline de pouls ou de puissance) ;
- « Hero Threat Radar Card » (boussole de proximité) ;
- bouton d'urgence avec halo pulsé ;
- contrôle segmenté et curseurs personnalisés.

## Graphiques

- **Radar** (`radar_chart_controller.js`, Chart.js 4.6.1) : cinq statistiques de 0 à 100, remplissage
  `rgba(75,94,64,.35)`, trait `#34462a`, police Inter. L'animation est désactivée si
  `prefers-reduced-motion` est actif.
- **Jauges et anneaux** : en CSS ou SVG, sans bibliothèque.
- **Carte** : SVG avec une grille, des zones de menace (`.map-zone--ok/warn/danger`) et des marqueurs.

## Responsive

| Point de rupture | Prévu | Utilisé (`app.css`) |
|---|---|---|
| ≥ 1200 px | 12 colonnes, trois volets | Barre latérale de 18rem ; grilles à deux colonnes |
| 768–1199 px | 8 colonnes ; panneaux secondaires en bas ou en onglets | Barre latérale de 15rem ; les grilles principales passent sur une colonne ; HUD de la carte sous la carte, plus en surimpression ; `.grid-4` passe en 2 colonnes |
| < 768 px | 4 colonnes, cartes empilées | Barre latérale horizontale (navigation défilante, pied masqué) ; toutes les grilles sur une colonne ; carte de 24rem minimum ; titres réduits |

## Accessibilité

Ces règles sont vérifiées dans les templates et le CSS.

- **Navigation au clavier** :
  - lien d'évitement « Aller au contenu » (`.skip-link`) vers `<main id="contenu">` ;
  - focus visible global (`:focus-visible`, contour de 2px) ;
  - `aria-current="page"` sur la navigation active et `aria-current="true"` sur les puces de filtre.
- **Structure** :
  - `lang="fr"` ;
  - sections nommées par `aria-labelledby` (28 occurrences) et `aria-label` (32) ;
  - `role="search"` sur la recherche globale ;
  - libellés `.sr-only` pour les champs sans libellé visible.
- **Composants dynamiques** :
  - jauges `role="progressbar"` avec `aria-valuemin`, `aria-valuemax` et `aria-valuenow` ;
  - timer `role="timer"` avec `aria-live="off"`, pour ne pas annoncer chaque seconde ;
  - bandeau d'urgence `role="alert"`.
- **Graphiques** :
  - le radar est un `canvas` avec `role="img"` et un `aria-label` qui énonce les valeurs ;
  - les barres textuelles restent l'alternative ;
  - les marqueurs de la carte sont des liens avec `aria-label` (nom et distance).
- **Icônes** : décoratives (`aria-hidden="true"`), sauf si un `label` est fourni. Si la police
  d'icônes ne charge pas, une boîte de largeur fixe évite que le nom de ligature ne casse la mise en page.
- **Mouvement** : `prefers-reduced-motion` coupe la pulsation et les transitions CSS, ainsi que l'animation Chart.js.
- **Non vérifié** : les contrastes n'ont pas été mesurés avec un outil, et aucun audit
  automatisé (axe, Lighthouse) n'a été exécuté.
