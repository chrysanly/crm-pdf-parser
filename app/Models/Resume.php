<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ResumeStatus;
use Database\Factories\ResumeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $company_id
 * @property int|null $resume_template_id
 * @property int $uploaded_by
 * @property string $original_filename
 * @property string $stored_path
 * @property string $file_hash
 * @property int $file_size
 * @property int|null $page_count
 * @property ResumeStatus $status
 * @property string|null $candidate_name
 * @property string|null $candidate_email
 * @property array<string, mixed>|null $parsed_data
 * @property string|null $failure_reason
 * @property Carbon|null $parsed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company $company
 * @property-read ResumeTemplate|null $resumeTemplate
 * @property-read User $uploader
 */
#[Fillable([
    'company_id',
    'resume_template_id',
    'uploaded_by',
    'original_filename',
    'stored_path',
    'file_hash',
    'file_size',
    'page_count',
    'status',
    'candidate_name',
    'candidate_email',
    'parsed_data',
    'failure_reason',
    'parsed_at',
])]
class Resume extends Model
{
    /** @use HasFactory<ResumeFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ResumeStatus::class,
            'parsed_data' => 'array',
            'parsed_at' => 'datetime',
            'file_size' => 'integer',
            'page_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The house style this document was produced with, frozen at upload so a
     * later change to the company never restyles it.
     *
     * @return BelongsTo<ResumeTemplate, $this>
     */
    public function resumeTemplate(): BelongsTo
    {
        return $this->belongsTo(ResumeTemplate::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeParsed(Builder $query): Builder
    {
        return $query->where('status', ResumeStatus::Parsed);
    }
}
