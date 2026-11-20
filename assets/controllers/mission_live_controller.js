import { Controller } from '@hotwired/stimulus';

/*
 * Fiche mission : met à jour taux, progression, menace, phase, objectifs, jauges de santé
 * et journal à partir des instantanés serveur (événement « genosha:etat »).
 */
const PHASES = { PLANIFIEE: 'Planifiée', APPROCHE: 'Équipe en approche', ENGAGEMENT: 'Engagement en cours', TERMINEE: 'Terminée', ECHOUEE: 'Échouée' };

export default class extends Controller {
    static targets = ['taux', 'progression', 'menace', 'phase', 'journal'];
    static values = { id: Number };

    connect() {
        this.surEtat = (e) => this.appliquer(e.detail);
        window.addEventListener('genosha:etat', this.surEtat);
    }

    disconnect() {
        window.removeEventListener('genosha:etat', this.surEtat);
    }

    appliquer(etat) {
        const m = etat.missions.find((x) => x.id === this.idValue);
        if (!m) {
            // Rechargement unique, seulement si la mission vient de se terminer sous nos yeux
            // (elle figurait dans un instantané précédent) : jamais de boucle de rechargement.
            if (this.vueActive && !this.recharge) {
                this.recharge = true;
                window.location.reload();
            }
            return;
        }
        this.vueActive = true;
        if (this.hasTauxTarget) this.tauxTarget.textContent = `${m.taux} %`;
        if (this.hasProgressionTarget) this.progressionTarget.textContent = `${m.progression} %`;
        if (this.hasMenaceTarget) this.menaceTarget.textContent = m.menace !== null ? `${Math.round(m.menace)} (${m.menaceLabel})` : '—';
        if (this.hasPhaseTarget) this.phaseTarget.textContent = PHASES[m.phase] ?? m.phase;

        m.objectifs.forEach((o) => {
            document.querySelectorAll(`[data-objectif="${o.id}"]`).forEach((li) => {
                li.className = `objective objective--${o.termine ? 'termine' : (o.progression > 0 ? 'en_cours' : 'en_attente')}`;
                const texte = li.querySelector('[data-objectif-texte]');
                if (texte) texte.textContent = o.termine ? 'Atteint' : `${o.progression} %`;
                this.majJauge(li, o.progression);
            });
        });

        etat.heros.filter((h) => h.missionId === m.id).forEach((h) => {
            document.querySelectorAll(`[data-equipe-hero="${h.id}"]`).forEach((li) => {
                const d = h.deplacement;
                const statut = li.querySelector('[data-deplacement-statut]');
                if (statut) statut.textContent = d ? d.statutLabel : (h.affectation ?? '');
                const texte = li.querySelector('[data-deplacement-texte]');
                if (texte && d) {
                    texte.textContent = d.statut === 'EN_DEPLACEMENT'
                        ? `${d.distanceRestanteKm.toLocaleString('fr-FR')} km restants • arrivée ${new Date(d.arriveePrevue).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`
                        : `${h.santeLabel} • sur place`;
                }
            });
            document.querySelectorAll(`[data-sante-hero="${h.id}"]`).forEach((g) => {
                const ton = h.sante <= 25 ? 'danger' : (h.sante <= 50 ? 'warn' : 'ok');
                g.classList.remove('gauge--ok', 'gauge--warn', 'gauge--danger');
                g.classList.add(`gauge--${ton}`);
                g.querySelector('[data-sante-texte]').textContent = `${h.pv} / ${h.pvMax} PV`;
                g.querySelector('[data-sante-etat]').textContent = h.santeLabel;
                this.majJauge(g, h.sante, `${h.pv} points de vie sur ${h.pvMax} (${h.santeLabel.toLowerCase()})`, h.pv);
            });
        });

        if (this.hasJournalTarget && m.journal.length > 0) {
            const items = [...m.journal].reverse().map((e) => {
                const li = document.createElement('li');
                li.className = e.niveau === 'danger' ? 'text-danger' : (e.niveau === 'ok' ? 'text-ok' : 'text-muted');
                const t = document.createElement('time');
                t.className = 'label-md muted';
                t.textContent = new Date(e.t).toLocaleTimeString('fr-FR');
                li.append(t, ` ${e.message}`);
                return li;
            });
            this.journalTarget.replaceChildren(...items);
        }
    }

    majJauge(racine, pct, texte = null, valeur = null) {
        const fill = racine.querySelector('.gauge__fill');
        if (fill) fill.style.width = `${Math.max(0, Math.min(100, pct))}%`;
        const piste = racine.querySelector('[role="progressbar"]');
        if (piste) {
            piste.setAttribute('aria-valuenow', String(valeur ?? pct));
            if (texte) piste.setAttribute('aria-valuetext', texte);
        }
    }
}
