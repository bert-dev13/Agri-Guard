<?php

/**
 * Curated Amulung barangay flood exposure tiers used for map coloring and listings.
 * Names aligned with database seed (database/data/amulung_barangay_names.php); legacy GIS spellings kept as aliases.
 *
 * @return array{
 *     high: list<string>,
 *     moderate: list<string>,
 *     low: list<string>,
 *     aliases: array<string, 'high'|'moderate'|'low'>
 * }
 */
return [
    /** TTL for computed municipality flood-tier listings (minutes). Env: BARANGAY_FLOOD_OVERVIEW_CACHE_MINUTES */
    'cache_ttl_minutes' => (int) env('BARANGAY_FLOOD_OVERVIEW_CACHE_MINUTES', 30),

    'high' => [
        'Abolo',
        'Agguirit',
        'Alitungtung',
        'Annabuculan',
        'Annafatan',
        'Anquiray',
        'Babayuan',
        'Baccuit',
        'Baculud',
        'Balauini',
        'Bauan',
        'Calamagui',
        'Casingsingan Norte',
        'Casingsingan Sur',
        'Centro',
        'Concepcion',
        'Dafunganay',
        'Dugayung',
        'Estefania',
        'Gabut',
        'Goran',
        'Jurisdiccion',
        'Logung',
        'Marobbob',
        'Monte Alegre',
        'Pacac-Grande',
        'Pacac-Pequeño',
        'Palacu',
        'Palayag',
        'Tana',
        'Unag',
    ],
    'moderate' => [
        'Calintaan',
        'Caratacat',
        'Cordova',
        'Dadda',
        'Gangauan',
        'Magogod',
        'Manalo',
        'Masical',
        'Nangalasauan',
    ],
    'low' => [
        'Bacring',
        'Bayabat',
        'Catarauan',
        'La Suerte',
        'Nabbialan',
        'Nagsabaran',
        'Nanuccauan',
    ],
    'aliases' => [
        // Historical / GeoJSON spelling variants mapped to tiers (canonical lookup keys)
        'alitungtung' => 'high',
        'aggurit' => 'high',
        'pacacgrand' => 'high',
        'pacacpequeo' => 'high',
        'backring' => 'low',
        'cataruan' => 'low',
    ],
];
