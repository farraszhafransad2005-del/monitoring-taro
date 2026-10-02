import Chart from 'chart.js/auto';
import { COLORS, escapeHtml, fmtInt, fmtNum } from './format';

const METRIC_META = {
    perf: {
        label: 'PERFORMANCE RATE',
        icon: '⚡',
        subtitle: 'Output aktual vs kapasitas ideal (Operating Time × Speed standar mesin)',
        formula: 'Performance Rate (%) = Actual Output ÷ (Operating Time × Ideal Speed) × 100%',
        targetKey: 'performance',
        components: [
            ['📦 Actual Output (Pcs)', 'Vol aktual karton × isi per karton, sesuai laporan DCR.'],
            ['🎯 Ideal Speed (PPM)', 'Kolom "Actual Speed" di DCR — speed setting standar mesin per SKU.'],
            ['⏱️ Operating Time (Menit)', 'Available Time dikurangi downtime tak terencana.'],
            ['📈 Ideal Output', 'Operating Time × Ideal Speed — kapasitas maksimum teoritis.'],
            ['⚠️ Speed Loss', 'Selisih Ideal Output dan Actual Output (micro-stop, speed turun, macet).'],
        ],
    },
    avail: {
        label: 'AVAILABILITY RATE',
        icon: '⏱️',
        subtitle: 'Waktu operasi bersih vs waktu tersedia (Calender Time − Unavailable Time)',
        formula: 'Availability Rate (%) = Operating Time ÷ Available Time × 100% = (Available − Downtime) ÷ Available × 100%',
        targetKey: 'availability',
        components: [
            ['📅 Available Time', 'Calender Time shift dikurangi waktu non-operasional terencana (mis. Half shift).'],
            ['🛑 Unplanned Downtime', 'Durasi henti tidak terencana. Kolom DCR masih kosong — isi via logbook/API.'],
            ['⚙️ Operating Time', 'Durasi bersih mesin benar-benar berproduksi.'],
            ['📉 Downtime Loss (%)', 'Persentase waktu terbuang dari waktu tersedia.'],
        ],
    },
    qual: {
        label: 'QUALITY RATE',
        icon: '✨',
        subtitle: 'Rasio kemasan lolos standar terhadap total kemasan diproses',
        formula: 'Quality Rate (%) = Good Output ÷ (Good Output + Reject) × 100%',
        targetKey: 'quality',
        components: [
            ['✅ Good Output (Pcs)', 'Kemasan yang masuk karton (vol aktual × isi).'],
            ['❌ Reject (Pcs)', 'Total afal etiket: setting awal, ganti roll, pack kosong, bocor, kue terjepit, dll.'],
            ['🏭 Total Output (Pcs)', 'Good Output + Reject.'],
            ['🔍 Defect Rate (%)', 'Reject ÷ Total Output (100% − Quality Rate).'],
        ],
    },
    oee: {
        label: 'OVERALL EQUIPMENT EFFECTIVENESS (OEE)',
        icon: '📊',
        subtitle: 'Integrasi Availability × Performance × Quality',
        formula: 'OEE = Availability × Performance × Quality Rate × 100%',
        targetKey: 'oee',
        components: [
            ['⏱️ Availability', 'Efisiensi pemanfaatan waktu tersedia.'],
            ['⚡ Performance', 'Efisiensi kecepatan aktual terhadap speed standar.'],
            ['✨ Quality', 'Rasio kemasan bebas cacat.'],
            ['🏆 World Class Benchmark', 'Standar manufaktur global (OEE ≥ 85%).'],
        ],
    },
};

const pctBar = (value, color) => `
    <span style="font-weight:800; color:var(--brand-dark);">${value.toFixed(1)}%</span>
    <div class="mini-progress"><div class="mini-progress-fill" style="width:${Math.min(100, value)}%; background:${color};"></div></div>`;

const evaluationColor = (value) => (value >= 90 ? COLORS.green : value >= 80 ? COLORS.amber : COLORS.red);

function evaluationBadge(value, target) {
    if (value >= target) return '<span class="stat-badge world-class">🏆 Capai Target</span>';
    if (value >= target - 10) return '<span class="stat-badge good">⚡ Mendekati</span>';
    return '<span class="stat-badge warning">⚠️ Di Bawah</span>';
}

const typeBadge = (type) => `<span class="${/kawa/i.test(type) ? 'badge-kawasima' : 'badge-pm'}" style="white-space:nowrap;">${escapeHtml(type)}</span>`;

/**
 * Totals for a set of machines, recomputed from additive sums so they match the server's math.
 */
function totals(machines) {
    const sum = (key) => machines.reduce((acc, m) => acc + m[key], 0);
    const t = {
        availableMinutes: sum('availableMinutes'),
        downtimeMinutes: sum('downtimeMinutes'),
        operatingMinutes: sum('operatingMinutes'),
        idealPcs: sum('idealPcs'),
        actualPcs: sum('actualPcs'),
        rejectPcs: sum('rejectPcs'),
        totalPcs: sum('totalPcs'),
    };
    const ratio = (a, b) => (b > 0 ? Math.min(1, a / b) : 0);
    t.avail = ratio(t.operatingMinutes, t.availableMinutes) * 100;
    t.perf = ratio(t.actualPcs, t.idealPcs) * 100;
    t.qual = ratio(t.actualPcs, t.totalPcs) * 100;
    t.oee = (t.avail * t.perf * t.qual) / 10000;
    return t;
}

/**
 * Column definitions per metric: [header, cell(machine), footer(totals)].
 */
const TABLE_COLUMNS = {
    perf: [
        ['Operating Time', (m) => `${fmtInt(m.operatingMinutes)} min`, (t) => `${fmtInt(t.operatingMinutes)} min`],
        ['Ideal Speed', (m) => `${fmtNum(m.idealSpeed)} PPM`, () => '-'],
        ['Ideal Output', (m) => `${fmtInt(m.idealPcs)} pcs`, (t) => `${fmtInt(t.idealPcs)} pcs`],
        ['Actual Output', (m) => `<b style="color:var(--brand-dark);">${fmtInt(m.actualPcs)} pcs</b>`, (t) => `${fmtInt(t.actualPcs)} pcs`],
        ['Actual Speed', (m) => `${fmtNum(m.actualSpeed)} PPM`, () => '-'],
        ['Speed Loss', (m) => `<span style="color:#EF4444;">-${fmtInt(Math.max(0, m.idealPcs - m.actualPcs))} pcs</span>`, (t) => `<span style="color:#EF4444;">-${fmtInt(Math.max(0, t.idealPcs - t.actualPcs))} pcs</span>`],
    ],
    avail: [
        ['Available Time', (m) => `${fmtInt(m.availableMinutes)} min`, (t) => `${fmtInt(t.availableMinutes)} min`],
        ['Unplanned Downtime', (m) => `<span style="color:#EF4444;">${fmtInt(m.downtimeMinutes)} min</span>`, (t) => `${fmtInt(t.downtimeMinutes)} min`],
        ['Operating Time', (m) => `<b style="color:var(--brand-dark);">${fmtInt(m.operatingMinutes)} min</b>`, (t) => `${fmtInt(t.operatingMinutes)} min`],
        ['Downtime Loss (%)', (m) => `${(m.availableMinutes > 0 ? (m.downtimeMinutes / m.availableMinutes) * 100 : 0).toFixed(1)}%`, (t) => `${(t.availableMinutes > 0 ? (t.downtimeMinutes / t.availableMinutes) * 100 : 0).toFixed(1)}%`],
    ],
    qual: [
        ['Total Output', (m) => `${fmtInt(m.totalPcs)} pcs`, (t) => `${fmtInt(t.totalPcs)} pcs`],
        ['Good Output', (m) => `<span style="color:#10B981; font-weight:700;">${fmtInt(m.actualPcs)} pcs</span>`, (t) => `${fmtInt(t.actualPcs)} pcs`],
        ['Reject', (m) => `<span style="color:#EF4444;">${fmtInt(m.rejectPcs)} pcs</span>`, (t) => `${fmtInt(t.rejectPcs)} pcs`],
        ['Defect Rate (%)', (m) => `${(m.totalPcs > 0 ? (m.rejectPcs / m.totalPcs) * 100 : 0).toFixed(2)}%`, (t) => `${(t.totalPcs > 0 ? (t.rejectPcs / t.totalPcs) * 100 : 0).toFixed(2)}%`],
    ],
    oee: [
        ['Availability (%)', (m) => `${m.avail.toFixed(1)}%`, (t) => `${t.avail.toFixed(1)}%`],
        ['Performance (%)', (m) => `${m.perf.toFixed(1)}%`, (t) => `${t.perf.toFixed(1)}%`],
        ['Quality Rate (%)', (m) => `${m.qual.toFixed(1)}%`, (t) => `${t.qual.toFixed(1)}%`],
    ],
};

export function initMetricModal(payload) {
    const modal = document.getElementById('metricModal');
    if (!modal) return;

    const state = { metric: 'oee', filter: 'all', selectedCode: null, chart: null };
    const targets = payload.targets;
    const el = (id) => document.getElementById(id);

    const filteredMachines = () => payload.machines.filter((m) => state.filter === 'all' || m.type === state.filter);
    const valueOf = (m) => m[state.metric];
    const targetOf = () => targets[METRIC_META[state.metric].targetKey];

    function open(metric) {
        state.metric = metric;
        state.selectedCode = null;
        const meta = METRIC_META[metric];

        document.querySelectorAll('.clickable-kpi-card').forEach((card) => {
            card.classList.toggle('active-kpi', card.dataset.metric === metric);
        });

        el('detailSectionIcon').textContent = meta.icon;
        el('detailSectionTitle').textContent = `ANALISIS & PERHITUNGAN ${meta.label} (${payload.machines.length} MESIN)`;
        el('detailSectionSubtitle').textContent = meta.subtitle;
        el('chartTitle').textContent = `GRAFIK PERBANDINGAN ${meta.label} SETIAP MESIN (%)`;
        el('tableTitle').textContent = `📊 DATA DASAR & HASIL PERHITUNGAN ${meta.label} PER MESIN`;

        modal.classList.add('open');
        modal.scrollTop = 0;
        document.body.classList.add('modal-open');

        renderFormula(meta);
        renderAll();
    }

    function close() {
        modal.classList.remove('open');
        document.body.classList.remove('modal-open');
        document.querySelectorAll('.clickable-kpi-card.active-kpi').forEach((card) => card.classList.remove('active-kpi'));
    }

    function renderAll() {
        renderChart();
        renderTable();
        renderDetail();
    }

    function renderFormula(meta) {
        el('formulaContainer').innerHTML = `
            <div class="formula-top">
                <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.06em; color:#94A3B8; font-weight:700;">📐 Rumus Matematis Standar OEE</div>
                <div class="formula-equation">${meta.formula}</div>
            </div>
            <div class="formula-components-grid">
                ${meta.components.map(([name, desc]) => `<div class="formula-comp-card"><div class="comp-name">${name}</div><div class="comp-desc">${desc}</div></div>`).join('')}
            </div>`;
    }

    function renderChart() {
        const machines = filteredMachines();
        const values = machines.map(valueOf);
        const minValue = Math.min(60, ...values.map((v) => Math.floor(v / 10) * 10));

        state.chart?.destroy();
        state.chart = new Chart(el('machineMetricChart'), {
            type: 'bar',
            data: {
                labels: machines.map((m) => m.name),
                datasets: [{ data: values, backgroundColor: values.map(evaluationColor), borderRadius: 6, maxBarThickness: 36 }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 400 },
                onClick: (evt, elements) => {
                    if (elements.length) selectMachine(machines[elements[0].index].code);
                },
                onHover: (evt, elements) => {
                    evt.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1E232A',
                        padding: 10,
                        displayColors: false,
                        callbacks: {
                            label: (ctx) => {
                                const m = machines[ctx.dataIndex];
                                return [
                                    `Nilai: ${ctx.parsed.y}%`,
                                    `SKU: ${m.sku}`,
                                    `Speed: ${fmtNum(m.actualSpeed)} PPM (Ideal: ${fmtNum(m.idealSpeed)} PPM)`,
                                    `Output: ${fmtInt(m.actualPcs)} pcs`,
                                ];
                            },
                        },
                    },
                },
                scales: {
                    y: { min: minValue, max: 100, ticks: { stepSize: 10, callback: (v) => `${v}%` }, grid: { color: 'rgba(0,0,0,0.06)' } },
                    x: { ticks: { font: { size: 11, weight: '600' }, color: '#374151' }, grid: { display: false } },
                },
            },
        });
    }

    function renderTable() {
        const machines = filteredMachines();
        const columns = TABLE_COLUMNS[state.metric];
        const target = targetOf();
        const total = totals(machines);
        const label = state.metric === 'oee' ? 'OEE Score' : `${METRIC_META[state.metric].label.split(' ')[0]} Rate`;

        const head = `<tr>
            <th style="width:40px; text-align:center;">No</th><th>Mesin Packaging</th><th>Tipe Mesin</th><th>SKU Produk</th>
            ${columns.map(([header]) => `<th class="num">${header}</th>`).join('')}
            <th class="num" style="min-width:140px;">${label}</th><th style="text-align:center;">Evaluasi</th></tr>`;

        const rows = machines.map((m, index) => `
            <tr data-machine-code="${escapeHtml(m.code)}" class="${m.code === state.selectedCode ? 'selected-row' : ''}" title="Klik untuk detail perhitungan ${escapeHtml(m.name)}">
                <td style="text-align:center; color:var(--text-muted); font-weight:600;">${index + 1}</td>
                <td><strong style="color:var(--brand-dark);">${escapeHtml(m.name)}</strong></td>
                <td>${typeBadge(m.type)}</td>
                <td style="color:var(--text-secondary); font-size:11.5px;">${escapeHtml(m.sku)}</td>
                ${columns.map(([, cell]) => `<td class="num">${cell(m)}</td>`).join('')}
                <td class="num">${pctBar(valueOf(m), evaluationColor(valueOf(m)))}</td>
                <td style="text-align:center;">${evaluationBadge(valueOf(m), target)}</td>
            </tr>`).join('');

        const foot = `<tr class="total-row">
            <td colspan="4" style="text-align:left;">TOTAL / RATA-RATA (${machines.length} MESIN)</td>
            ${columns.map(([, , footer]) => `<td class="num">${footer(total)}</td>`).join('')}
            <td class="num"><span style="font-size:14px; font-weight:800; color:${evaluationColor(total[state.metric])};">${total[state.metric].toFixed(1)}%</span></td>
            <td style="text-align:center;">${evaluationBadge(total[state.metric], target)}</td></tr>`;

        el('tableContainer').innerHTML = `<table class="calc-table"><thead>${head}</thead><tbody>${rows}</tbody><tfoot>${foot}</tfoot></table>`;
    }

    function selectMachine(code) {
        state.selectedCode = state.selectedCode === code ? null : code;
        document.querySelectorAll('#tableContainer tbody tr').forEach((tr) => {
            tr.classList.toggle('selected-row', tr.dataset.machineCode === state.selectedCode);
        });
        renderDetail();
        if (state.selectedCode !== null) el('machineDetailPanel').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function renderDetail() {
        const panel = el('machineDetailPanel');
        const m = filteredMachines().find((machine) => machine.code === state.selectedCode);

        if (!m) {
            panel.style.display = 'none';
            panel.innerHTML = '';
            return;
        }

        const target = targetOf();
        const gap = +(valueOf(m) - target).toFixed(1);
        const gapHtml = gap >= 0
            ? `<span class="stat-badge world-class">▲ ${gap} pp di atas target ${target}%</span>`
            : `<span class="stat-badge warning">▼ ${Math.abs(gap)} pp di bawah target ${target}%</span>`;
        const pct = (a, b, digits = 1) => (b > 0 ? ((a / b) * 100).toFixed(digits) : '0.0');
        const { stats, steps, note } = detailContent(m, pct);

        panel.innerHTML = `
            <div class="md-head">
                <div>
                    <div class="md-name">🔎 Detail Perhitungan — ${escapeHtml(m.name)} <span style="color:var(--text-muted); font-weight:600;">(${escapeHtml(m.code)})</span></div>
                    <div class="md-sku">⚙️ ${escapeHtml(m.type)} • ${escapeHtml(m.sku)}${m.productionHouse ? ` • ${escapeHtml(m.productionHouse)}` : ''}</div>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    ${gapHtml}
                    <button type="button" class="modal-close-btn" style="width:28px; height:28px; font-size:12px;" data-machine-code="${escapeHtml(m.code)}" title="Tutup detail">✕</button>
                </div>
            </div>
            <div class="md-grid">${stats.map(([k, v]) => `<div class="md-stat"><div class="k">${k}</div><div class="v">${v}</div></div>`).join('')}</div>
            <div class="md-steps">${steps}</div>
            <div class="md-note">${note}</div>`;
        panel.style.display = 'block';
    }

    function detailContent(m, pct) {
        if (state.metric === 'perf') {
            const speedLoss = Math.max(0, m.idealPcs - m.actualPcs);
            return {
                stats: [
                    ['Operating Time', `${fmtInt(m.operatingMinutes)} min`],
                    ['Ideal Speed', `${fmtNum(m.idealSpeed)} PPM`],
                    ['Actual Speed', `${fmtNum(m.actualSpeed)} PPM`],
                    ['Ideal Output', `${fmtInt(m.idealPcs)} pcs`],
                    ['Actual Output', `${fmtInt(m.actualPcs)} pcs`],
                    ['Speed Loss', `${fmtInt(speedLoss)} pcs`],
                ],
                steps: `Ideal Output = Operating Time × Ideal Speed<br>
                    &nbsp;&nbsp;= ${fmtInt(m.operatingMinutes)} min × ${fmtNum(m.idealSpeed)} PPM = <span class="hl">${fmtInt(m.idealPcs)} pcs</span><br>
                    Performance = Actual Output ÷ Ideal Output × 100%<br>
                    &nbsp;&nbsp;= ${fmtInt(m.actualPcs)} ÷ ${fmtInt(m.idealPcs)} × 100% = <span class="hl">${pct(m.actualPcs, m.idealPcs)}%</span>`,
                note: `Mesin kehilangan <b>${fmtInt(speedLoss)} pcs</b> akibat speed loss & micro-stop (rata-rata ${fmtNum(Math.max(0, m.idealSpeed - m.actualSpeed))} PPM di bawah speed standar).`,
            };
        }

        if (state.metric === 'avail') {
            return {
                stats: [
                    ['Available Time', `${fmtInt(m.availableMinutes)} min`],
                    ['Unplanned Downtime', `${fmtInt(m.downtimeMinutes)} min`],
                    ['Operating Time', `${fmtInt(m.operatingMinutes)} min`],
                    ['Downtime Loss', `${pct(m.downtimeMinutes, m.availableMinutes)}%`],
                ],
                steps: `Operating Time = Available Time − Downtime<br>
                    &nbsp;&nbsp;= ${fmtInt(m.availableMinutes)} − ${fmtInt(m.downtimeMinutes)} = <span class="hl">${fmtInt(m.operatingMinutes)} min</span><br>
                    Availability = Operating Time ÷ Available Time × 100%<br>
                    &nbsp;&nbsp;= ${fmtInt(m.operatingMinutes)} ÷ ${fmtInt(m.availableMinutes)} × 100% = <span class="hl">${pct(m.operatingMinutes, m.availableMinutes)}%</span>`,
                note: m.downtimeMinutes > 0
                    ? `Mesin berhenti <b>${fmtInt(m.downtimeMinutes)} menit</b> di luar rencana — setara ±${fmtInt(m.downtimeMinutes * m.idealSpeed)} pcs potensi output.`
                    : 'Belum ada data downtime untuk mesin ini. Isi kolom downtime di DCR atau kirim via API agar Availability akurat.',
            };
        }

        if (state.metric === 'qual') {
            return {
                stats: [
                    ['Total Output', `${fmtInt(m.totalPcs)} pcs`],
                    ['Good Output', `${fmtInt(m.actualPcs)} pcs`],
                    ['Reject', `${fmtInt(m.rejectPcs)} pcs`],
                    ['Defect Rate', `${pct(m.rejectPcs, m.totalPcs, 2)}%`],
                ],
                steps: `Total Output = Good Output + Reject<br>
                    &nbsp;&nbsp;= ${fmtInt(m.actualPcs)} + ${fmtInt(m.rejectPcs)} = <span class="hl">${fmtInt(m.totalPcs)} pcs</span><br>
                    Quality Rate = Good Output ÷ Total Output × 100%<br>
                    &nbsp;&nbsp;= ${fmtInt(m.actualPcs)} ÷ ${fmtInt(m.totalPcs)} × 100% = <span class="hl">${pct(m.actualPcs, m.totalPcs)}%</span>`,
                note: `Dari setiap 1.000 kemasan, sekitar <b>${(m.totalPcs > 0 ? (m.rejectPcs / m.totalPcs) * 1000 : 0).toFixed(1)} pcs</b> menjadi afal etiket.`,
            };
        }

        const pillars = [['Availability', m.avail], ['Performance', m.perf], ['Quality', m.qual]];
        const weakest = pillars.reduce((a, b) => (b[1] < a[1] ? b : a));
        return {
            stats: [
                ['Availability', `${m.avail}%`],
                ['Performance', `${m.perf}%`],
                ['Quality Rate', `${m.qual}%`],
                ['Good Output', `${fmtInt(m.actualPcs)} pcs`],
            ],
            steps: `OEE = Availability × Performance × Quality<br>
                &nbsp;&nbsp;= ${(m.avail / 100).toFixed(3)} × ${(m.perf / 100).toFixed(3)} × ${(m.qual / 100).toFixed(3)} × 100%<br>
                &nbsp;&nbsp;= <span class="hl">${((m.avail * m.perf * m.qual) / 10000).toFixed(1)}%</span>`,
            note: `Pilar terlemah: <b>${weakest[0]} (${weakest[1]}%)</b> — fokus perbaikan di sini memberi dampak OEE terbesar.`,
        };
    }

    function setFilter(filter) {
        state.filter = filter;
        document.querySelectorAll('[data-machine-filter]').forEach((btn) => {
            btn.classList.toggle('active', btn.dataset.machineFilter === filter);
        });
        if (!filteredMachines().some((m) => m.code === state.selectedCode)) state.selectedCode = null;
        renderAll();
    }

    document.querySelectorAll('.clickable-kpi-card').forEach((card) => {
        card.addEventListener('click', () => open(card.dataset.metric));
        card.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                open(card.dataset.metric);
            }
        });
    });

    document.querySelectorAll('[data-machine-filter]').forEach((btn) => {
        btn.addEventListener('click', () => setFilter(btn.dataset.machineFilter));
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal || event.target.closest('[data-action="close-modal"]')) {
            close();
            return;
        }
        const row = event.target.closest('[data-machine-code]');
        if (row) selectMachine(row.dataset.machineCode);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('open')) close();
    });
}
