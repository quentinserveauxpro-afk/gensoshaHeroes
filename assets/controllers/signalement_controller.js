import { Controller } from '@hotwired/stimulus';

/*
 * Signalement citoyen : affichage conditionnel des champs.
 * - « Rester anonyme » coché : nom et prénom masqués et désactivés (jamais envoyés) ;
 * - « Présence d'un super-vilain » décoché : sélection du super-vilain masquée et désactivée.
 * Sans JavaScript, tous les champs restent visibles et le serveur applique les mêmes règles.
 */
export default class extends Controller {
    static targets = ['anonyme', 'identite', 'presenceVilain', 'vilain'];

    connect() {
        this.basculer();
    }

    basculer() {
        const anonyme = this.hasAnonymeTarget && this.anonymeTarget.checked;
        this.identiteTargets.forEach((ligne) => this.afficher(ligne, !anonyme));
        const vilain = this.hasPresenceVilainTarget && this.presenceVilainTarget.checked;
        this.vilainTargets.forEach((ligne) => this.afficher(ligne, vilain));
    }

    afficher(ligne, visible) {
        ligne.hidden = !visible;
        ligne.querySelectorAll('input, select, textarea').forEach((champ) => {
            champ.disabled = !visible;
        });
    }
}
