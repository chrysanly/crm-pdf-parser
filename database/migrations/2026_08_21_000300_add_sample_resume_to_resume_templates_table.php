<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A template can be built from a sample resume: the PDF is parsed on the queue
 * and the order its sections were printed in becomes the template's section
 * order (SCHEMA §B4, PRD BR-6c).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resume_templates', function (Blueprint $table): void {
            // Private disk, random filename — same rules as a candidate resume.
            $table->string('sample_path', 255)->nullable()->after('section_order');
            $table->string('sample_filename', 255)->nullable()->after('sample_path');
            $table->string('sample_status', 30)->nullable()->after('sample_filename');
            $table->text('sample_failure_reason')->nullable()->after('sample_status');
        });
    }

    public function down(): void
    {
        Schema::table('resume_templates', function (Blueprint $table): void {
            $table->dropColumn([
                'sample_path',
                'sample_filename',
                'sample_status',
                'sample_failure_reason',
            ]);
        });
    }
};
