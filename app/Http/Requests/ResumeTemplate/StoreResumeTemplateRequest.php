<?php

declare(strict_types=1);

namespace App\Http\Requests\ResumeTemplate;

use App\Models\ResumeTemplate;
use Illuminate\Foundation\Http\FormRequest;

final class StoreResumeTemplateRequest extends FormRequest
{
    use ResumeTemplateValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', ResumeTemplate::class) ?? false;
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
