<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Sign-in is by USERNAME (config/fortify.php). Demo credentials:
     *   admin / password   ·  recruiter / password
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Amina Faris',
                'email' => 'admin@crm.test',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['username' => 'recruiter'],
            [
                'name' => 'Omar Khalil',
                'email' => 'recruiter@crm.test',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        // Templates first: companies reference one.
        $this->call(ResumeTemplateSeeder::class);
        $this->call(CompanySeeder::class);
    }
}
