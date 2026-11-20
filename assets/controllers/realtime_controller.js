import { Controller } from '@hotwired/stimulus';

/*
 * Abonnement unique à la situation tactique (une connexion par page, jamais une par héros).
 *
 * Transport : Server-Sent Events (/api/temps-reel/flux) ; repli automatique en requêtes
 * périodiques (/api/temps-reel/etat) si EventSource est indisponible ou échoue trois fois.
 * Chaque instantané reçu est diffusé sur window via l'événement « genosha:etat » :
 * la carte, les panneaux et les jauges s'y abonnent. Le navigateur n'invente aucun état :
 * il affiche uniquement ce que le serveur calcule.
 *
 * Le polling est suspendu quand l'onglet est masqué, et reprend immédiatement au retour.
 */
export default class extends Controller {
    static values = {
        etatUrl: String,
        fluxUrl: String,
        transport: { type: String, default: 'sse' },
        intervalle: { type: Number, default: 3 },
    };
    static targets = ['statut'];

    connect() {
        this.echecs = 0;
        this.arrete = false;
        this.visibilite = () => {
            if (document.hidden) {
                this.suspendre();
            } else {
                this.demarrer();
            }
        };
        document.addEventListener('visibilitychange', this.visibilite);
        this.demarrer();
    }

    disconnect() {
        this.arrete = true;
        document.removeEventListener('visibilitychange', this.visibilite);
        this.suspendre();
    }

    demarrer() {
        if (this.arrete || document.hidden || this.source || this.minuteur) {
            return;
        }
        if (this.transportValue === 'sse' && 'EventSource' in window && this.echecs < 3) {
            this.ouvrirFlux();
        } else {
            this.sonder();
        }
    }

    suspendre() {
        if (this.source) {
            this.source.close();
            this.source = null;
        }
        clearTimeout(this.minuteur);
        this.minuteur = null;
    }

    ouvrirFlux() {
        this.source = new EventSource(this.fluxUrlValue);
        this.source.addEventListener('etat', (e) => {
            this.echecs = 0;
            this.recevoir(JSON.parse(e.data), 'Flux temps réel');
        });
        this.source.onerror = () => {
            // Fermeture normale en fin de flux (EventSource se reconnecte seul) ou vraie panne.
            if (this.source && this.source.readyState === EventSource.CLOSED) {
                this.echecs += 1;
                this.source = null;
                this.indiquer('Reconnexion…', 'warn');
                this.minuteur = setTimeout(() => { this.minuteur = null; this.demarrer(); }, 3000);
            }
        };
    }

    async sonder() {
        this.minuteur = null;
        try {
            const reponse = await fetch(this.etatUrlValue, { headers: { Accept: 'application/json' }, cache: 'no-store', credentials: 'same-origin' });
            if (!reponse.ok) {
                throw new Error(String(reponse.status));
            }
            this.recevoir(await reponse.json(), 'Actualisation périodique');
        } catch (e) {
            this.indiquer('Connexion perdue — nouvel essai', 'danger');
        }
        if (!this.arrete && !document.hidden) {
            const secondes = Math.min(5, Math.max(2, this.intervalleValue));
            this.minuteur = setTimeout(() => this.sonder(), secondes * 1000);
        }
    }

    recevoir(etat, mode) {
        window.dispatchEvent(new CustomEvent('genosha:etat', { detail: etat }));
        const heure = new Date(etat.genereLe).toLocaleTimeString('fr-FR');
        this.indiquer(`${mode} • ${heure}`, 'ok');
    }

    indiquer(texte, ton) {
        if (this.hasStatutTarget) {
            this.statutTarget.textContent = texte;
            this.statutTarget.dataset.tone = ton;
        }
    }
}
