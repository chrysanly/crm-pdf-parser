<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

/**
 * v1 authorization: any verified CRM user manages companies; only an inactive
 * company with no resumes can be hard-deleted. Tighten to roles when
 * ENABLE_SPATIE=true (see CLAUDE.md §5).
 */
final class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Company $company): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Company $company): bool
    {
        return $company->trashed() === false;
    }

    public function delete(User $user, Company $company): bool
    {
        return $company->trashed() === false;
    }
}
