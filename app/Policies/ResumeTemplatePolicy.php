<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ResumeTemplate;
use App\Models\User;

/**
 * v1 authorization: any verified CRM user manages templates. A template still
 * assigned to a company cannot be archived — companies would be left without a
 * house style. Tighten to roles when ENABLE_SPATIE=true (see CLAUDE.md §5).
 */
final class ResumeTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ResumeTemplate $template): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ResumeTemplate $template): bool
    {
        return $template->trashed() === false;
    }

    public function delete(User $user, ResumeTemplate $template): bool
    {
        return $template->trashed() === false
            && $template->companies()->count() === 0;
    }
}
