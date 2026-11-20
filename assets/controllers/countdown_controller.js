import { Controller } from '@hotwired/stimulus';

/*
 * Compte à rebours de mission.
 * - La valeur de référence (secondes restantes) est calculée par le serveur (MissionTimer) ;
 *   au rechargement, elle repart de l'échéance enregistrée (jamais réinitialisée).
 * - Entre deux actualisations, décompte local sur une horloge monotone (performance.now),
 *   insensible à l'heure système du poste.
 * - Resynchronisation : à chaque réponse du rafraîchissement automatique (mission-status,
 *   via recaler()) ; à défaut, requête périodique vers l'API du timer.
 * - Jamais de durée négative : « 00:00 » à l'échéance ; le libellé de fin (« Mission terminée »)
 *   n'apparaît que lorsque le serveur confirme la clôture (valeur « fin »).
 */
export default class extends Controller {
    static targets = ['output'];
    static values = { seconds: Number, url: String, resync: { type: Number, default: 60 }, fin: { type: String, default: '' } };

    connect() {
        this.connecte = true;
        this.tick = this.tick.bind(this);
        this.recaler(this.secondsValue);
        // Sous le rafraîchissement groupé, pas de requête propre à chaque timer.
        if (this.urlValue && !this.element.closest('[data-controller~="mission-status"]')) {
            this.resyncTimer = window.setInterval(() => this.resynchroniser(), this.resyncValue * 1000);
        }
    }

    disconnect() {
        this.connecte = false;
        this.arreter();
        window.clearInterval(this.resyncTimer);
    }

    secondsValueChanged() {
        if (this.connecte) this.recaler(this.secondsValue);
    }

    finValueChanged() {
        if (this.connecte) this.recaler(this.secondsValue);
    }

    /** Nouvelle valeur officielle du serveur. */
    recaler(secondes) {
        const n = Number(secondes);
        this.reference = Number.isFinite(n) ? Math.max(0, Math.floor(n)) : 0;
        this.origine = performance.now();
        this.render();
        if (this.reference > 0 && !this.finValue) {
            if (!this.interval) this.interval = window.setInterval(this.tick, 1000);
        } else {
            this.arreter();
        }
    }

    arreter() {
        window.clearInterval(this.interval);
        this.interval = null;
    }

    restantes() {
        const ecoulees = Math.floor((performance.now() - this.origine) / 1000);
        return Math.max(0, this.reference - ecoulees);
    }

    tick() {
        this.render();
        if (this.restantes() === 0) this.arreter();
    }

    render() {
        if (this.finValue) {
            this.ecrire(this.finValue);
            this.element.classList.add('timer--done');
            return;
        }
        const s = this.restantes();
        this.element.classList.toggle('timer--done', s <= 0);
        if (s <= 0) {
            this.ecrire('00:00');
            return;
        }
        const h = String(Math.floor(s / 3600)).padStart(2, '0');
        const m = String(Math.floor((s % 3600) / 60)).padStart(2, '0');
        const sec = String(s % 60).padStart(2, '0');
        this.ecrire(`${h}:${m}:${sec}`);
    }

    ecrire(texte) {
        if (this.outputTarget.textContent !== texte) this.outputTarget.textContent = texte;
    }

    async resynchroniser() {
        try {
            const reponse = await fetch(this.urlValue, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
            if (!reponse.ok) return;
            const data = await reponse.json();
            this.recaler(data.secondesRestantes);
        } catch (e) {
            // Hors ligne : on conserve le décompte local.
        }
    }
}
