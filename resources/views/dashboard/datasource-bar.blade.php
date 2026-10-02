@php
    $filters = $dashboard['filters'];
@endphp

<div class="datasource-bar">
    <div class="datasource-info">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--accent-dark)" stroke-width="2.2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
        </svg>
        Sumber Data: <span class="datasource-file" id="activeFileName">DCR Produksi Packing
            ({{ \Illuminate\Support\Carbon::parse($filters['minDate'])->translatedFormat('d M') }} –
            {{ \Illuminate\Support\Carbon::parse($filters['maxDate'])->translatedFormat('d M Y') }})</span>
        <span
            style="font-size:11.5px; color:var(--text-secondary); background:#FFFFFF; padding:3px 8px; border-radius:4px; border:1px solid var(--border);"
            id="dataStatsBadge">{{ $dashboard['summary']['machineCount'] }} Mesin •
            {{ number_format($dashboard['summary']['records'], 0, ',', '.') }} Laporan Shift</span>
    </div>

    <form method="GET" action="{{ route('dashboard') }}" class="date-filter-group" id="filterForm"
        style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
        <input type="hidden" name="tab" value="{{ $activeTab }}" id="tabInput">
        <div style="display:flex; align-items:center; gap:6px;">
            <label for="dateFilterInput" style="font-size:12px; font-weight:700; color:var(--brand-dark);">📅 PILIH
                TANGGAL:</label>
            <input type="date" id="dateFilterInput" name="date" value="{{ $filters['date'] }}"
                min="{{ $filters['minDate'] }}" max="{{ $filters['maxDate'] }}" data-auto-submit
                style="background:#FFFFFF; border:1px solid var(--border); padding:5px 10px; border-radius:6px; font-weight:700; font-size:12.5px; color:var(--brand-dark); outline:none; cursor:pointer;">
        </div>
        <div style="display:flex; align-items:center; gap:6px;">
            <label for="shiftFilterSelect" style="font-size:12px; font-weight:700; color:var(--brand-dark);">🕒
                SHIFT:</label>
            <select id="shiftFilterSelect" name="shift" data-auto-submit
                style="background:#FFFFFF; border:1px solid var(--border); padding:5px 10px; border-radius:6px; font-weight:700; font-size:12.5px; color:var(--brand-dark); outline:none; cursor:pointer;">
                <option value="">Semua Shift (1, 2, 3)</option>
                @foreach ($filters['shifts'] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['shift'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <noscript><button type="submit" class="btn btn-dark">Terapkan</button></noscript>
    </form>
</div>
