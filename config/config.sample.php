<?php
/**
 * Sample configuration.
 *
 * Copy this file to config/config.php and fill in real values.
 * config/config.php is git-ignored and must never be committed.
 */

return [
    // ---------------------------------------------------------------
    // Database (PDO MySQL)
    // ---------------------------------------------------------------
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'file_repository',
        'user'    => 'db_user',
        'pass'    => 'db_password',
        'charset' => 'utf8mb4',
    ],

    // ---------------------------------------------------------------
    // Application
    // ---------------------------------------------------------------
    'app' => [
        'name'     => 'File Repository',
        'env'      => 'production',           // 'production' | 'development'
        'debug'    => false,                  // show detailed errors when true
        // Base URL path the app is served from, e.g. '' for domain root
        // or '/repo' if installed in a sub-directory. No trailing slash.
        'base_url' => '',
        'timezone' => 'Asia/Kolkata',
    ],

    // ---------------------------------------------------------------
    // Security
    // ---------------------------------------------------------------
    'security' => [
        // Used for CSRF token salting / misc. Set to a long random string.
        'app_key'      => 'change-me-to-a-long-random-secret',
        'session_name' => 'frapp_session',
    ],

    // ---------------------------------------------------------------
    // Email / SMTP credentials (kept here so they are git-ignored and
    // never overwritten by automated deploys — see .cpanel.yml).
    // ---------------------------------------------------------------
    'mail' => [
        'transport'  => 'smtp',                 // 'smtp' | 'mail'
        'from_email' => 'no-reply@kdiscmis.org.in',
        'from_name'  => 'File Repository',
        'smtp'       => [
            'host'       => '',
            'port'       => 587,
            'username'   => '',
            'password'   => '',
            'encryption' => 'tls',              // 'tls' | 'ssl' | ''
        ],
    ],

    // ---------------------------------------------------------------
    // Storage paths (absolute or relative to project root)
    // ---------------------------------------------------------------
    'storage' => [
        'uploads' => __DIR__ . '/../storage/uploads',
        'reports' => __DIR__ . '/../storage/reports',
        'tmp'     => __DIR__ . '/../storage/tmp',
    ],

    // ---------------------------------------------------------------
    // Uploads — MIME + extension whitelist
    // ---------------------------------------------------------------
    'uploads' => [
        'max_size_bytes'      => 25 * 1024 * 1024, // 25 MB per file
        'allowed_extensions'  => ['pdf','doc','docx','xls','xlsx','csv','ppt','pptx','txt','jpg','jpeg','png','gif','zip'],
        'allowed_mime_types'  => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv',
            'text/plain',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/zip',
        ],
    ],
];
