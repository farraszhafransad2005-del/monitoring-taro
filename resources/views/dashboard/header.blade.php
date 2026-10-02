<header>
    <div class="header-brand">
        <div class="fks-logo-box">
            <svg viewBox="0 0 320 110" width="130" height="42" xmlns="http://www.w3.org/2000/svg" role="img"
                aria-label="FKS Food">
                <text x="5" y="78" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"
                    font-weight="900" font-size="64" fill="#3D3D3D" letter-spacing="-1">FKS</text>
                <path d="M 175 14 C 245 6, 315 36, 305 72 C 295 102, 205 110, 145 96 C 115 86, 130 24, 175 14 Z"
                    fill="#E5A91A" />
                <text x="165" y="76" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"
                    font-weight="700" font-size="52" fill="#FFFFFF">Food</text>
            </svg>
        </div>

        <div class="header-titles" style="border-left:2px solid var(--border); padding-left:14px;">
            <h1>OEE Smart Monitoring Dashboard</h1>
            <p>Sistem Pengawasan Performa &amp; Data Live Mesin Packaging — <b>FKS Food</b></p>
        </div>
    </div>

    @if ($dashboard['filters']['maxDate'] !== null)
        <nav class="nav-tabs">
            <button type="button" @class(['nav-btn', 'active' => $activeTab === 'overview']) data-tab="overview">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                Ringkasan OEE
            </button>
            <button type="button" @class(['nav-btn', 'active' => $activeTab === 'packaging']) data-tab="packaging">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
                Detail Lini Packaging <span
                    style="background:var(--accent); color:#1F2227; font-size:10px; font-weight:800; padding:2px 6px; border-radius:10px;">LIVE</span>
            </button>
        </nav>
    @endif

    <div class="header-right">
        <div class="export-actions">
            <form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data" id="importForm">
                @csrf
                <label class="btn btn-dark" style="cursor:pointer;" title="Import file DCR Produksi (Packing) .xlsx">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2.2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                    <span id="importLabel">Import Excel</span>
                    <input type="file" name="workbook" accept=".xlsx" style="display:none;" data-auto-submit>
                </label>
            </form>

            @if ($dashboard['filters']['maxDate'] !== null)
                <a class="btn btn-dark" style="text-decoration:none;"
                    href="{{ route('dashboard.export', array_filter(['date' => $dashboard['filters']['date'], 'shift' => $dashboard['filters']['shift']])) }}"
                    title="Download Data OEE Spreadsheet (CSV)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2.2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="8" y1="13" x2="16" y2="13"></line>
                        <line x1="8" y1="17" x2="16" y2="17"></line>
                    </svg>
                    Export CSV
                </a>
                <button type="button" class="btn btn-primary" data-action="export-image"
                    title="Download Screenshot HD Gambar Dashboard (PNG)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2.2">
                        <path
                            d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z">
                        </path>
                        <circle cx="12" cy="13" r="4"></circle>
                    </svg>
                    Download Gambar
                </button>
            @endif
        </div>

        <div class="live-badge"><span class="live-dot"></span><span id="liveStatusText">FKS Live Monitoring
                Aktif</span></div>
        <div class="clock-box" id="sysClock">--:--:-- WIB</div>
    </div>
</header>
