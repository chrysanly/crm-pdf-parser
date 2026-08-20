<?php

declare(strict_types=1);

namespace App\Http\Requests\Resume;

use App\Models\Company;
use App\Models\Resume;
use Illuminate\Foundation\Http\FormRequest;

final class StoreResumeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');

        return $company instanceof Company
            && $company->is_active
            && ($this->user()?->can('create', Resume::class) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // RULES §5.7: the extension is never trusted — mimetypes checks the
            // real content type, and size is capped server-side.
            'file' => [
                'required',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf',
                'max:'.config('crm.max_resume_kb'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => __('Only PDF resumes can be parsed.'),
            'file.mimetypes' => __('That file is not a real PDF.'),
            'file.max' => __('The resume must be smaller than :max MB.', [
                'max' => (int) round(((int) config('crm.max_resume_kb')) / 1024),
            ]),
        ];
    }
}
