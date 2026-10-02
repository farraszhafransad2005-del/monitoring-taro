import Chart from 'chart.js/auto';
import { renderPillarGauges } from './gauges';
import { COLORS, escapeHtml, fmtShortDate, heatColor } from './format';

const percentAxis = { min: 0, max: 100, ticks: { callback: (v) => `${v}%` }, grid: { color: COLORS.grid } };

export function initOverview(payload) {
    renderPillarGauges({ oee: 'gOee', avail: 'gAvail', perf: 'gPerf', qual: 'gQual' }, payload.summary, payload.targets.oee);

    const labels = payload.trend.map((point) => fmtShortDate(point.date));
    const series = (key) => payload.trend.map((point) => point[key]);

    new Chart(document.getElementById('lineTime'), {
        type: 'line',
        data: {
            labels,
            datasets: [
                { label: 'OEE', data: series('oee'), borderColor: COLORS.green, borderWidth: 2, pointRadius: 0, tension: 0.3 },
                { label: 'Availability', data: series('avail'), borderColor: COLORS.amber, borderWidth: 1.5, pointRadius: 0, tension: 0.3 },
                { label: 'Performance', data: series('perf'), borderColor: COLORS.dark, borderWidth: 1.5, pointRadius: 0, tension: 0.3 },
                { label: 'Quality', data: series('qual'), borderColor: COLORS.grey, borderWidth: 1.5, pointRadius: 0, tension: 0.3 },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y}%` } } },
            scales: { y: percentAxis, x: { grid: { display: false }, ticks: { maxTicksLimit: 12, font: { size: 10 } } } },
        },
    });

    new Chart(document.getElementById('barMonth'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'OEE',
                data: series('oee'),
                backgroundColor: payload.trend.map((point) => (point.date === payload.filters.date ? COLORS.dark : COLORS.amber)),
                borderRadius: 3,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => `OEE: ${ctx.parsed.y}%` } } },
            scales: { y: percentAxis, x: { grid: { display: false }, ticks: { maxTicksLimit: 12, font: { size: 9 } } } },
        },
    });

    renderHeatmap(payload.heatmap);
}

function renderHeatmap(heatmap) {
    const table = document.getElementById('heatmapTable');
    if (!table) return;

    const cell = (value) => (value === null
        ? '<td><div class="heat-cell empty">–</div></td>'
        : `<td><div class="heat-cell" style="background:${heatColor(value)}">${value.toFixed(1)}</div></td>`);

    let html = `<tr><th>Mesin</th>${heatmap.weeks.map((week) => `<th>${escapeHtml(week.label)}</th>`).join('')}<th>Rata2</th></tr>`;

    heatmap.rows.forEach((row) => {
        html += `<tr><td style="text-align:left;font-weight:600;">${escapeHtml(row.machine)}</td>`
            + row.values.map(cell).join('')
            + `<td style="font-weight:600;">${row.average.toFixed(1)}</td></tr>`;
    });

    table.innerHTML = html;
}
