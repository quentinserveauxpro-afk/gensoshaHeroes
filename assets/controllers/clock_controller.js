import { Controller } from '@hotwired/stimulus';

/* Horloge système (UTC) affichée dans la barre latérale — purement informative. */
export default class extends Controller {
    connect() {
        this.render();
        this.interval = window.setInterval(() => this.render(), 1000);
    }

    disconnect() {
        window.clearInterval(this.interval);
    }

    render() {
        this.element.textContent = new Date().toISOString().substring(11, 19);
    }
}
