<?php

declare(strict_types=1);

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ResumeController;
use App\Http\Controllers\ResumeFileController;
use App\Http\Controllers\ResumePdfController;
use App\Http\Controllers\ResumeTemplateController;
use Illuminate\Support\Facades\Route;

/*
| Companies + resumes. Every route is named, inside the auth+verified group, and
| backed by a Policy (RULES §5.1). Public identifiers only: companies bind by
| slug, resumes by ULID (SCHEMA §A2).
*/

Route::middleware(['auth', 'verified'])->group(function (): void {
    // House styles. No show page: the index carries everything, editing is the
    // only detail view.
    Route::resource('resume-templates', ResumeTemplateController::class)
        ->except(['show'])
        ->scoped(['resume_template' => 'slug'])
        ->parameters(['resume-templates' => 'resume_template']);

    Route::resource('companies', CompanyController::class)->scoped([
        'company' => 'slug',
    ]);

    // Upload is nested under the company it is filed against.
    Route::post('companies/{company}/resumes', [ResumeController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('companies.resumes.store');

    Route::get('resumes/{resume}', [ResumeController::class, 'show'])->name('resumes.show');
    Route::get('resumes/{resume}/file', ResumeFileController::class)->name('resumes.file');

    // The ATS document as a PDF — the preview's content, nothing else.
    Route::get('resumes/{resume}/pdf', ResumePdfController::class)->name('resumes.pdf');

    // Re-style an existing document without re-reading the PDF.
    Route::put('resumes/{resume}/template', [ResumeController::class, 'template'])
        ->name('resumes.template');
    Route::post('resumes/{resume}/reparse', [ResumeController::class, 'reparse'])
        ->middleware('throttle:10,1')
        ->name('resumes.reparse');
    Route::delete('resumes/{resume}', [ResumeController::class, 'destroy'])->name('resumes.destroy');
});
