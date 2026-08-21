<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-company letterhead settings for the ATS preview: where the logo sits and
 * how big it prints. Defaults keep existing companies rendering as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('logo_placement', 20)->default('right')->after('logo_path');
            $table->string('logo_size', 20)->default('medium')->after('logo_placement');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['logo_placement', 'logo_size']);
        });
    }
};
