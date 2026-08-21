<?php

declare(strict_types=1);

namespace App\Actions\Resume;

use App\Models\Resume;
use App\Models\ResumeTemplate;
use Illuminate\Database\DatabaseManager;

/**
 * Re-styles one already-parsed document. Presentation only: `parsed_data` is
 * untouched, so nothing is re-read and no sidecar call happens — the preview and
 * the PDF simply lay the same data out in the new template.
 */
final readonly class ChangeResumeTemplate
{
    public function __construct(private DatabaseManager $db) {}

    public function handle(Resume $resume, ResumeTemplate $template): Resume
    {
        $this->db->transaction(function () use ($resume, $template): void {
            $resume->update(['resume_template_id' => $template->id]);
        });

        return $resume->refresh();
    }
}
