<?php

declare(strict_types=1);

namespace App\Actions\ResumeTemplate;

use App\Exceptions\ResumeTemplateInUseException;
use App\Models\ResumeTemplate;
use Illuminate\Database\DatabaseManager;

/**
 * Soft delete: the template leaves the pickers but the resumes produced with it
 * keep rendering, because their frozen reference still resolves.
 */
final readonly class DeleteResumeTemplate
{
    public function __construct(private DatabaseManager $db) {}

    public function handle(ResumeTemplate $template): void
    {
        $companies = $template->companies()->count();

        if ($companies > 0) {
            throw ResumeTemplateInUseException::forCompanies($companies);
        }

        $this->db->transaction(function () use ($template): void {
            $template->update(['is_active' => false]);
            $template->delete();
        });
    }
}
