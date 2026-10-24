<?php

// คัดลอกไฟล์นี้เป็น config.php แล้วแก้ค่าให้ตรงกับเครื่อง
// config.php อยู่ใน .gitignore — ห้าม commit

return [
    'app' => [
        'name'     => 'PhaiERP',
        'url'      => 'http://localhost/phaierp',
        'debug'    => false,
        'timezone' => 'Asia/Bangkok',
        'locale'   => 'th',
    ],

    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'phaierp',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    'session' => [
        'name'     => 'phaierp',
        'lifetime' => 480, // นาที
    ],

    'ai' => [
        'enabled'  => false,
        'provider' => 'claude', // claude | gemini
        'api_key'  => '',
        'model'    => '', // empty = default of the provider
        'price_in'  => 0, // USD per million input tokens, for the usage log
        'price_out' => 0, // USD per million output tokens
    ],

    'pdf' => [
        'browser' => '', // Edge / Chrome used to make PDF files; empty = found automatically
    ],
];
