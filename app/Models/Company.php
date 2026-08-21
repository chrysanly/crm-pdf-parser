<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LogoPlacement;
use App\Enums\LogoSize;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Relations, casts and scopes only — business logic lives in Actions (RULES §4).
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $slug
 * @property string|null $industry
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $website
 * @property string|null $logo_path
 * @property LogoPlacement $logo_placement
 * @property LogoSize $logo_size
 * @property string $brand_color
 * @property int $resume_template_id
 * @property string|null $formatting_notes
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Resume> $resumes
 * @property-read int|null $resumes_count
 * @property-read ResumeTemplate $resumeTemplate
 */
#[Fillable([
    'name',
    'slug',
    'industry',
    'contact_email',
    'contact_phone',
    'website',
    'logo_path',
    'logo_placement',
    'logo_size',
    'brand_color',
    'resume_template_id',
    'formatting_notes',
    'is_active',
])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    use HasUlids;
    use SoftDeletes;

    /**
     * Only `public_id` is a ULID — the primary key stays an auto-increment bigint.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'logo_placement' => LogoPlacement::class,
            'logo_size' => LogoSize::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ResumeTemplate, $this>
     */
    public function resumeTemplate(): BelongsTo
    {
        return $this->belongsTo(ResumeTemplate::class);
    }

    /**
     * @return HasMany<Resume, $this>
     */
    public function resumes(): HasMany
    {
        return $this->hasMany(Resume::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $like = '%'.trim($term).'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('name', 'like', $like)
                ->orWhere('industry', 'like', $like);
        });
    }

    /**
     * Section order actually in force, i.e. the assigned template's.
     *
     * @return list<string>
     */
    public function effectiveSectionOrder(): array
    {
        return $this->resumeTemplate->effectiveSectionOrder();
    }
}
