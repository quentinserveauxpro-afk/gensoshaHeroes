import { Controller } from '@hotwired/stimulus';

/*
 * Mise à jour en direct de la page Infirmerie, sans rechargement.
 *
 * Aucune boucle propre : le contrôleur écoute l'événement « genosha:etat » diffusé par
 * l'abonnement temps réel unique de la page (realtime_controller : SSE, repli fetch).
 * Les valeurs affichées (santé, fatigue, statut, temps restant, lits) sont celles calculées
 * par le serveur ; les transitions CSS des barres sont purement visuelles.
 *
 * Une admission ou une sortie (ensemble des séjours modifié) n'est pas recomposée en
 * JavaScript : un message propose d'actualiser la page, jamais de rechargement automatique.
 */
export default class extends Controller {
    static targets = ['annonce'];

    connect() {
        this.surEtat = (e) => this.appliquer(e.detail);
        window.addEventListener('genosha:etat', this.surEtat);
    }

    disconnect() {
        window.removeEventListener('genosha:etat', this.surEtat);
    }

    appliquer(etat) {
        const inf = etat && etat.infirmerie;
        if (!inf) {
            return;
        }
        this.indicateurs(inf.indicateurs);
        const recus = new Set();
        for (const s of inf.sejours) {
            recus.add(String(s.id));
            const carte = this.element.querySelector(`[data-sejour="${s.id}"]`);
            if (carte) {
                this.carte(carte, s);
            }
        }
        // Séjours affichés qui ne sont plus actifs (sortie) ; nombre total différent (admission).
        const partis = [...this.element.querySelectorAll('[data-sejour]')].filter((c) => !recus.has(c.dataset.sejour)).length;
        this.totalInitial ??= inf.sejours.length;
        if (partis > 0) {
            this.annoncer(`${partis} séjour(s) terminé(s) depuis l'affichage. Actualisez la page pour mettre la liste à jour.`);
        } else if (inf.sejours.length > this.totalInitial) {
            this.annoncer('De nouveaux héros ont été admis. Actualisez la page pour les afficher.');
        }
    }

    indicateurs(k) {
        for (const cle of ['retours', 'traitements', 'retablis', 'litsDisponibles']) {
            const el = this.element.querySelector(`[data-kpi="${cle}"]`);
            if (el) el.textContent = String(k[cle]);
        }
        const moyenne = this.element.querySelector('[data-kpi="dureeMoyenne"]');
        if (moyenne) moyenne.textContent = this.duree(k.dureeMoyenne);
        const complet = this.element.querySelector('[data-kpi="complet"]');
        if (complet) complet.hidden = k.litsDisponibles > 0;
    }

    carte(carte, s) {
        const champ = (nom) => carte.querySelector(`[data-champ="${nom}"]`);
        carte.dataset.statut = s.statut;

        const statut = champ('statut');
        statut.textContent = s.statutLabel;
        statut.className = `badge badge--${s.tone}`;
        statut.dataset.tone = s.tone;

        this.jauge(champ('jauge-sante'), champ('sante-barre'), s.sante, s.sante <= 20 ? 'danger' : (s.sante <= 50 ? 'warn' : 'ok'));
        champ('sante-texte').textContent = `${s.pv} / ${s.pvMax} PV (${s.sante} %)`;
        this.jauge(champ('jauge-fatigue'), champ('fatigue-barre'), s.fatigue, s.fatigue >= 90 ? 'danger' : (s.fatigue >= 50 ? 'warn' : 'ok'));
        champ('fatigue-texte').textContent = `${s.fatigue} %`;
        champ('alerte-sante').hidden = !s.santeCritique;
        champ('alerte-fatigue').hidden = !s.epuise;

        champ('restant-libelle').textContent = s.statut === 'RETOUR_QG' ? 'Arrivée au QG' : 'Rétablissement';
        champ('restant').textContent = s.statut === 'RETABLI' ? 'Atteint' : (s.secondesRestantes === null ? '—' : `dans ${this.duree(s.secondesRestantes)}`);
        champ('taux').textContent = `${s.tauxSanteHeure} PV/h • −${s.tauxFatigueHeure} fatigue/h`;

        const retour = champ('retour');
        retour.hidden = !s.retour;
        if (s.retour) {
            const barre = champ('retour-barre');
            barre.setAttribute('aria-valuenow', String(s.retour.progression));
            barre.firstElementChild.style.width = `${s.retour.progression}%`;
            champ('retour-texte').textContent = `${String(s.retour.distanceRestanteKm).replace('.', ',')} km restants • position ${s.retour.position.lat.toFixed(4).replace('.', ',')}, ${s.retour.position.lng.toFixed(4).replace('.', ',')}`;
        }
        const sortie = champ('sortie');
        if (sortie) sortie.hidden = s.statut !== 'RETABLI';
    }

    jauge(conteneur, barre, valeur, ton) {
        conteneur.classList.remove('gauge--ok', 'gauge--warn', 'gauge--danger');
        conteneur.classList.add(`gauge--${ton}`);
        barre.setAttribute('aria-valuenow', String(valeur));
        barre.firstElementChild.style.width = `${Math.max(0, Math.min(100, valeur))}%`;
    }

    annoncer(texte) {
        if (!this.hasAnnonceTarget || this.annonceTarget.textContent === texte) return;
        this.annonceTarget.hidden = false;
        this.annonceTarget.textContent = texte;
    }

    duree(secondes) {
        if (secondes === null || secondes === undefined) return '—';
        const s = Math.max(0, secondes);
        const h = Math.floor(s / 3600);
        const m = Math.floor((s % 3600) / 60);
        if (h >= 24) return `${Math.floor(h / 24)} j ${String(h % 24).padStart(2, '0')} h`;
        if (h > 0) return `${h} h ${String(m).padStart(2, '0')} min`;
        if (m > 0) return `${m} min`;
        return `${s} s`;
    }
}
