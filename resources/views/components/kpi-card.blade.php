@props(['metric', 'gauge', 'valueId', 'label', 'value', 'delta', 'deltaId' => null, 'deltaUp' => true, 'hint'])

<div {{ $attributes->class(['card', 'kpi-card', 'clickable-kpi-card']) }} data-metric="{{ $metric }}" role="button"
    tabindex="0" title="Klik untuk melihat {{ $label }} per mesin & tabel perhitungan">
    <div class="kpi-gauge"><canvas id="{{ $gauge }}"></canvas></div>
    <div>
        <div class="card-title" style="margin-bottom:2px;">{{ $label }}</div>
        <div class="kpi-value" id="{{ $valueId }}">{{ number_format($value, 1) }}%</div>
        <div @class(['kpi-delta', 'up' => $deltaUp, 'down' => ! $deltaUp]) @if ($deltaId) id="{{ $deltaId }}" @endif>
            {{ $delta }}</div>
        <span class="kpi-click-hint">{{ $hint }}</span>
    </div>
</div>
