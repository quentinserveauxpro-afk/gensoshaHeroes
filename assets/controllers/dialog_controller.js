import { Controller } from '@hotwired/stimulus';

/*
 * Fenêtre modale native (<dialog>) : ouverture, fermeture, retour du focus au déclencheur.
 * Échap et le bouton « Annuler » ferment la fenêtre ; aucune décision métier n'est prise ici.
 */
export default class extends Controller {
    static targets = ['dialog'];

    ouvrir(event) {
        this.declencheur = event?.currentTarget ?? null;
        this.dialogTarget.showModal();
    }

    fermer() {
        this.dialogTarget.close();
    }

    fermeture() {
        this.declencheur?.focus();
    }

    // Clic sur le fond (hors du contenu) : fermeture.
    clicFond(event) {
        if (event.target === this.dialogTarget) {
            this.fermer();
        }
    }
}
