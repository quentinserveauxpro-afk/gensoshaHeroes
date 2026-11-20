import { Controller } from '@hotwired/stimulus';
import { LivePoller } from '../lib/live_poller.js';

/*
 * Rafraîchissement automatique des fiches de mission (page Missions, centre tactique).
 *
 * Une seule boucle par page (LivePoller : 3 s, AbortController, pause onglet masqué, délai
 * progressif après erreurs). Une seule requête par cycle :
 *   - GET /missions/live-status?ids=… pour toutes les fiches visibles ;
 *   - GET /missions/{id}/live-status quand seule la mission sélectionnée est suivie.
 * Le serveur est la source de vérité : ce contrôleur n'invente aucun statut et ne met à jour
 * que les éléments dont la valeur a changé (jamais de remplacement global du HTML).
 *
 * Repères dans les fiches (attributs data-* stables) :
 *   [data-mission-id] fiche • [data-mission-status] • [data-mission-progress] •
 *   [data-mission-success-rate] • [data-mission-timer] • [data-objective-id] •
 *   [data-hero-id] (+ [data-hero-health-gauge], [data-hero-fatigue-text]) •
 *   [data-mission-heroes] liste d'équipe • [data-mission-alerts] alertes • [data-live-pct] texte.
 */
const TON = (p) => (p < 40 ? 'danger' : (p < 70 ? 'warn' : 'ok'));

export default class extends Controller {
    static values = {
        url: String,
        oneUrl: String,
        selection: { type: Number, default: 0 },
        intervalle: { type: Number, default: 3 },
    };
    static targets = ['etat'];

    connect() {
        this.signatures = new Map();
        this.memoire = new Map();
        this.annonce = this.element.querySelector(':scope > [data-mission-annonce]') ?? this.creerAnnonce();
        this.poller = new LivePoller({
            url: () => this.urlSuivante(),
            onData: (d) => this.appliquer(d),
            onError: (e, echecs) => this.indiquer(echecs >= 2 ? 'Actualisation interrompue — nouvel essai…' : ''),
            interval: Math.min(5, Math.max(2, this.intervalleValue)) * 1000,
        });
        this.poller.start();
    }

    disconnect() {
        this.poller?.stop();
        this.poller = null;
    }

    /** Autre mission sélectionnée : l'ancienne n'est plus suivie, actualisation immédiate. */
    selectionValueChanged(nouvelle, ancienne) {
        if (this.poller && ancienne !== undefined && nouvelle !== ancienne) {
            this.signatures.delete(ancienne);
            this.poller.reprendre();
        }
    }

    identifiants() {
        const ids = new Set();
        this.element.querySelectorAll('[data-mission-id]').forEach((f) => {
            const id = Number.parseInt(f.dataset.missionId, 10);
            if (Number.isInteger(id) && id > 0) ids.add(id);
        });
        return [...ids];
    }

    urlSuivante() {
        const ids = this.identifiants();
        if (ids.length === 0) return null;
        if (ids.length === 1 && ids[0] === this.selectionValue && this.oneUrlValue) {
            return this.oneUrlValue.replace('__ID__', String(ids[0]));
        }
        return `${this.urlValue}?ids=${ids.join(',')}`;
    }

    appliquer(donnees) {
        this.indiquer('');
        const missions = Array.isArray(donnees?.missions) ? donnees.missions : [];
        const annonces = [];
        missions.forEach((m) => {
            const signature = JSON.stringify(m);
            if (this.signatures.get(m.id) === signature) return; // rien n'a changé
            this.signatures.set(m.id, signature);
            const fiches = this.element.querySelectorAll(`[data-mission-id="${m.id}"]`);
            fiches.forEach((fiche) => this.majFiche(fiche, m));
            annonces.push(...this.changementsImportants(m, fiches[0]));
        });
        if (annonces.length > 0) this.annonce.textContent = annonces.join(' ');
        // La carte (marqueurs) se met à jour avec les mêmes valeurs.
        window.dispatchEvent(new CustomEvent('genosha:missions-live', { detail: { serverTime: donnees?.serverTime, missions } }));
    }

    majFiche(fiche, m) {
        poserAttr(fiche, 'data-mission-urgency', m.urgency);
        fiche.classList.toggle('is-critical', Boolean(m.critical));
        fiche.querySelectorAll('[data-mission-status]').forEach((el) => {
            poserAttr(el, 'data-mission-status', m.status);
            poserTexte(el, el.dataset.format === 'lower' ? m.statusLabel.toLowerCase() : m.statusLabel);
        });
        fiche.querySelectorAll('[data-mission-progress]').forEach((el) => this.majValeur(el, 'data-mission-progress', m.progress));
        fiche.querySelectorAll('[data-mission-success-rate]').forEach((el) => this.majValeur(el, 'data-mission-success-rate', m.successRate, true));
        fiche.querySelectorAll('[data-mission-timer]').forEach((el) => this.majTimer(el, m));
        (m.objectives ?? []).forEach((o) => fiche.querySelectorAll(`[data-objective-id="${o.id}"]`).forEach((li) => this.majObjectif(li, o)));
        this.majEquipe(fiche, m.heroes ?? []);
        fiche.querySelectorAll('[data-mission-alerts]').forEach((ul) => this.majAlertes(ul, m.alerts ?? []));
    }

    /** Jauge, anneau ou simple texte : largeur, aria-valuenow, pourcentage visible. */
    majValeur(el, attribut, valeur, tonAuto = false) {
        const v = borner(valeur);
        poserAttr(el, attribut, v);
        const texte = el.querySelector('[data-live-pct]') ?? (el.children.length === 0 ? el : null);
        poserTexte(texte, `${v} %`);
        el.querySelectorAll('.gauge').forEach((g) => {
            const fill = g.querySelector('.gauge__fill');
            if (fill && fill.style.width !== `${v}%`) fill.style.width = `${v}%`;
            poserAttr(g.querySelector('[role="progressbar"]'), 'aria-valuenow', v);
            if (tonAuto) ton(g, 'gauge', TON(v));
        });
        el.querySelectorAll('.ring').forEach((r) => {
            const fg = r.querySelector('.ring__fg');
            const c = Number.parseFloat(fg?.getAttribute('stroke-dasharray'));
            if (fg && Number.isFinite(c)) poserAttr(fg, 'stroke-dashoffset', (c * (1 - v / 100)).toFixed(3));
            const label = r.querySelector('.ring__label');
            if (label && label.firstChild) {
                if (label.firstChild.nodeValue?.trim() !== `${v}%`) label.firstChild.nodeValue = `${v}%`;
            }
            poserAttr(r, 'aria-label', (r.getAttribute('aria-label') ?? '').replace(/: \d+ %$/, `: ${v} %`));
            ton(r, 'ring', TON(v));
        });
    }

    /** Resynchronise le compte à rebours sur la valeur officielle du serveur. */
    majTimer(el, m) {
        const fin = m.closed ? `Mission ${m.statusLabel.toLowerCase()}` : '';
        const secondes = String(Math.max(0, Number.parseInt(m.remainingSeconds, 10) || 0));
        poserAttr(el, 'data-countdown-fin-value', fin);
        el.setAttribute('data-countdown-seconds-value', secondes);
        this.application.getControllerForElementAndIdentifier(el, 'countdown')?.recaler(Number(secondes));
    }

    majObjectif(li, o) {
        const p = borner(o.progress);
        const etat = o.completed ? 'termine' : (p > 0 ? 'en_cours' : 'en_attente');
        poserAttr(li, 'data-objective-progress', p);
        ['termine', 'en_cours', 'en_attente'].forEach((e) => li.classList.toggle(`objective--${e}`, e === etat));
        const icone = li.querySelector('.objective__icon .material-symbols-outlined');
        if (icone) {
            poserTexte(icone, etat === 'termine' ? 'check_circle' : (etat === 'en_cours' ? 'progress_activity' : 'radio_button_unchecked'));
            poserAttr(icone, 'aria-label', etat === 'termine' ? 'Terminé' : (etat === 'en_cours' ? 'En cours' : 'En attente'));
        }
        poserTexte(li.querySelector('[data-live-pct]'), etat === 'termine' ? 'Atteint' : (etat === 'en_cours' ? `${p} %` : 'En attente'));
        li.querySelectorAll('[data-objective-gauge]').forEach((g) => {
            if (g.dataset.objectiveGauge === 'masquable') g.hidden = etat !== 'en_cours';
            this.majValeur(g, 'data-objective-gauge-value', p);
        });
    }

    majEquipe(fiche, heros) {
        const ids = heros.map((h) => String(h.id)).sort().join(',');
        fiche.querySelectorAll('[data-mission-heroes]').forEach((liste) => {
            const actuels = [...liste.querySelectorAll('[data-hero-id]')].map((e) => e.dataset.heroId).sort().join(',');
            if (actuels !== ids) liste.replaceChildren(...heros.map((h) => ligneHero(h, liste.dataset.missionHeroes)));
            liste.hidden = heros.length === 0;
        });
        heros.forEach((h) => fiche.querySelectorAll(`[data-hero-id="${h.id}"]`).forEach((el) => {
            poserAttr(el, 'data-hero-health', h.healthPercent);
            poserAttr(el, 'data-hero-fatigue', h.fatigue);
            el.classList.toggle('is-critical', Boolean(h.critical));
            el.querySelectorAll('[data-hero-health-gauge]').forEach((g) => {
                const v = borner(h.healthPercent);
                poserTexte(g.querySelector('[data-live-pct]'), `${v} %`);
                const fill = g.querySelector('.gauge__fill');
                if (fill && fill.style.width !== `${v}%`) fill.style.width = `${v}%`;
                const piste = g.querySelector('[role="progressbar"]');
                // Barre exprimée en points de vie (aria-valuemax = PV max) ou en pourcentage.
                poserAttr(piste, 'aria-valuenow', piste?.getAttribute('aria-valuemax') === '100' ? v : h.health);
                poserAttr(piste, 'aria-valuetext', `${h.health} points de vie sur ${h.maxHealth} (${v} %)`);
                ton(g.classList.contains('gauge') ? g : g.querySelector('.gauge'), 'gauge', TON(v));
            });
            el.querySelectorAll('[data-hero-fatigue-text]').forEach((t) => poserTexte(t, `Fatigue ${borner(h.fatigue)}`));
        }));
    }

    majAlertes(ul, alertes) {
        const ids = alertes.map((a) => a.id).join(',');
        if (ul.dataset.ids !== ids) {
            ul.dataset.ids = ids;
            ul.replaceChildren(...alertes.map((a) => {
                const li = document.createElement('li');
                li.className = `label-md text-${a.tone === 'danger' ? 'danger' : 'muted'}`;
                li.textContent = `⚠ ${a.title}`;
                return li;
            }));
        }
        ul.hidden = alertes.length === 0;
    }

    /** Annonces (aria-live polite) réservées aux changements importants, jamais au premier état reçu. */
    changementsImportants(m, fiche) {
        const avant = this.memoire.get(m.id);
        const apres = {
            status: m.status,
            objectifs: new Map((m.objectives ?? []).map((o) => [o.id, o.completed])),
            critiques: new Map((m.heroes ?? []).map((h) => [h.id, h.critical])),
        };
        this.memoire.set(m.id, apres);
        if (!avant) return [];
        const nom = fiche?.dataset.missionName ?? `n° ${m.id}`;
        const annonces = [];
        if (avant.status !== m.status && m.status === 'TERMINEE') annonces.push(`Mission ${nom} terminée.`);
        if (avant.status !== m.status && m.status === 'ECHOUEE') annonces.push(`Mission ${nom} échouée.`);
        apres.objectifs.forEach((fait, id) => {
            if (fait && avant.objectifs.get(id) === false) {
                const titre = this.element.querySelector(`[data-objective-id="${id}"]`)?.dataset.objectiveTitle;
                annonces.push(`Objectif ${titre ? `« ${titre} » ` : ''}atteint (mission ${nom}).`);
            }
        });
        (m.heroes ?? []).forEach((h) => {
            if (h.critical && avant.critiques.get(h.id) === false) annonces.push(`${h.name} en état critique (mission ${nom}).`);
        });
        return annonces;
    }

    creerAnnonce() {
        const div = document.createElement('div');
        div.className = 'sr-only';
        div.setAttribute('aria-live', 'polite');
        div.setAttribute('data-mission-annonce', '');
        this.element.append(div);
        return div;
    }

    indiquer(texte) {
        if (this.hasEtatTarget) {
            poserTexte(this.etatTarget, texte);
            this.etatTarget.hidden = texte === '';
        }
    }
}

function borner(v) {
    const n = Number(v);
    return Number.isFinite(n) ? Math.max(0, Math.min(100, Math.round(n))) : 0;
}

function poserTexte(el, texte) {
    if (el && el.textContent !== texte) el.textContent = texte;
}

function poserAttr(el, nom, valeur) {
    if (el && el.getAttribute(nom) !== String(valeur)) el.setAttribute(nom, String(valeur));
}

function ton(el, bloc, valeur) {
    if (!el) return;
    ['ok', 'warn', 'danger'].forEach((t) => el.classList.toggle(`${bloc}--${t}`, t === valeur));
}

/** Ligne d'équipe reconstruite quand la composition change (affectation ou retrait). */
function ligneHero(h, mode) {
    const li = document.createElement('li');
    li.dataset.heroId = String(h.id);
    li.className = mode === 'compact' ? 'label-md' : 'row';
    const v = borner(h.healthPercent);
    if (mode === 'compact') {
        const nom = document.createElement('span');
        nom.textContent = `${h.name} `;
        const sante = document.createElement('span');
        sante.dataset.heroHealthGauge = '';
        const pct = document.createElement('span');
        pct.dataset.livePct = '';
        pct.textContent = `${v} %`;
        sante.append('♥ ', pct);
        const fatigue = document.createElement('span');
        fatigue.dataset.heroFatigueText = '';
        fatigue.className = 'muted';
        fatigue.textContent = `Fatigue ${borner(h.fatigue)}`;
        li.append(nom, sante, ' · ', fatigue);
        return li;
    }
    li.innerHTML = `<div style="flex:1"><div class="gauge gauge--thin gauge--${TON(v)}" data-hero-health-gauge>
        <div class="gauge__head"><span></span><span data-live-pct>${v} %</span></div>
        <div class="gauge__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${v}"><div class="gauge__fill" style="width:${v}%"></div></div>
    </div><span class="label-md muted" data-hero-fatigue-text>Fatigue ${borner(h.fatigue)}</span></div>`;
    li.querySelector('.gauge__head span').textContent = h.name;
    li.querySelector('[role="progressbar"]').setAttribute('aria-label', `Santé de ${h.name}`);
    return li;
}
