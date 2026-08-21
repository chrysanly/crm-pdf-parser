<?php

declare(strict_types=1);

namespace App\Http\Requests\ResumeTemplate;

use App\Enums\TemplateLayout;
use App\Services\Ats\AtsResumeFormatter;
use Illuminate\Validation\Rule;

/**
 * One source of truth for template input rules — shared by Store and Update so
 * the two can never drift (RULES §3 DRY).
 */
trait ResumeTemplateValidationRules
{
    /**
     * @return array<string, mixed>
     */
    protected function resumeTemplateRules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'layout' => ['required', Rule::enum(TemplateLayout::class)],
            'section_order' => ['nullable', 'array', 'max:'.count(AtsResumeFormatter::SUPPORTED_SECTIONS)],
            'section_order.*' => ['required', 'string', 'distinct', Rule::in(AtsResumeFormatter::SUPPORTED_SECTIONS)],
            'is_active' => ['boolean'],
            // Optional: a sample resume whose printed section order the template
            // adopts. Same upload rules as a candidate resume (RULES §5.7).
            'sample_resume' => [
                'nullable',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf',
                'max:'.config('crm.max_resume_kb'),
            ],
            'remove_sample' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sample_resume.mimes' => __('The sample must be a PDF.'),
            'sample_resume.mimetypes' => __('That file is not a real PDF.'),
            'section_order.*.distinct' => __('Each section can only appear once.'),
        ];
    }

    /**
     * Normalisation belongs here, not in the Action (ARCHITECTURE §3).
     */
    protected function prepareResumeTemplateInput(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'is_active' => $this->boolean('is_active'),
            'remove_sample' => $this->boolean('remove_sample'),
        ]);
    }
}
