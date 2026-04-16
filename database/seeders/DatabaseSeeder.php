<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting database seeding...');
        $this->command->info('');

        // ============================================
        // 1. Core Seeders (Production & Development)
        // ============================================
        $this->seedCore();

        // ============================================
        // 2. Development Seeders (Development Only)
        // ============================================
        if ($this->shouldSeedDevelopmentData()) {
            $this->seedDevelopment();
        }

        $this->command->info('');
        $this->command->info('✅ Database seeding completed!');
        $this->displayLoginCredentials();
    }

    /**
     * Seed core data (required for both production and development).
     */
    protected function seedCore(): void
    {
        $this->command->info('📦 Seeding core data...');

        $this->call([
            CompanySeeder::class,           // 1. الشركات الأساسية
            CompanyAccountsSeeder::class,   // 2. الحسابات المحاسبية لكل شركة
            CompanyAdminSeeder::class,      // 3. الأدمنز (بما فيهم Super Admin)
        ]);

        $this->command->info('');
    }

    /**
     * Seed development data (only for local/development environment).
     */
    protected function seedDevelopment(): void
    {
        $this->command->info('🎲 Seeding development data...');

        $this->call([
            DevelopmentDataSeeder::class,   // بيانات تجريبية (مواعيد، فواتير، مرضى)
        ]);

        $this->command->info('');
    }

    /**
     * Determine if development data should be seeded.
     */
    protected function shouldSeedDevelopmentData(): bool
    {
        // في بيئة الإنتاج - لا نضيف بيانات تجريبية
        if (App::environment('production')) {
            return false;
        }

        // لو المستخدم أكد أو مرر flag
        if ($this->command->option('with-dev-data')) {
            return true;
        }

        // اسأل المستخدم
        return $this->command->confirm('Do you want to seed development data (appointments, invoices, etc.)?', false);
    }

    /**
     * Display login credentials after seeding.
     */
    protected function displayLoginCredentials(): void
    {
        $this->command->info('🔑 Login Credentials:');
        $this->command->line('   Super Admin: superadmin@erp.test / 123456');
        $this->command->line('');
        $this->command->line('   Company Admins:');
        $this->command->line('   - Bright Smile Dental: admin@brightsmiledental.test / 123456');
        $this->command->line('   - Perfect Teeth Clinic: admin@perfectteethclinic.test / 123456');
        $this->command->line('   - Dental Care Center: admin@dentalcarecenter.test / 123456');
        $this->command->line('   - Smile Studio: admin@smilestudio.test / 123456');
    }
}
