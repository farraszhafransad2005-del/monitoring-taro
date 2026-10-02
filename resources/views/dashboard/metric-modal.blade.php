@php
    $types = collect($dashboard['machines'])->countBy('type')->sortKeys();
@endphp

<div class="metric-modal-overlay" id="metricModal">
    <div class="metric-modal detail-section-card" id="machineBreakdownSection" role="dialog" aria-modal="true"
        aria-labelledby="detailSectionTitle">
        <div class="detail-section-header">
            <div class="detail-title-group">
                <div class="detail-title-icon" id="detailSectionIcon">⚡</div>
                <div>
                    <h3 class="card-title" id="detailSectionTitle" style="font-size:16px; margin-bottom:2px;"></h3>
                    <span id="detailSectionSubtitle" style="font-size:12px; color:var(--text-secondary);"></span>
                </div>
            </div>

            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <div class="filter-pill-group">
                    <button type="button" class="filter-pill active" data-machine-filter="all">Semua
                        ({{ count($dashboard['machines']) }} Mesin)</button>
                    @foreach ($types as $type => $count)
                        <button type="button" class="filter-pill" data-machine-filter="{{ $type }}">⚙️
                            {{ $type }} ({{ $count }})</button>
                    @endforeach
                </div>
                <button type="button" class="modal-close-btn" data-action="close-modal" title="Tutup (Esc)"
                    aria-label="Tutup">✕</button>
            </div>
        </div>

        <div class="metric-modal-body">
            <div class="formula-box" id="formulaContainer"></div>

            <div class="vis-grid">
                <div class="chart-container-card">
                    <div
                        style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
                        <div style="font-weight:700; font-size:13px; color:var(--brand-dark);" id="chartTitle"></div>
                        <div
                            style="display:flex; gap:12px; font-size:11.5px; color:var(--text-secondary); flex-wrap:wrap;">
                            <span style="display:inline-flex; align-items:center; gap:4px;"><span
                                    style="width:10px; height:10px; border-radius:2px; background:#10B981; display:inline-block;"></span>
                                Optimal (&ge;90%)</span>
                            <span style="display:inline-flex; align-items:center; gap:4px;"><span
                                    style="width:10px; height:10px; border-radius:2px; background:#E5A91A; display:inline-block;"></span>
                                Normal (80-89%)</span>
                            <span style="display:inline-flex; align-items:center; gap:4px;"><span
                                    style="width:10px; height:10px; border-radius:2px; background:#EF4444; display:inline-block;"></span>
                                Underperforming (&lt;80%)</span>
                        </div>
                    </div>
                    <div style="height:260px; position:relative; width:100%;">
                        <canvas id="machineMetricChart"></canvas>
                    </div>
                    <div style="font-size:11px; color:var(--text-muted); margin-top:8px;">
                        💡 Klik batang grafik atau baris tabel untuk melihat detail perhitungan mesin tersebut.
                    </div>
                </div>
            </div>

            <div id="machineDetailPanel" class="machine-detail-panel" style="display:none;"></div>

            <div class="table-wrap-responsive">
                <div
                    style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
                    <div style="font-weight:700; font-size:13px; color:var(--brand-dark);" id="tableTitle"></div>
                    <span
                        style="font-size:11.5px; color:var(--text-secondary); background:#F8F7F2; padding:3px 8px; border-radius:4px; border:1px solid var(--border);">
                        🔢 Satuan: Menit (Waktu Operasi), PPM (Kecepatan), Pcs (Jumlah Unit)
                    </span>
                </div>
                <div id="tableContainer"></div>
            </div>
        </div>
    </div>
</div>
