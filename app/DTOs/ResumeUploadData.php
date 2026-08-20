<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Http\Requests\Resume\StoreResumeRequest;
use App\Models\Company;
use Illuminate\Http\UploadedFile;

final readonly class ResumeUploadData
{
    public function __construct(
        public Company $company,
        public int $uploadedBy,
        public UploadedFile $file,
    ) {}

    public static function fromRequest(StoreResumeRequest $request, Company $company): self
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');

        /** @var \App\Models\User $user */
        $user = $request->user();

        return new self(
            company: $company,
            uploadedBy: $user->id,
            file: $file,
        );
    }
}
