<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resumes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();

            $table->string('original_filename', 255);
            $table->string('stored_path', 255);            // private disk; never public
            $table->char('file_hash', 64);                 // sha256 of the upload — idempotency key
            $table->unsignedInteger('file_size');
            $table->unsignedSmallInteger('page_count')->nullable();

            $table->string('status', 30)->default('pending');
            $table->string('candidate_name', 150)->nullable();   // PII, denormalised for list pages
            $table->string('candidate_email', 255)->nullable();  // PII
            $table->json('parsed_data')->nullable();             // ParsedResume shape — see SCHEMA.md Part B
            $table->text('failure_reason')->nullable();
            $table->timestamp('parsed_at')->nullable();
            $table->timestamps();

            // RULES §5.5 / §A4: the same file cannot be ingested twice for one company.
            $table->unique(['company_id', 'file_hash'], 'resumes_company_id_file_hash_unique');
            $table->index(['company_id', 'status', 'created_at'], 'idx_resumes_company_status_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resumes');
    }
};
