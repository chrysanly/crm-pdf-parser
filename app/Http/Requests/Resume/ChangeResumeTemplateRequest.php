<?php

declare(strict_types=1);

namespace App\Http\Requests\Resume;

use App\Models\Resume;
use App\Models\ResumeTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Re-styling one document: the template is referenced by its public slug, never
 * an internal id (SCHEMA §A2).
 */
final class ChangeResumeTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $resume = $this->route('resume');

        return $resume instanceof Resume
            && ($this->user()?->can('changeTemplate', $resume) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resume_template' => [
                'required',
                'string',
                Rule::exists('resume_templates', 'slug')->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * The chosen template, resolved from the validated slug.
     */
    public function template(): ResumeTemplate
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ResumeTemplate::query()
            ->where('slug', (string) $validated['resume_template'])
            ->firstOrFail();
    }
}
