import { Controller } from '@hotwired/stimulus';

/*
 * Interactions de la carte tactique SVG : affichage des calques et du HUD.
 * Les positions sont rendues côté serveur à partir de TacticalMapProvider ;
 * une future implémentation Leaflet/OSM remplacerait uniquement ce contrôleur et le gabarit SVG,
 * en consommant la même structure de marqueurs.
 */
export default class extends Controller {
    static targets = ['hud'];

    toggle(event) {
        const calque = event.target.dataset.layer;
        this.element.querySelectorAll('.map-layer-' + calque).forEach((g) => {
            g.style.display = event.target.checked ? '' : 'none';
        });
    }

    toggleHud(event) {
        if (this.hasHudTarget) {
            this.hudTarget.hidden = !event.target.checked;
        }
    }
}
