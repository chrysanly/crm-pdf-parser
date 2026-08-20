<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Frontend Stack
    |--------------------------------------------------------------------------
    |
    | Resolved once by the STARTUP.md first-run gate and persisted here + in
    | .env (APP_STACK). One of: 'blade' | 'react' | 'vue'. Setup and conventions
    | branch on this value.
    |
    */

    'stack' => env('APP_STACK', 'blade'),

    /*
    |--------------------------------------------------------------------------
    | Spatie Permission (RBAC)
    |--------------------------------------------------------------------------
    |
    | When false, AppServiceProvider registers a Gate::before bypass so every
    | authorization check passes (a fast-dev default). Flip to true — and run
    | features:install / vendor:publish — before shipping real authorization.
    |
    */

    'spatie' => env('ENABLE_SPATIE', false),

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Managed by `php artisan auth:setup`. `mode` drives registration + social;
    | `allowed_domains` restricts sign-in to a comma list of company domains
    | (empty = no restriction), enforced in the Socialite callback.
    |
    */

    'auth' => [
        'mode' => env('AUTH_MODE', 'login-register'),
        'registration' => env('AUTH_REGISTRATION', true),
        'social' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('AUTH_SOCIAL', '')),
        ))),
        'allowed_domains' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('AUTH_ALLOWED_DOMAINS', '')),
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Installable Feature Modules
    |--------------------------------------------------------------------------
    |
    | Each module is turned on with its ENABLE_* flag, then wired by
    | `php artisan features:install`: composer require the package(s), publish
    | the vendor config, and copy the listed stubs into app/ (as real .php).
    |
    |   enabled  — env flag gating the module.
    |   packages — composer packages to require (skipped if already installed).
    |   publish  — vendor:publish --provider entries to run.
    |   stubs    — map of stub file => destination path (relative to base_path).
    |
    */

    'modules' => [

        'pdf' => [
            'enabled' => env('ENABLE_PDF', false),
            'packages' => [
                'barryvdh/laravel-dompdf',
            ],
            'publish' => [
                'Barryvdh\DomPDF\ServiceProvider',
            ],
            'stubs' => [
                'stubs/features/pdf/PdfService.stub' => 'app/Services/Pdf/PdfService.php',
                'stubs/features/pdf/GeneratePdfJob.stub' => 'app/Jobs/GeneratePdfJob.php',
            ],
        ],

        'excel' => [
            'enabled' => env('ENABLE_EXCEL', false),
            'packages' => [
                'maatwebsite/excel',
            ],
            'publish' => [
                'Maatwebsite\Excel\ExcelServiceProvider',
            ],
            'stubs' => [
                'stubs/features/excel/ExcelService.stub' => 'app/Services/Excel/ExcelService.php',
                'stubs/features/excel/ArrayExport.stub' => 'app/Services/Excel/Exports/ArrayExport.php',
                'stubs/features/excel/GenerateExcelJob.stub' => 'app/Jobs/GenerateExcelJob.php',
            ],
        ],

    ],

];
