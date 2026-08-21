<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Resume;
use App\Services\Pdf\AtsResumePdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Downloads the ATS resume as a PDF. Anyone who may view the preview may export
 * it — the file holds the same content the page already shows. The *original*
 * upload stays uploader-only (`ResumeFileController`).
 */
final class ResumePdfController extends Controller
{
    public function __invoke(Resume $resume, AtsResumePdf $pdf): Response
    {
        $this->authorize('view', $resume);

        abort_if($resume->parsed_data === null, 404, 'This resume has not been parsed yet.');

        $resume->load(['company.resumeTemplate', 'resumeTemplate']);

        return response($pdf->render($resume), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->filename($resume).'"',
        ]);
    }
}
