<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ResumeStatus;
use App\Enums\TemplateLayout;
use Database\Factories\ResumeTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A reusable resume house style: one of the built-in layouts plus the section
 * order it prints them in. Companies point at one; a resume freezes the one it
 * was uploaded against (PRD §4).
 *
 * Relations, casts and scopes only — business logic lives in Actions (RULES §4).
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property TemplateLayout $layout
 * @property list<string>|null $section_order
 * @property string|null $sample_path
 * @property string|null $sample_filename
 * @property ResumeStatus|null $sample_status
 * @property string|null $sample_failure_reason
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Company> $companies
 * @property-read int|null $companies_count
 * @property-read Collection<int, Resume> $resumes
 * @property-read int|null $resumes_count
 */
#[Fillable([
    'name',
    'slug',
    'description',
    'layout',
    'section_order',
    'sample_path',
    'sample_filename',
    'sample_status',
    'sample_failure_reason',
    'is_active',
])]
class ResumeTemplate extends Model
{
    /** @use HasFactory<ResumeTemplateFactory> */
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
            'layout' => TemplateLayout::class,
            'section_order' => 'array',
            'sample_status' => ResumeStatus::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Company, $this>
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
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
                ->orWhere('description', 'like', $like);
        });
    }

    /**
     * True while the sample resume is still being read on the queue.
     */
    public function sampleInFlight(): bool
    {
        return in_array($this->sample_status, [ResumeStatus::Pending, ResumeStatus::Processing], true);
    }

    /**
     * Section order actually in force: this template's override, else the
     * layout's default.
     *
     * @return list<string>
     */
    public function effectiveSectionOrder(): array
    {
        $override = $this->section_order;

        return $override === null || $override === []
            ? $this->layout->defaultSectionOrder()
            : $override;
    }
}
