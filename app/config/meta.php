<?php

return [
    'app_id' => trim((string) Env::get('META_APP_ID', '')),
    'app_secret' => trim((string) Env::get('META_APP_SECRET', '')),
    'redirect_uri' => trim((string) Env::get('META_REDIRECT_URI', '')),
    'graph_version' => trim((string) Env::get('META_GRAPH_VERSION', 'v25.0')),
    'scopes' => [
        'ads_read',
        'ads_management',
        'business_management'
    ]
];
