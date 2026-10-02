import Chart from 'chart.js/auto';
import { renderPillarGauges } from './gauges';
import { COLORS, escapeHtml, fmtInt, fmtNum } from './format';

const POLL_INTERVAL_MS = 5000;
const STALE_AFTER_MS = 60_000;
const READINGS_ENDPOINT = '/api/v1/oee-readings';

export function initPackaging(payload) {
    const select = document.getElementById('lineSelect');
    if (!select || payload.machines.length === 0) return;

    const el = (id) => document.getElementById(id);
    const state = { live: true, lastReadingId: null, offline: false, timer: null };
    const chart = createStreamChart(el('liveChartStream'));

    function addLog(type, tag, message) {
        const consoleEl = el('logConsole');
        const tagClass = { info: 'tag-info', warn: 'tag-warn', err: 'tag-err' }[type] ?? 'tag-success';
        const entry = document.createElement('div');
        entry.className = 'log-entry';
        entry.innerHTML = `<span class="log-time">[${new Date().toLocaleTimeString('id-ID', { hour12: false })}]</span>`
            + `<span class="log-tag ${tagClass}">${escapeHtml(tag)}</span><span>${escapeHtml(message)}</span>`;
        consoleEl.appendChild(entry);
        consoleEl.scrollTop = consoleEl.scrollHeight;
    }

    function setStatus(kind, text) {
        el('statusPill').className = `line-status-pill ${kind}`;
        el('statusPillText').textContent = text;
    }

    function showMachine(machine) {
        const targets = payload.targets;
        el('pkOeeVal').textContent = `${machine.oee.toFixed(1)}%`;
        el('pkAvailVal').textContent = `${machine.avail.toFixed(1)}%`;
        el('pkPerfVal').textContent = `${machine.perf.toFixed(1)}%`;
        el('pkQualVal').textContent = `${machine.qual.toFixed(1)}%`;

        const gap = +(machine.oee - targets.oee).toFixed(1);
        setDelta('pkOeeDelta', gap >= 0, gap >= 0 ? `▲ Target ${targets.oee.toFixed(1)}% Achieved` : `▼ ${Math.abs(gap)} pp di bawah target`);
        setDelta('pkAvailDelta', machine.avail >= targets.availability, `Operating: ${fmtInt(machine.operatingMinutes)} menit`);
        setDelta('pkPerfDelta', machine.perf >= targets.performance, `Speed standar ${fmtNum(machine.idealSpeed, 0)} PPM`);
        setDelta('pkQualDelta', machine.qual >= targets.quality, `Reject ${fmtInt(machine.rejectPcs)} pcs`);

        renderPillarGauges({ oee: 'pkGOee', avail: 'pkGAvail', perf: 'pkGPerf', qual: 'pkGQual' }, machine, targets.oee);

        el('machineDataTitle').textContent = `DATA ${machine.name.toUpperCase()}`;
        el('livePpm').textContent = `${fmtNum(machine.actualSpeed, 0)} PPM`;
        el('liveTotalCount').textContent = fmtInt(machine.totalPcs);
        el('liveGoodCount').textContent = fmtInt(machine.actualPcs);
        const rejectPct = machine.totalPcs > 0 ? (machine.rejectPcs / machine.totalPcs) * 100 : 0;
        el('liveRejectCount').textContent = `${fmtInt(machine.rejectPcs)} (${rejectPct.toFixed(2)}%)`;
    }

    function setDelta(id, isUp, text) {
        const delta = el(id);
        delta.className = `kpi-delta ${isUp ? 'up' : 'down'}`;
        delta.textContent = text;
    }

    async function poll() {
        if (!state.live) return;

        try {
            const params = new URLSearchParams({ machine: select.value, limit: '20' });
            const response = await fetch(`${READINGS_ENDPOINT}?${params}`, {
                headers: { Accept: 'application/json' },
                signal: AbortSignal.timeout(4000),
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const { data: readings } = await response.json();

            if (state.offline) {
                state.offline = false;
                addLog('success', 'RECOVERY', 'Koneksi ke server data live pulih.');
            }

            applyReadings(readings);
        } catch (error) {
            if (!state.offline) {
                state.offline = true;
                addLog('err', 'OFFLINE', `Gagal mengambil data live: ${error.message}`);
            }
            setStatus('breakdown', 'SERVER DATA LIVE OFFLINE');
        }
    }

    function applyReadings(readings) {
        chart.data.labels = readings.map((r) => new Date(r.recorded_at).toLocaleTimeString('id-ID', { minute: '2-digit', second: '2-digit' }));
        chart.data.datasets[0].data = readings.map((r) => r.oee);
        chart.data.datasets[1].data = readings.map((r) => r.speed_ppm);
        chart.update('none');

        const latest = readings.at(-1);
        if (!latest) {
            setStatus('paused', 'BELUM ADA DATA LIVE');
            return;
        }

        if (latest.id !== state.lastReadingId) {
            state.lastReadingId = latest.id;
            addLog('info', latest.source === 'node-red' ? 'NODE-RED' : 'API',
                `OEE ${latest.oee.toFixed(1)}% (A ${latest.availability}% • P ${latest.performance}% • Q ${latest.quality}%)`);
        }

        const isFresh = Date.now() - new Date(latest.recorded_at).getTime() < STALE_AFTER_MS;
        setStatus(isFresh ? 'running' : 'paused', isFresh ? 'STATUS: RUNNING (LIVE)' : 'DATA LIVE TERAKHIR > 1 MENIT');
    }

    function changeMachine() {
        const machine = payload.machines.find((m) => m.code === select.value);
        if (!machine) return;
        state.lastReadingId = null;
        showMachine(machine);
        addLog('info', 'CONFIG', `Menampilkan ${machine.name} (${machine.sku}).`);
        poll();
    }

    function toggleLive() {
        state.live = !state.live;
        const button = el('btnToggleLive');
        button.textContent = state.live ? '⏸ Pause Live' : '▶ Resume Live';
        button.className = `btn ${state.live ? 'btn-dark' : 'btn-primary'}`;
        el('liveStatusText').textContent = state.live ? 'FKS Live Monitoring Aktif' : 'Monitoring Dijeda';
        addLog(state.live ? 'info' : 'warn', 'FKS-SYS', state.live ? 'Monitoring live dilanjutkan.' : 'Monitoring live dijeda oleh user.');
        if (state.live) poll();
        else setStatus('paused', 'MONITORING DIJEDA');
    }

    select.addEventListener('change', changeMachine);
    el('btnToggleLive').addEventListener('click', toggleLive);

    addLog('info', 'SISTEM', `Data DCR dimuat: ${payload.machines.length} mesin aktif pada tanggal terpilih.`);
    changeMachine();
    state.timer = setInterval(poll, POLL_INTERVAL_MS);
}

function createStreamChart(canvas) {
    return new Chart(canvas, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'OEE (%)',
                    data: [],
                    borderColor: COLORS.green,
                    backgroundColor: 'rgba(16,185,129,0.08)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.35,
                    yAxisID: 'yOee',
                    pointRadius: 2,
                },
                {
                    label: 'Kecepatan (PPM)',
                    data: [],
                    borderColor: COLORS.amber,
                    borderWidth: 2,
                    tension: 0.35,
                    yAxisID: 'yPpm',
                    pointRadius: 2,
                    spanGaps: true,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 400 },
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: COLORS.grid }, ticks: { font: { size: 10 } } },
                yOee: { type: 'linear', position: 'left', min: 0, max: 100, ticks: { callback: (v) => `${v}%` }, grid: { color: COLORS.grid } },
                yPpm: { type: 'linear', position: 'right', min: 0, suggestedMax: 200, ticks: { callback: (v) => `${v} PPM` }, grid: { display: false } },
            },
        },
    });
}
