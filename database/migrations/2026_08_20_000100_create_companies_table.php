<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();          // SCHEMA §A2: never expose auto-increment ids
            $table->string('name', 150);
            $table->string('slug', 170)->unique();
            $table->string('industry', 100)->nullable();
            $table->string('contact_email', 255)->nullable();   // PII
            $table->string('contact_phone', 20)->nullable();    // PII, E.164
            $table->string('website', 255)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->string('brand_color', 7)->default('#1F2937');
            $table->string('resume_template', 30)->default('classic');
            $table->json('section_order')->nullable();     // list<string> overriding the template default
            $table->text('formatting_notes')->nullable();  // house-style hints shown on the ATS preview
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'name']);          // hot list page: active companies, name-sorted
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
