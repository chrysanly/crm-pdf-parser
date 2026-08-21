<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use App\Enums\LogoPlacement;
use App\Enums\LogoSize;
use App\Enums\ResumeTemplate;
use Illuminate\Validation\Rule;

/**
 * One source of truth for company input rules — shared by Store and Update so the
 * two can never drift (RULES §3 DRY).
 */
trait CompanyValidationRules
{
    /**
     * @return array<string, mixed>
     */
    protected function companyRules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'industry' => ['nullable', 'string', 'max:100'],
            'contact_email' => ['nullable', 'email:rfc', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20', 'regex:/^\+[1-9]\d{6,18}$/'],
            'website' => ['nullable', 'url', 'max:255'],
            'brand_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'resume_template' => ['required', Rule::enum(ResumeTemplate::class)],
            'section_order' => ['nullable', 'array', 'max:7'],
            'section_order.*' => ['required', 'string', Rule::in([
                'details', 'summary', 'experience', 'education', 'skills', 'certifications', 'languages',
            ])],
            'formatting_notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            // RULES §5.7: mime + dimensions + size, all server-side. No SVG — it cannot
            // be safely re-encoded.
            'logo' => [
                'nullable',
                'file',
                'mimes:png,jpg,jpeg,webp',
                'mimetypes:image/png,image/jpeg,image/webp',
                'max:'.config('crm.max_logo_kb'),
                'dimensions:min_width=64,min_height=64,max_width=4000,max_height=4000',
            ],
            'remove_logo' => ['boolean'],
            'logo_placement' => ['required', Rule::enum(LogoPlacement::class)],
            'logo_size' => ['required', Rule::enum(LogoSize::class)],
        ];
    }

    /**
     * Normalisation belongs here, not in the Action (ARCHITECTURE §3).
     */
    protected function prepareCompanyInput(): void
    {
        $phone = $this->input('contact_phone');

        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'contact_email' => is_string($this->input('contact_email'))
                ? mb_strtolower(trim($this->input('contact_email')))
                : $this->input('contact_email'),
            'contact_phone' => is_string($phone) && $phone !== ''
                ? preg_replace('/(?!^\+)\D/', '', trim($phone))
                : $phone,
            'brand_color' => is_string($this->input('brand_color'))
                ? mb_strtoupper(trim($this->input('brand_color')))
                : $this->input('brand_color'),
            'is_active' => $this->boolean('is_active'),
            'remove_logo' => $this->boolean('remove_logo'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_phone.regex' => __('Enter the phone in international format, e.g. +971501234567.'),
            'brand_color.regex' => __('Use a six-digit hex colour, e.g. #1F2937.'),
            'logo.dimensions' => __('The logo must be at least 64×64 and at most 4000×4000 pixels.'),
        ];
    }
}
