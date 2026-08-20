<?php

declare(strict_types=1);

namespace App\Actions\Company;

use App\Models\Company;
use Illuminate\Database\DatabaseManager;

/**
 * Soft delete: the company disappears from the CRM but its resumes stay
 * auditable. Files are kept until a retention job prunes them (PRD §6).
 */
final readonly class DeleteCompany
{
    public function __construct(private DatabaseManager $db) {}

    public function handle(Company $company): void
    {
        $this->db->transaction(function () use ($company): void {
            $company->update(['is_active' => false]);
            $company->delete();
        });
    }
}
