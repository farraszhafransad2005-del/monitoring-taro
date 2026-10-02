@php
    $machines = $dashboard['machines'];
    $first = $machines[0] ?? null;
@endphp

<div id="pagePackaging" @class(['tab-page', 'active' => $activeTab === 'packaging'])>
    <div class="toolbar-card">
        <div class="toolbar-left">
            <label for="lineSelect" style="font-size:13px; font-weight:700; color:#CBD5E1;">PILIH MESIN PACKAGING
                ({{ count($machines) }} aktif):</label>
            <select id="lineSelect">
                @foreach ($machines as $machine)
                    <option value="{{ $machine['code'] }}">{{ $machine['name'] }} — {{ $machine['sku'] }}</option>
                @endforeach
            </select>
            <div class="line-status-pill running" id="statusPill">
                <span style="width:6px;height:6px;border-radius:50%;background:currentColor;"></span>
                <span id="statusPillText">MENUNGGU DATA LIVE</span>
            </div>
        </div>

        <div class="toolbar-actions">
            <button type="button" class="btn btn-dark" id="btnToggleLive">⏸ Pause Live</button>
        </div>
    </div>

    @if ($first)
        <div class="grid kpi-row" style="margin-bottom:16px;">
            <x-kpi-card id="cardKpiOee" metric="oee" gauge="pkGOee" value-id="pkOeeVal" delta-id="pkOeeDelta"
                label="OEE Mesin" :value="$first['oee']" delta="Data DCR tanggal terpilih" hint="📊 Detail per mesin" />
            <x-kpi-card id="cardKpiAvail" metric="avail" gauge="pkGAvail" value-id="pkAvailVal"
                delta-id="pkAvailDelta" label="Availability" :value="$first['avail']" delta="-"
                hint="⏱️ Detail per mesin" />
            <x-kpi-card id="cardKpiPerf" metric="perf" gauge="pkGPerf" value-id="pkPerfVal" delta-id="pkPerfDelta"
                label="Performance" :value="$first['perf']" delta="-" hint="⚡ Detail per mesin" />
            <x-kpi-card id="cardKpiQual" metric="qual" gauge="pkGQual" value-id="pkQualVal" delta-id="pkQualDelta"
                label="Quality Rate" :value="$first['qual']" delta="-" hint="✨ Detail per mesin" />

            <div class="card" style="background:linear-gradient(135deg, #33373E, #1F2227); color:#fff; border:none;">
                <div class="card-title" style="color:#CBD5E1; margin-bottom:10px;" id="machineDataTitle">DATA MESIN
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div>
                        <div style="font-size:11px; color:#94A3B8;">Kecepatan Rata-rata</div>
                        <div style="font-size:20px; font-weight:800; color:#FCD34D;" id="livePpm">-</div>
                    </div>
                    <div>
                        <div style="font-size:11px; color:#94A3B8;">Total Output</div>
                        <div style="font-size:20px; font-weight:800; color:#34D399;" id="liveTotalCount">-</div>
                    </div>
                    <div>
                        <div style="font-size:11px; color:#94A3B8;">Good Product</div>
                        <div style="font-size:15px; font-weight:700; color:#E2E8F0;" id="liveGoodCount">-</div>
                    </div>
                    <div>
                        <div style="font-size:11px; color:#94A3B8;">Reject / Defect</div>
                        <div style="font-size:15px; font-weight:700; color:#F87171;" id="liveRejectCount">-</div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="grid chart-row">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">GRAFIK KECEPATAN MESIN (PPM) &amp; EFISIENSI REAL-TIME</h3>
                <div class="legend-row" style="margin:0;">
                    <span><span class="legend-dot" style="background:#10B981"></span>Real-Time OEE (%)</span>
                    <span><span class="legend-dot" style="background:#E5A91A"></span>Kecepatan Mesin (PPM)</span>
                </div>
            </div>
            <div class="chart-wrap"><canvas id="liveChartStream"></canvas></div>
        </div>

        <div class="card" style="padding:16px;">
            <div class="card-header">
                <h3 class="card-title">LOG AKTIVITAS MESIN LIVE</h3>
                <span
                    style="font-size:11px; color:var(--text-secondary); background:#F7F5EF; padding:2px 8px; border-radius:4px;">Auto-scroll</span>
            </div>
            <div class="log-box" id="logConsole"></div>
        </div>
    </div>
</div>
