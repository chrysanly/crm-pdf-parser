<?php

declare(strict_types=1);

namespace App\Http\Requests\ResumeTemplate;

use App\Models\ResumeTemplate;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateResumeTemplateRequest extends FormRequest
{
    use ResumeTemplateValidationRules;

    public function authorize(): bool
    {
        $template = $this->route('resume_template');

        return $template instanceof ResumeTemplate
            && ($this->user()?->can('update', $template) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->resumeTemplateRules();
    }

    protected function prepareForValidation(): void
    {
        $this->prepareResumeTemplateInput();
    }
}
