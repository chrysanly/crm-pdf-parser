<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Resume;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Resumes live on a private disk, so the original PDF is only ever served through
 * this authorized route — never a public URL (RULES §5.7).
 */
final class ResumeFileController extends Controller
{
    public function __invoke(Resume $resume, FilesystemFactory $filesystem): StreamedResponse
    {
        $this->authorize('download', $resume);

        return $filesystem->disk((string) config('crm.resume_disk'))->download(
            $resume->stored_path,
            $resume->original_filename,
        );
    }
}
