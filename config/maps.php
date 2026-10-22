<?php

/**
 * Lightweight map tile configuration for customer location pin picker.
 * Default: OpenStreetMap raster tiles (no API key). Override via .env.
 * No geocoding — coordinates are set by map click or manual entry only.
 */
return [

    'enabled' => filter_var(env('MAPS_ENABLED', true), FILTER_VALIDATE_BOOL),

    /*
    | Leaflet-compatible XYZ tile URL. Placeholders: {s} {z} {x} {y}
    */
    'tile_url' => env(
        'MAPS_TILE_URL',
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'
    ),

    'attribution' => env(
        'MAPS_ATTRIBUTION',
        '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>'
    ),

    'default_center' => [
        'lat' => (float) env('MAPS_DEFAULT_LAT', 24.7136),
        'lng' => (float) env('MAPS_DEFAULT_LNG', 46.6753),
    ],

    'default_zoom' => (int) env('MAPS_DEFAULT_ZOOM', 11),

    'max_zoom' => (int) env('MAPS_MAX_ZOOM', 19),
];
