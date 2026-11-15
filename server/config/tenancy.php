<?php

/*
 * Multi-hotel (same pattern as kulakku): one central database lists the hotels, every hotel has its own
 * database. The CMS picks the hotel with "Kode Hotel" before login; TVs and PMS are routed per request.
 * With TENANCY_ENABLED=false the app behaves as a single-hotel install (local development, most tests).
 */
return [
    'enabled' => (bool) env('TENANCY_ENABLED', false),

    // Connection holding the hotel list, routing indexes, sessions, cache and jobs (never switched).
    'central_connection' => 'central',

    // Connection whose database is switched to the current hotel.
    'tenant_connection' => env('DB_CONNECTION', 'pgsql'),

    // New hotel databases are named <prefix><slug> (the DB user needs CREATE rights on this prefix).
    'database_prefix' => env('TENANCY_DB_PREFIX', 'sangatv_'),

    // Device cookie remembering the hotel code on the CMS login page (1 year).
    'remember_cookie' => 'sangatv_hotel',

    // TVs and PMS that do not (yet) send a hotel identifier belong to this hotel (the first hotel, Napolin).
    'legacy_hotel' => env('TENANCY_LEGACY_HOTEL'),

    'reserved' => ['admin', 'sijalucom', 'api', 'vendor', 'central', 'www', 'storage', 'up', 'privasi'],
];
