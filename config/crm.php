<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Resume Storage
    |--------------------------------------------------------------------------
    |
    | Resumes are PII-dense, so they live on a PRIVATE disk and are served only
    | through ResumeFileController after a policy check (RULES §5.7). Never point
    | this at the `public` disk.
    |
    */

    'resume_disk' => env('RESUME_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Upload Limits
    |--------------------------------------------------------------------------
    |
    | Enforced server-side by the FormRequests. Kilobytes.
    |
    */

    'max_resume_kb' => (int) env('MAX_RESUME_KB', 10240),
    'max_logo_kb' => (int) env('MAX_LOGO_KB', 2048),

];
