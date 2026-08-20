<?php

declare(strict_types=1);

namespace App\Actions\Company;

use App\DTOs\CompanyData;
use App\Models\Company;
use App\Services\Storage\LogoStorage;
use Illuminate\Database\DatabaseManager;

final readonly class UpdateCompany
{
    public function __construct(
        private DatabaseManager $db,
        private LogoStorage $logos,
        private UniqueCompanySlug $slugs,
    ) {}

    public function handle(Company $company, CompanyData $data): Company
    {
        $previousLogo = $company->logo_path;

        $logoPath = match (true) {
            $data->logo !== null => $this->logos->store($data->logo),
            $data->removeLogo => null,
            default => $previousLogo,
        };

        $this->db->transaction(function () use ($company, $data, $logoPath): void {
            $company->fill([
                ...$data->toAttributes(),
                'logo_path' => $logoPath,
            ]);

            // Renaming a company re-slugs it; the old slug is released.
            if ($company->isDirty('name')) {
                $company->slug = $this->slugs->for($data->name, $company->id);
            }

            $company->save();
        });

        if ($logoPath !== $previousLogo) {
            $this->logos->delete($previousLogo);
        }

        return $company->refresh();
    }
}
