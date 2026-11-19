import { Controller } from '@hotwired/stimulus';
import { Chart, RadarController, RadialLinearScale, PointElement, LineElement, Filler, Tooltip } from 'chart.js';

Chart.register(RadarController, RadialLinearScale, PointElement, LineElement, Filler, Tooltip);

/* Radar des statistiques d'un héros (Chart.js). Les barres textuelles restent l'alternative accessible. */
export default class extends Controller {
    static targets = ['canvas'];
    static values = { labels: Array, values: Array };

    connect() {
        this.chart = new Chart(this.canvasTarget, {
            type: 'radar',
            data: {
                labels: this.labelsValue.map((l, i) => `${l} (${this.valuesValue[i]})`),
                datasets: [{
                    data: this.valuesValue,
                    backgroundColor: 'rgba(75, 94, 64, 0.35)',
                    borderColor: '#34462a',
                    borderWidth: 2,
                    pointBackgroundColor: '#34462a',
                }],
            },
            options: {
                animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : undefined,
                layout: { padding: 24 },
                plugins: { legend: { display: false } },
                scales: {
                    r: {
                        min: 0, max: 100,
                        ticks: { display: false, stepSize: 20 },
                        grid: { color: 'rgba(52, 70, 42, 0.25)' },
                        angleLines: { color: 'rgba(52, 70, 42, 0.25)' },
                        pointLabels: { font: { family: 'Inter', size: 11, weight: '600' }, color: '#34462a' },
                    },
                },
            },
        });
    }

    disconnect() {
        this.chart?.destroy();
    }
}
