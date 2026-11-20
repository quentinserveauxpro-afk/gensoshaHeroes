import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';
import 'leaflet/dist/leaflet.min.css';

/*
 * Carte réelle Leaflet + OpenStreetMap (France métropolitaine et outre-mer, sans bornes
 * artificielles). Deux usages :
 *  - carte tactique : héros, missions, vilains et trajets mis à jour à chaque événement
 *    « genosha:etat » (instantané serveur) ;
 *  - mini-carte statique : liste de points (fiche requête).
 *
 * Trajets : segments en ligne droite (grand cercle) entre le départ et la mission —
 * simulation, pas un itinéraire routier. Les textes des infobulles sont insérés via
 * textContent (aucune injection HTML depuis les données).
 */
const TONS = { ok: '#4b5e40', warn: '#f59e0b', danger: '#ba1a1a', muted: '#74796d' };
const PHASES = { PLANIFIEE: 'Planifiée', APPROCHE: 'Équipe en approche', ENGAGEMENT: 'Engagement en cours', TERMINEE: 'Terminée', ECHOUEE: 'Échouée' };

export default class extends Controller {
    static targets = ['carte', 'gabaritHero', 'gabaritMission', 'compteurMissions', 'compteurHeros', 'compteurRoute', 'liste', 'annonce'];
    static values = {
        config: Object,
        etat: Object,
        points: Array,
        selection: Number,
        missionUrl: String,
    };

    connect() {
        const conteneur = this.hasCarteTarget ? this.carteTarget : this.element;
        const c = this.configValue;
        this.carte = L.map(conteneur, { zoomControl: true, worldCopyJump: true }).setView([c.centre.lat, c.centre.lng], c.zoom);
        L.tileLayer(c.tuiles, { maxZoom: 19, attribution: c.attribution }).addTo(this.carte);

        if (this.pointsValue.length > 0) {
            this.afficherPoints();
            return;
        }

        this.calques = {
            missions: L.layerGroup().addTo(this.carte),
            heros: L.layerGroup().addTo(this.carte),
            vilains: L.layerGroup().addTo(this.carte),
            trajets: L.layerGroup().addTo(this.carte),
        };
        this.marqueursHeros = new Map();
        this.marqueursMissions = new Map();
        this.marqueursVilains = new Map();
        this.trajets = new Map();
        this.dejaCadre = false;
        this.statutsPrecedents = new Map();

        L.marker([c.qg.lat, c.qg.lng], { icon: this.icone('map-pin map-pin--qg', 'QG'), title: c.qg.label, keyboard: false })
            .bindTooltip(c.qg.label).addTo(this.carte);

        this.surEtat = (e) => this.appliquer(e.detail);
        window.addEventListener('genosha:etat', this.surEtat);
        if (this.etatValue && this.etatValue.heros) {
            this.appliquer(this.etatValue);
        }
    }

    disconnect() {
        window.removeEventListener('genosha:etat', this.surEtat);
        this.carte?.remove();
    }

    // --- actions ---------------------------------------------------------

    aller(event) {
        const { lat, lng, zoom } = event.params;
        this.carte.flyTo([lat, lng], zoom ?? 9, { duration: 0.8 });
    }

    recadrer() {
        this.dejaCadre = false;
        this.cadrer(this.dernierEtat);
    }

    basculer(event) {
        const calque = this.calques[event.params.calque];
        if (!calque) return;
        if (event.target.checked) calque.addTo(this.carte); else calque.remove();
    }

    // --- rendu -----------------------------------------------------------

    afficherPoints() {
        const groupe = L.featureGroup().addTo(this.carte);
        this.pointsValue.forEach((p) => L.marker([p.lat, p.lng], { icon: this.icone('map-pin map-pin--mission', '!'), title: p.label }).addTo(groupe));
        this.carte.setView(groupe.getBounds().getCenter(), 14);
    }

    appliquer(etat) {
        if (!etat || !this.calques) return;
        this.dernierEtat = etat;
        const missions = new Map(etat.missions.map((m) => [m.id, m]));

        this.synchroniser(this.marqueursMissions, etat.missions.filter((m) => m.position), this.calques.missions, (m) => this.marqueurMission(m), (marqueur, m) => this.majMission(marqueur, m));
        this.synchroniser(this.marqueursHeros, etat.heros, this.calques.heros, (h) => this.marqueurHero(h, missions), (marqueur, h) => this.majHero(marqueur, h, missions));

        const vilains = etat.missions.flatMap((m) => m.vilains.filter((v) => v.position).map((v) => ({ ...v, cle: `${m.id}-${v.id}` })));
        this.synchroniser(this.marqueursVilains, vilains, this.calques.vilains, (v) => L.marker([v.position.lat, v.position.lng], {
            icon: this.icone('map-pin map-pin--vilain', '☠'), title: `Vilain : ${v.nom} (menace ${v.menace}/10)`, keyboard: false,
        }).bindTooltip(`${v.nom} — menace ${v.menace}/10 — ${v.statut}`), (marqueur, v) => marqueur.setLatLng([v.position.lat, v.position.lng]), (v) => v.cle);

        const trajets = etat.heros.filter((h) => h.deplacement && h.deplacement.statut === 'EN_DEPLACEMENT');
        this.synchroniser(this.trajets, trajets, this.calques.trajets, (h) => L.polyline(this.ligne(h), { color: TONS.warn, weight: 2, dashArray: '6 6', opacity: 0.8, interactive: false }),
            (ligne, h) => ligne.setLatLngs(this.ligne(h)));

        this.majCompteurs(etat);
        this.annoncerArrivees(etat);
        this.cadrer(etat);
    }

    /** Ajoute, met à jour ou retire les marqueurs pour coller exactement à l'instantané. */
    synchroniser(index, elements, calque, creer, maj, cle = (e) => e.id) {
        const vus = new Set();
        elements.forEach((e) => {
            const k = cle(e);
            vus.add(k);
            let marqueur = index.get(k);
            if (!marqueur) {
                marqueur = creer(e);
                index.set(k, marqueur);
                calque.addLayer(marqueur);
            }
            maj(marqueur, e);
        });
        [...index.keys()].filter((k) => !vus.has(k)).forEach((k) => {
            calque.removeLayer(index.get(k));
            index.delete(k);
        });
    }

    marqueurMission(m) {
        const marqueur = L.marker([m.position.lat, m.position.lng], { riseOnHover: true, title: `Mission ${m.nom}` });
        marqueur.bindPopup(() => this.popupMission(marqueur.donnees), { maxWidth: 300 });
        return marqueur;
    }

    majMission(marqueur, m) {
        marqueur.donnees = m;
        marqueur.setLatLng([m.position.lat, m.position.lng]);
        const classes = ['map-pin', 'map-pin--mission', `map-pin--${m.urgence.toLowerCase()}`];
        if (m.critique) classes.push('map-pin--critique');
        if (m.id === this.selectionValue) classes.push('map-pin--selection');
        marqueur.setIcon(this.icone(classes.join(' '), m.phase === 'ENGAGEMENT' ? '⚔' : '!'));
        marqueur.options.title = `Mission ${m.nom} — urgence ${m.urgenceLabel} — ${PHASES[m.phase] ?? m.phase}`;
        if (marqueur.isPopupOpen()) marqueur.setPopupContent(this.popupMission(m));
    }

    marqueurHero(h, missions) {
        const marqueur = L.marker([h.position.lat, h.position.lng], { riseOnHover: true, title: h.nom });
        marqueur.bindPopup(() => this.popupHero(marqueur.donnees, missions), { maxWidth: 300 });
        return marqueur;
    }

    majHero(marqueur, h, missions) {
        marqueur.donnees = h;
        marqueur.setLatLng([h.position.lat, h.position.lng]);
        const enRoute = h.deplacement && h.deplacement.statut === 'EN_DEPLACEMENT';
        const classes = ['map-pin', 'map-pin--hero', `map-pin--${h.tone}`];
        if (enRoute) classes.push('map-pin--route');
        if (h.critique) classes.push('map-pin--critique');
        marqueur.setIcon(this.icone(classes.join(' '), h.initiales, `--sante:${h.sante}%`));
        if (marqueur.isPopupOpen()) marqueur.setPopupContent(this.popupHero(h, missions));
    }

    popupHero(h, missions) {
        const n = this.gabaritHeroTarget.content.cloneNode(true);
        const mission = h.missionId ? missions.get(h.missionId) : null;
        const d = h.deplacement;
        this.remplir(n, {
            nom: h.nom,
            statutLabel: h.statutLabel,
            role: h.role,
            santeTexte: `${h.pv} / ${h.pvMax} PV (${h.santeLabel})`,
            missionNom: mission ? mission.nom : 'Aucune',
            deplacementTexte: d ? `${d.statutLabel} (${d.progression} %)` : (h.affectation ?? '—'),
            distanceTexte: d ? `${this.km(d.distanceRestanteKm)} sur ${this.km(d.distanceTotaleKm)}` : (h.distanceMissionKm !== null ? this.km(h.distanceMissionKm) : '—'),
            etaTexte: d && d.statut === 'EN_DEPLACEMENT' ? `${this.duree(d.secondesRestantes)} (arrivée ${new Date(d.arriveePrevue).toLocaleTimeString('fr-FR')})` : (d && d.arriveeReelle ? `Arrivé (${new Date(d.arriveeReelle).toLocaleTimeString('fr-FR')})` : '—'),
        }, { url: h.url });
        this.jauge(n, 'sante', h.sante, h.sante <= 25 ? 'danger' : (h.sante <= 50 ? 'warn' : 'ok'));
        n.querySelector('[data-tone-champ]')?.classList.add(`badge--${h.tone}`);
        return this.conteneur(n);
    }

    popupMission(m) {
        const n = this.gabaritMissionTarget.content.cloneNode(true);
        this.remplir(n, {
            nom: m.nom,
            urgenceLabel: `Urgence ${m.urgenceLabel.toLowerCase()}`,
            localisation: m.localisation ?? '',
            phaseLabel: PHASES[m.phase] ?? m.phase,
            tauxTexte: `Réussite estimée : ${m.taux} %`,
            progressionTexte: `${m.progression} %`,
            menaceTexte: m.menace !== null ? `Menace : ${Math.round(m.menace)} / 100 (${m.menaceLabel})` : 'Menace : évaluation à l\'arrivée',
            equipeTexte: `${m.equipe.length} héros affecté(s)`,
        }, { url: m.url });
        this.jauge(n, 'progression', m.progression);
        return this.conteneur(n);
    }

    majCompteurs(etat) {
        const enRoute = etat.heros.filter((h) => h.deplacement && h.deplacement.statut === 'EN_DEPLACEMENT').length;
        if (this.hasCompteurMissionsTarget) this.compteurMissionsTarget.textContent = String(etat.missions.length);
        if (this.hasCompteurHerosTarget) this.compteurHerosTarget.textContent = String(etat.heros.length);
        if (this.hasCompteurRouteTarget) this.compteurRouteTarget.textContent = String(enRoute);
        if (this.hasListeTarget) {
            // Équivalent textuel de la carte pour les technologies d'assistance.
            const items = etat.heros.filter((h) => h.missionId).map((h) => {
                const li = document.createElement('li');
                const d = h.deplacement;
                li.textContent = `${h.nom} — ${h.statutLabel}, santé ${h.sante} %` + (d ? `, ${d.statutLabel.toLowerCase()}, ${this.km(d.distanceRestanteKm)} restants` + (d.statut === 'EN_DEPLACEMENT' ? `, arrivée dans ${this.duree(d.secondesRestantes)}` : '') : '');
                return li;
            });
            this.listeTarget.replaceChildren(...items);
        }
    }

    annoncerArrivees(etat) {
        etat.heros.forEach((h) => {
            const statut = h.deplacement?.statut;
            const avant = this.statutsPrecedents.get(h.id);
            if (avant === 'EN_DEPLACEMENT' && statut === 'ARRIVE' && this.hasAnnonceTarget) {
                this.annonceTarget.textContent = `${h.nom} est arrivé sur place.`;
            }
            this.statutsPrecedents.set(h.id, statut);
        });
    }

    cadrer(etat) {
        if (this.dejaCadre || !etat) return;
        const points = [
            ...etat.missions.filter((m) => m.position).map((m) => [m.position.lat, m.position.lng]),
            ...etat.heros.filter((h) => h.missionId).map((h) => [h.position.lat, h.position.lng]),
        ];
        const choisie = etat.missions.find((m) => m.id === this.selectionValue && m.position);
        if (choisie) {
            this.carte.setView([choisie.position.lat, choisie.position.lng], 11);
        } else if (points.length > 1) {
            this.carte.fitBounds(L.latLngBounds(points), { padding: [40, 40], maxZoom: 12 });
        } else if (points.length === 1) {
            this.carte.setView(points[0], 11);
        }
        this.dejaCadre = true;
    }

    // --- utilitaires -----------------------------------------------------

    ligne(h) {
        const d = h.deplacement;
        return [[h.position.lat, h.position.lng], [d.arrivee.lat, d.arrivee.lng]];
    }

    icone(classes, texte, style = '') {
        const span = document.createElement('span');
        span.className = 'map-pin__corps';
        span.textContent = texte;
        if (style) span.setAttribute('style', style);
        return L.divIcon({ className: classes, html: span.outerHTML, iconSize: [34, 34], iconAnchor: [17, 17], popupAnchor: [0, -16] });
    }

    remplir(fragment, champs, liens) {
        Object.entries(champs).forEach(([cle, valeur]) => {
            fragment.querySelectorAll(`[data-champ="${cle}"]`).forEach((el) => { el.textContent = valeur; });
        });
        Object.entries(liens).forEach(([cle, valeur]) => {
            fragment.querySelectorAll(`[data-lien="${cle}"]`).forEach((el) => el.setAttribute('href', valeur));
        });
    }

    jauge(fragment, nom, valeur, ton = null) {
        const jauge = fragment.querySelector(`[data-jauge="${nom}"]`);
        if (!jauge) return;
        const v = Math.max(0, Math.min(100, Math.round(valeur)));
        if (ton) jauge.classList.add(`gauge--${ton}`);
        jauge.querySelector('.gauge__fill').style.width = `${v}%`;
        const piste = jauge.querySelector('[role="progressbar"]');
        piste.setAttribute('aria-valuenow', String(v));
    }

    conteneur(fragment) {
        const div = document.createElement('div');
        div.appendChild(fragment);
        return div;
    }

    km(valeur) {
        return `${Number(valeur).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} km`;
    }

    /* Minuterie « MM:SS » (09:42, 00:08) ; jamais négative, NaN ni infinie. */
    duree(secondes) {
        const n = Number(secondes);
        if (secondes === null || secondes === undefined || !Number.isFinite(n)) return '—';
        const s = Math.max(0, Math.round(n));
        const mm = String(Math.floor((s % 3600) / 60)).padStart(2, '0');
        const ss = String(s % 60).padStart(2, '0');
        return s >= 3600 ? `${Math.floor(s / 3600)}:${mm}:${ss}` : `${mm}:${ss}`;
    }
}
