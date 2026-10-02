<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OEE Targets (%)
    |--------------------------------------------------------------------------
    |
    | Target per pilar OEE yang dipakai untuk evaluasi di dashboard. OEE 85%
    | adalah benchmark world class manufaktur.
    |
    */

    'targets' => [
        'oee' => 85.0,
        'availability' => 90.0,
        'performance' => 90.0,
        'quality' => 99.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Planned Minutes per Shift
    |--------------------------------------------------------------------------
    |
    | Dipakai saat sel "Calender Time" di DCR kosong (rumus belum terhitung).
    | Nilai berasal dari kolom "Jam mesin" di sheet Reff Packing.
    |
    */

    'default_planned_minutes' => 449.5565,

    /*
    |--------------------------------------------------------------------------
    | Half Shift Unavailable Minutes
    |--------------------------------------------------------------------------
    |
    | Waktu non-operasional terencana untuk shift "Half" saat sel
    | "Unavailable Time" di DCR kosong.
    |
    */

    'half_shift_unavailable_minutes' => 180,

    'shifts' => [
        1 => 'Shift 1 (06.30 – 14.30 WIB)',
        2 => 'Shift 2 (14.30 – 22.30 WIB)',
        3 => 'Shift 3 (22.30 – 06.30 WIB)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Live Data (Node-RED)
    |--------------------------------------------------------------------------
    |
    | `node_red_url` ditarik oleh scheduler (`oee:pull-node-red`). Node-RED
    | juga bisa langsung POST ke /api/v1/oee-readings dengan bearer token
    | `ingest_token`.
    |
    */

    'node_red_url' => env('OEE_NODE_RED_URL'),

    'ingest_token' => env('OEE_INGEST_TOKEN'),

    'imports_path' => storage_path('app/imports'),

];
