@php
    $summary = $dashboard['summary'];
    $targets = $dashboard['targets'];
    $gap = round($summary['oee'] - $targets['oee'], 1);
    $periodGap = round($dashboard['period']['oee'] - $targets['oee'], 1);
    $avgSpeed = $summary['operatingMinutes'] > 0 ? $summary['actualPcs'] / $summary['operatingMinutes'] : 0;
    $hint = $summary['machineCount'].' Mesin';
@endphp

<div id="pageOverview" @class(['tab-page', 'active' => $activeTab === 'overview'])>
    <div class="grid kpi-row" style="margin-bottom:16px;">
        <x-kpi-card id="ovKpiOee" metric="oee" gauge="gOee" value-id="ovOeeVal" label="OEE Keseluruhan"
            :value="$summary['oee']" :delta-up="$gap >= 0"
            :delta="$gap >= 0 ? '▲ Target '.number_format($targets['oee'], 1).'% Achieved' : '▼ '.number_format(abs($gap), 1).' pp di bawah target '.number_format($targets['oee'], 1).'%'"
            :hint="'📊 Detail '.$hint" />
        <x-kpi-card id="ovKpiAvail" metric="avail" gauge="gAvail" value-id="ovAvailVal" label="Availability"
            :value="$summary['avail']" :delta-up="$summary['avail'] >= $targets['availability']"
            :delta="'Downtime: '.number_format($summary['downtimeMinutes'], 0, ',', '.').' menit'"
            :hint="'⏱️ Detail '.$hint" />
        <x-kpi-card id="ovKpiPerf" metric="perf" gauge="gPerf" value-id="ovPerfVal" label="Performance"
            :value="$summary['perf']" :delta-up="$summary['perf'] >= $targets['performance']"
            :delta="'Speed rata-rata '.number_format($avgSpeed, 0, ',', '.').' PPM'" :hint="'⚡ Detail '.$hint" />
        <x-kpi-card id="ovKpiQual" metric="qual" gauge="gQual" value-id="ovQualVal" label="Quality Rate"
            :value="$summary['qual']" :delta-up="$summary['qual'] >= $targets['quality']"
            :delta="'Reject '.number_format($summary['rejectPcs'], 0, ',', '.').' pcs'" :hint="'✨ Detail '.$hint" />

        <div class="card">
            <div class="info-list">
                <div class="info-row"><span class="label">Planned time</span><span class="value"
                        id="ovPlannedHours">{{ number_format($summary['availableMinutes'] / 60, 1, ',', '.') }} jam</span>
                </div>
                <div class="info-row"><span class="label">Run time</span><span class="value"
                        id="ovRuntimeHours">{{ number_format($summary['operatingMinutes'] / 60, 1, ',', '.') }} jam</span>
                </div>
                <div class="info-row"><span class="label">Good count</span><span class="value"
                        id="ovGoodCount">{{ number_format($summary['actualPcs'], 0, ',', '.') }}</span></div>
                <div class="info-row"><span class="label">Total count</span><span class="value"
                        id="ovTotalCount">{{ number_format($summary['totalPcs'], 0, ',', '.') }}</span></div>
            </div>
        </div>
    </div>

    <div class="grid chart-row" style="margin-bottom:16px;">
        <div class="card">
            <div class="card-title">OEE dari waktu ke waktu (harian)</div>
            <div class="legend-row">
                <span><span class="legend-dot" style="background:#10B981"></span>OEE</span>
                <span><span class="legend-dot" style="background:#E5A91A"></span>Availability</span>
                <span><span class="legend-dot" style="background:#33373E"></span>Performance</span>
                <span><span class="legend-dot" style="background:#555963"></span>Quality</span>
            </div>
            <div class="chart-wrap"><canvas id="lineTime"></canvas></div>
        </div>
        <div class="card">
            <div class="card-title">OEE per hari</div>
            <div class="chart-wrap"><canvas id="barMonth"></canvas></div>
        </div>
    </div>

    <div class="grid bottom-row">
        <div class="card">
            <div class="card-title">Top losses — afal etiket</div>
            <table>
                <tr>
                    <th>Kategori losses</th>
                    <th class="num">Pcs</th>
                    <th class="num">% dari total</th>
                </tr>
                @foreach ($dashboard['losses']['items'] as $loss)
                    @continue($loss['pcs'] === 0)
                    <tr>
                        <td>{{ $loss['label'] }}</td>
                        <td class="num">{{ number_format($loss['pcs'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($loss['share'], 1, ',', '.') }}%</td>
                    </tr>
                @endforeach
                <tr>
                    <td>Total Afal</td>
                    <td class="num">{{ number_format($dashboard['losses']['total'], 0, ',', '.') }}</td>
                    <td class="num">100%</td>
                </tr>
            </table>
        </div>

        <div class="card">
            <div class="card-title">OEE target</div>
            <div class="target-block">
                <div class="t-label">Target OEE</div>
                <div class="t-value">{{ number_format($targets['oee'], 1, ',', '.') }}%</div>
            </div>
            <div class="target-block">
                <div class="t-label">Gap ke target (tanggal terpilih)</div>
                <div @class(['t-value', 'gap-green' => $gap >= 0, 'gap-red' => $gap < 0])>
                    {{ $gap >= 0 ? '+' : '' }}{{ number_format($gap, 1, ',', '.') }} pp</div>
            </div>
            <div class="target-block" style="margin-bottom:0;">
                <div class="t-label">Rata-rata OEE seluruh periode</div>
                <div @class(['t-value', 'gap-green' => $periodGap >= 0, 'gap-red' => $periodGap < 0])>
                    {{ number_format($dashboard['period']['oee'], 1, ',', '.') }}%</div>
                <div class="t-label" style="font-size:11px;">
                    {{ number_format($dashboard['period']['records'], 0, ',', '.') }} laporan shift
                    {{ \Illuminate\Support\Carbon::parse($dashboard['filters']['minDate'])->translatedFormat('d M') }} –
                    {{ \Illuminate\Support\Carbon::parse($dashboard['filters']['maxDate'])->translatedFormat('d M Y') }}
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Heatmap OEE per mesin per minggu</div>
            <table class="heatmap-table" id="heatmapTable"></table>
            <div class="legend-row" style="margin-top:10px;">
                <span><span class="legend-dot" style="background:#EF4444"></span>&lt; 60%</span>
                <span><span class="legend-dot" style="background:#F59E0B"></span>60–70%</span>
                <span><span class="legend-dot" style="background:#EAB308"></span>70–80%</span>
                <span><span class="legend-dot" style="background:#10B981"></span>80–90%</span>
                <span><span class="legend-dot" style="background:#059669"></span>&ge; 90%</span>
            </div>
        </div>
    </div>
</div>
