<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enterprise Brands (Homepage)
    |--------------------------------------------------------------------------
    |
    | Surfaced as social-proof logo strip on the homepage hero. Order matters
    | — frontend renders left-to-right. Defaults are the 6 brands Ali has
    | shipped projects for; override via env (comma-separated) only if needed.
    |
    */

    'enterprise_brands' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'HOMEPAGE_ENTERPRISE_BRANDS',
            'Outskill,Telkomsel,XIAOMI,MPA Singapore,Satnusa,BOTIKA'
        ))
    ))),
];
