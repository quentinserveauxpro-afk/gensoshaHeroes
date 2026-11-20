/*
 * Boucle de rafraîchissement unique (aucune dépendance, testable sous Node).
 *
 * - une seule boucle par instance : un setTimeout réarmé après chaque réponse, jamais setInterval ;
 * - AbortController : la requête précédente encore active est annulée avant d'en lancer une autre ;
 * - onglet masqué (visibilitychange) : arrêt complet ; onglet visible : reprise immédiate ;
 * - aucune requête si url() renvoie null (aucun composant à actualiser) ;
 * - erreurs réseau : signalées sans exception ; délai progressif après plusieurs échecs.
 */
export class LivePoller {
    /**
     * @param {object} o
     * @param {() => (string|null)} o.url        URL de la prochaine requête (null : rien à suivre)
     * @param {(data: any) => void} o.onData     données reçues
     * @param {(err: any, echecs: number) => void} [o.onError]
     * @param {number} [o.interval]              intervalle nominal (ms), 3000 par défaut
     * @param {number} [o.maxDelay]              délai maximal après erreurs (ms)
     */
    constructor({ url, onData, onError = () => {}, interval = 3000, maxDelay = 30000, doc = globalThis.document, fetchImpl = (...a) => globalThis.fetch(...a), timers = globalThis }) {
        Object.assign(this, { url, onData, onError, interval, maxDelay, doc, fetchImpl, timers });
        this.echecs = 0;
        this.minuteur = null;
        this.controleur = null;
        this.actif = false;
        this.surVisibilite = () => (this.doc.hidden ? this.suspendre() : this.reprendre());
    }

    start() {
        if (this.actif) return;
        this.actif = true;
        this.doc.addEventListener('visibilitychange', this.surVisibilite);
        this.reprendre();
    }

    stop() {
        this.actif = false;
        this.doc.removeEventListener('visibilitychange', this.surVisibilite);
        this.suspendre();
    }

    /** Actualisation immédiate (changement de sélection, retour au premier plan). */
    reprendre() {
        if (!this.actif || this.doc.hidden) return;
        this.annulerMinuteur();
        this.tick();
    }

    suspendre() {
        this.annulerMinuteur();
        if (this.controleur) {
            this.controleur.abort();
            this.controleur = null;
        }
    }

    /** Délai avant la prochaine requête : nominal, puis doublé à partir du 2e échec consécutif. */
    delai() {
        if (this.echecs < 2) return this.interval;
        return Math.min(this.maxDelay, this.interval * 2 ** (this.echecs - 1));
    }

    annulerMinuteur() {
        if (this.minuteur !== null) {
            this.timers.clearTimeout(this.minuteur);
            this.minuteur = null;
        }
    }

    planifier() {
        this.annulerMinuteur();
        if (this.actif && !this.doc.hidden) {
            this.minuteur = this.timers.setTimeout(() => { this.minuteur = null; this.tick(); }, this.delai());
        }
    }

    async tick() {
        const url = this.url();
        if (!url) {
            this.planifier();
            return;
        }
        if (this.controleur) this.controleur.abort();
        const controleur = new AbortController();
        this.controleur = controleur;
        try {
            const reponse = await this.fetchImpl(url, {
                headers: { Accept: 'application/json' }, cache: 'no-store', credentials: 'same-origin', signal: controleur.signal,
            });
            if (!reponse.ok) throw new Error(`HTTP ${reponse.status}`);
            const donnees = await reponse.json();
            if (controleur.signal.aborted) return;
            this.echecs = 0;
            this.onData(donnees);
        } catch (e) {
            if (controleur.signal.aborted) return; // annulée volontairement : une autre requête a pris le relais
            this.echecs += 1;
            this.onError(e, this.echecs);
        } finally {
            if (this.controleur === controleur) this.controleur = null;
        }
        this.planifier();
    }
}
