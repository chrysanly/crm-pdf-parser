<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ResumeStatus;
use App\Models\Resume;
use App\Models\User;

final class ResumePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Resume $resume): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Downloading the original PDF is a PII-exposing action: uploader only.
     */
    public function download(User $user, Resume $resume): bool
    {
        return $resume->uploaded_by === $user->id;
    }

    public function reparse(User $user, Resume $resume): bool
    {
        return $resume->status !== ResumeStatus::Processing;
    }

    public function delete(User $user, Resume $resume): bool
    {
        return $resume->uploaded_by === $user->id;
    }
}
