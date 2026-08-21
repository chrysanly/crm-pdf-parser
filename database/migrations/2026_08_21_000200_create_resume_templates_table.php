<?php

declare(strict_types=1);

use App\Enums\TemplateLayout;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Promotes the resume house style from a per-company enum column to a managed
 * table, so a new style is data (SCHEMA §B4).
 *
 * Companies point at a template; resumes freeze the template they were uploaded
 * against, so restyling a company never rewrites documents already produced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resume_templates', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();           // SCHEMA §A2
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->string('description', 255)->nullable();
            $table->string('layout', 30)->default('classic');  // App\Enums\TemplateLayout
            $table->json('section_order')->nullable();         // list<string> overriding the layout default
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'name']);
            $table->index('deleted_at');
        });

        $this->seedBuiltInTemplates();

        Schema::table('companies', function (Blueprint $table): void {
            $table->foreignId('resume_template_id')
                ->nullable()
                ->after('brand_color')
                ->constrained('resume_templates')
                ->restrictOnDelete();
        });

        Schema::table('resumes', function (Blueprint $table): void {
            // The style this document was produced with — frozen at upload.
            $table->foreignId('resume_template_id')
                ->nullable()
                ->after('company_id')
                ->constrained('resume_templates')
                ->nullOnDelete();
        });

        $this->linkExistingCompanies();
        $this->linkExistingResumes();

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['resume_template', 'section_order']);

            // Every company has a house style; only the backfill above needed the
            // column to be nullable.
            $table->foreignId('resume_template_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('resume_template', 30)->default('classic')->after('brand_color');
            $table->json('section_order')->nullable()->after('resume_template');
        });

        Schema::table('resumes', function (Blueprint $table): void {
            $table->dropForeign(['resume_template_id']);
            $table->dropColumn('resume_template_id');
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropForeign(['resume_template_id']);
            $table->dropColumn('resume_template_id');
        });

        Schema::dropIfExists('resume_templates');
    }

    /**
     * One template per built-in layout, so every existing company has something
     * to point at.
     */
    private function seedBuiltInTemplates(): void
    {
        $now = now();

        DB::table('resume_templates')->insert(array_map(
            static fn (TemplateLayout $layout): array => [
                'public_id' => (string) Str::ulid(),
                'name' => Str::headline($layout->value),
                'slug' => $layout->value,
                'description' => $layout->label(),
                'layout' => $layout->value,
                'section_order' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            TemplateLayout::cases(),
        ));
    }

    /**
     * Each company keeps the style it had: the matching built-in template, or a
     * template of its own when it had overridden the section order.
     */
    private function linkExistingCompanies(): void
    {
        $templates = DB::table('resume_templates')->pluck('id', 'slug');

        $companies = DB::table('companies')
            ->select(['id', 'name', 'resume_template', 'section_order'])
            ->get();

        foreach ($companies as $company) {
            $layout = is_string($company->resume_template) ? $company->resume_template : 'classic';
            $order = $company->section_order;
            $hasOverride = is_string($order) && $order !== '' && $order !== '[]' && $order !== 'null';

            $templateId = $hasOverride
                ? $this->templateForCompany((string) $company->name, $layout, (string) $order)
                : ($templates[$layout] ?? $templates['classic']);

            DB::table('companies')
                ->where('id', $company->id)
                ->update(['resume_template_id' => $templateId]);
        }
    }

    private function templateForCompany(string $companyName, string $layout, string $sectionOrder): int
    {
        $now = now();
        $name = Str::limit($companyName.' house style', 100, '');

        return (int) DB::table('resume_templates')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'description' => 'Migrated from the company\'s custom section order.',
            'layout' => $layout,
            'section_order' => $sectionOrder,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Existing documents were produced with their company's style, so that is
     * what they freeze.
     */
    private function linkExistingResumes(): void
    {
        DB::table('resumes')->update([
            'resume_template_id' => DB::raw(
                '(select resume_template_id from companies where companies.id = resumes.company_id)'
            ),
        ]);
    }
};
