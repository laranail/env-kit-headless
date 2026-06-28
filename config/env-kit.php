<?php

declare(strict_types=1);

return [

    // The .env file EnvKit operates on by default.
    'path' => env('ENV_KIT_PATH', base_path('.env')),

    // When true, bare set()/forget()/rename() commit immediately; when false they
    // stage onto an implicit session you commit with EnvKit::save().
    'auto_commit' => true,

    // Take a timestamped backup before each write.
    'auto_backup' => true,
    'backup_path' => storage_path('env-kit/backups'),
    'backup_retention' => 30, // 0 = keep all

    // Block writes in production unless explicitly opted in (->allowProduction()).
    'protect_production' => true,

    // Layered key policy (§9).
    'protected_keys' => ['APP_KEY', 'DB_PASSWORD'], // never writable
    'hidden_keys' => ['APP_KEY', '*_PASSWORD', '*_SECRET', '*_TOKEN'], // masked in listings
    'editable_keys' => [], // empty = all non-protected are editable (UI/API)

    // ${VAR} interpolation on read.
    'interpolation' => [
        'resolve' => true,
        'on_undefined' => 'empty', // 'empty' | 'throw'
    ],

    // Audit trail: who changed what, when. Values are redacted (hidden_keys).
    'audit' => [
        'enabled' => true,
        'path' => storage_path('env-kit/audit.log'), // JSON-lines (file sink, the default)
    ],

    // Per-value encryption-at-rest. The `laravel` driver uses the APP_KEY
    // Encrypter; register your own via EnvKitManager::extend('driver', fn () => …).
    'encryption' => [
        'driver' => 'laravel',
    ],

    // schema settings land in a later slice.

];
