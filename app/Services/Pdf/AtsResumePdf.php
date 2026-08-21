<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\DTOs\Parsing\ParsedResume;
use App\Models\Resume;
use App\Services\Ats\AtsResumeFormatter;
use Barryvdh\DomPDF\PDF;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Renders the ATS document — and only the ATS document — as a real PDF.
 *
 * It formats through AtsResumeFormatter, so the file contains exactly what the
 * preview shows, in the template that was frozen on the resume. dompdf keeps the
 * text as text, which is what an ATS needs; a screenshot-based export would not.
 */
final readonly class AtsResumePdf
{
    /** dompdf cannot fetch remote files, so the logo is embedded. */
    private const array LOGO_MIME = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
    ];

    public function __construct(
        private AtsResumeFormatter $formatter,
        private PDF $pdf,
        private FilesystemFactory $filesystem,
    ) {}

    /**
     * @return string the PDF bytes
     */
    public function render(Resume $resume): string
    {
        $parsed = $resume->parsed_data;

        if ($parsed === null) {
            throw new RuntimeException('This resume has not been parsed yet.');
        }

        $company = $resume->company;

        $document = $this->formatter->format(
            ParsedResume::fromArray($parsed),
            $company,
            $resume->resumeTemplate,
        );

        return $this->pdf
            ->loadView('pdf.ats-resume', [
                'ats' => $document,
                'brandColor' => $company->brand_color,
                'companyName' => $company->name,
                'logoData' => $this->logoData($company->logo_path),
            ])
            ->setPaper('a4')
            ->setOption([
                // DejaVu covers the punctuation resumes actually contain (curly
                // quotes, dashes, accented names); subsetting keeps the file
                // small by embedding only the glyphs used.
                'enable_font_subsetting' => true,
                // Nothing in the document is remote — the logo is a data: URI —
                // so the fetcher stays off (RULES §5: no SSRF surface).
                'isRemoteEnabled' => false,
            ])
            ->output();
    }

    /**
     * A filename a recruiter can file without renaming it.
     */
    public function filename(Resume $resume): string
    {
        $candidate = $resume->candidate_name ?? pathinfo($resume->original_filename, PATHINFO_FILENAME);
        $slug = Str::slug($candidate) ?: 'resume';

        return $slug.'-'.Str::slug($resume->company->name).'.pdf';
    }

    private function logoData(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $disk = $this->filesystem->disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        $extension = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = self::LOGO_MIME[$extension] ?? null;

        if ($mime === null) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode((string) $disk->get($path));
    }
}
