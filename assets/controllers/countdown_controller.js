import { Controller } from '@hotwired/stimulus';

/*
 * Compte à rebours de mission.
 * - La valeur initiale (secondes restantes) est calculée par le serveur (MissionTimer).
 * - Le navigateur décrémente localement à partir d'une horloge monotone (performance.now),
 *   ce qui le rend insensible à l'heure système du poste.
 * - Resynchronisation périodique via l'API serveur, qui reste la source de vérité.
 * - Jamais de durée négative : « Mission terminée » à l'échéance.
 */
export default class extends Controller {
    static targets = ['output'];
    static values = { seconds: Number, url: String, resync: { type: Number, default: 60 } };

    connect() {
        this.reference = this.secondsValue;
        this.origine = performance.now();
        this.tick = this.tick.bind(this);
        this.render();
        this.interval = window.setInterval(this.tick, 1000);
        if (this.urlValue) {
            this.resyncTimer = window.setInterval(() => this.resynchroniser(), this.resyncValue * 1000);
        }
    }

    disconnect() {
        window.clearInterval(this.interval);
        window.clearInterval(this.resyncTimer);
    }

    restantes() {
        const ecoulees = Math.floor((performance.now() - this.origine) / 1000);
        return Math.max(0, this.reference - ecoulees);
    }

    tick() {
        this.render();
        if (this.restantes() === 0) {
            window.clearInterval(this.interval);
        }
    }

    render() {
        const s = this.restantes();
        if (s <= 0) {
            this.outputTarget.textContent = 'Mission terminée';
            this.element.classList.add('timer--done');
            return;
        }
        const h = String(Math.floor(s / 3600)).padStart(2, '0');
        const m = String(Math.floor((s % 3600) / 60)).padStart(2, '0');
        const sec = String(s % 60).padStart(2, '0');
        this.outputTarget.textContent = `${h}:${m}:${sec}`;
    }

    async resynchroniser() {
        try {
            const reponse = await fetch(this.urlValue, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!reponse.ok) return;
            const data = await reponse.json();
            this.reference = Math.max(0, Number(data.secondesRestantes) || 0);
            this.origine = performance.now();
            this.render();
            if (this.reference > 0 && !this.interval) {
                this.interval = window.setInterval(this.tick, 1000);
            }
        } catch (e) {
            // Hors ligne : on conserve le décompte local.
        }
    }
}
