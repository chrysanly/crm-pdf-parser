<?php

declare(strict_types=1);

namespace App\Actions\Resume;

use App\DTOs\ResumeUploadData;
use App\Enums\ResumeStatus;
use App\Jobs\ParseResumeJob;
use App\Models\Resume;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Stores the upload on the private disk and queues parsing. Idempotent by
 * UNIQUE(company_id, file_hash) — re-uploading the same document for the same
 * company returns the existing resume instead of creating a duplicate
 * (RULES §5.5, all three layers).
 */
final readonly class StoreResume
{
    public function __construct(
        private DatabaseManager $db,
        private FilesystemFactory $filesystem,
    ) {}

    public function handle(ResumeUploadData $data): Resume
    {
        $hash = (string) hash_file('sha256', $data->file->getRealPath());

        $existing = Resume::query()
            ->where('company_id', $data->company->id)
            ->where('file_hash', $hash)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $storedPath = $this->filesystem->disk($this->disk())->putFileAs(
            'resumes/'.$data->company->public_id,
            $data->file,
            Str::random(40).'.pdf',
        );

        if ($storedPath === false) {
            throw new \RuntimeException('The resume file could not be stored.');
        }

        try {
            $resume = $this->db->transaction(fn (): Resume => Resume::create([
                'company_id' => $data->company->id,
                // Frozen here: restyling the company later must not restyle
                // documents already produced (PRD §4).
                'resume_template_id' => $data->company->resume_template_id,
                'uploaded_by' => $data->uploadedBy,
                'original_filename' => $data->file->getClientOriginalName(),
                'stored_path' => $storedPath,
                'file_hash' => $hash,
                'file_size' => $data->file->getSize(),
                'status' => ResumeStatus::Pending,
            ]));
        } catch (UniqueConstraintViolationException) {
            // Lost a race against a concurrent identical upload: drop our copy and
            // hand back the row that won. Never a 500.
            $this->filesystem->disk($this->disk())->delete($storedPath);

            return Resume::query()
                ->where('company_id', $data->company->id)
                ->where('file_hash', $hash)
                ->firstOrFail();
        }

        ParseResumeJob::dispatch($resume->id);

        return $resume;
    }

    private function disk(): string
    {
        return (string) config('crm.resume_disk', 'local');
    }
}
