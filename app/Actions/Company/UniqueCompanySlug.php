<?php

declare(strict_types=1);

namespace App\Actions\Company;

use App\Models\Company;
use Illuminate\Support\Str;

/**
 * Slugs are public identifiers (SCHEMA §A2) and UNIQUE in the database, so they
 * are generated in one place — including for soft-deleted rows, which still hold
 * the slug.
 */
final readonly class UniqueCompanySlug
{
    public function for(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $base = $base === '' ? 'company' : Str::limit($base, 150, '');
        $slug = $base;
        $suffix = 1;

        while ($this->taken($slug, $ignoreId)) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    private function taken(string $slug, ?int $ignoreId): bool
    {
        return Company::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
