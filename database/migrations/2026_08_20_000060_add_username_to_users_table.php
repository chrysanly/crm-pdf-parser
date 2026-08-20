<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Sign-in identifier is the username, not the email (config/fortify.php
 * `username`). UNIQUE is the source of truth, validation is only UX (SCHEMA §A4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 50)->nullable()->after('name');
        });

        // Backfill existing rows from the email local-part before adding the index.
        foreach (DB::table('users')->select('id', 'email')->get() as $user) {
            DB::table('users')->where('id', $user->id)->update([
                'username' => Str::slug(Str::before((string) $user->email, '@'), '_').'_'.$user->id,
            ]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 50)->nullable(false)->change();
            $table->unique('username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
