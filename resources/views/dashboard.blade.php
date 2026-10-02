<x-layouts.app title="OEE Smart Monitoring — FKS Food Lini Packaging">
    @include('dashboard.header')

    @if (session('status'))
        <div class="flash flash-success" role="status">✅ {{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="flash flash-error" role="alert">⚠️ {{ $errors->first() }}</div>
    @endif

    @if ($dashboard['filters']['maxDate'] === null)
        <div class="card empty-state">
            <h2>Belum ada data produksi</h2>
            <p>Klik <b>Import Excel</b> di kanan atas untuk mengunggah file DCR Produksi (Packing), atau jalankan
                <code>php artisan oee:import-dcr</code> untuk mengimpor semua file di <code>storage/app/imports</code>.</p>
        </div>
    @else
        @include('dashboard.datasource-bar')

        <div class="toast-box" id="toastNotice" role="status">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#FCD34D" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            <span id="toastText"></span>
        </div>

        @include('dashboard.overview')
        @include('dashboard.packaging')
        @include('dashboard.metric-modal')

        <script type="application/json" id="dashboard-payload">@json($dashboard)</script>
    @endif
</x-layouts.app>
