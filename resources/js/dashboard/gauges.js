import Chart from 'chart.js/auto';
import { COLORS } from './format';

const gaugeInstances = {};

/**
 * Draw (or update) a donut gauge on the canvas with the given id.
 */
export function createGauge(id, value, color) {
    const safeValue = Math.max(0, Math.min(100, Number(value) || 0));
    const existing = gaugeInstances[id];

    if (existing) {
        existing.data.datasets[0].data = [safeValue, 100 - safeValue];
        existing.data.datasets[0].backgroundColor[0] = color;
        existing.update('none');
        return existing;
    }

    const canvas = document.getElementById(id);
    if (!canvas) return null;

    gaugeInstances[id] = new Chart(canvas, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [safeValue, 100 - safeValue],
                backgroundColor: [color, COLORS.track],
                borderWidth: 0,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '72%',
            circumference: 360,
            plugins: { tooltip: { enabled: false }, legend: { display: false } },
        },
    });

    return gaugeInstances[id];
}

/**
 * Render the four OEE pillar gauges for a metrics object ({ oee, avail, perf, qual }).
 */
export function renderPillarGauges(ids, metrics, oeeTarget) {
    createGauge(ids.oee, metrics.oee, metrics.oee >= oeeTarget ? COLORS.green : COLORS.amber);
    createGauge(ids.avail, metrics.avail, COLORS.amber);
    createGauge(ids.perf, metrics.perf, COLORS.dark);
    createGauge(ids.qual, metrics.qual, COLORS.green);
}
