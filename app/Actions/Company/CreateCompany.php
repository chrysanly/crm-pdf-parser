<?php

declare(strict_types=1);

namespace App\Actions\Company;

use App\DTOs\CompanyData;
use App\Models\Company;
use App\Services\Storage\LogoStorage;
use Illuminate\Database\DatabaseManager;

final readonly class CreateCompany
{
    public function __construct(
        private DatabaseManager $db,
        private LogoStorage $logos,
        private UniqueCompanySlug $slugs,
    ) {}

    public function handle(CompanyData $data): Company
    {
        $logoPath = $data->logo === null ? null : $this->logos->store($data->logo);

        try {
            return $this->db->transaction(fn (): Company => Company::create([
                ...$data->toAttributes(),
                'slug' => $this->slugs->for($data->name),
                'logo_path' => $logoPath,
            ]));
        } catch (\Throwable $exception) {
            // Don't leave an orphaned file behind if the insert fails.
            $this->logos->delete($logoPath);

            throw $exception;
        }
    }
}
