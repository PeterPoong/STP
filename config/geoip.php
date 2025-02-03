<?php

return [

    'cache' => 'all',

    'cache_tags' => null,

    'cache_expires' => 30,

    'default' => 'ipapi',

    'services' => [
        'ipapi' => [
            'url' => 'http://api.ipapi.com/api/',
            'key' => env('GEOIP_IPAPI_KEY'),
        ],
    ],

    'include_currency' => true,

];
