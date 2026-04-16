<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Services\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CompanyAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding company admins...');

        // ✅ جلب الشركات النشطة والتجريبية
        $companies = Company::whereIn('status', ['active', 'trial'])->get();

        if ($companies->isEmpty()) {
            $this->command->warn('No companies found. Run CompanySeeder first.');
            return;
        }

        $created = 0;
        $updated = 0;

        foreach ($companies as $company) {
            $result = $this->seedAdminForCompany($company);

            if ($result === 'created') {
                $created++;
            } elseif ($result === 'updated') {
                $updated++;
            }
        }

        $this->command->info("✅ Company admins: {$created} created, {$updated} updated.");

        // ✅ إنشاء Super Admin لو مش موجود
        $this->seedSuperAdmin();
    }

    /**
     * Seed admin for a specific company.
     */
    private function seedAdminForCompany(Company $company): string
    {
        // ✅ استخدام Tenant Context
        return Tenant::forCompany($company->id, function () use ($company) {
            $email = $this->generateAdminEmail($company);
            $password = $this->getDefaultPassword();

            $admin = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $company->name . ' Admin',
                    'password' => Hash::make($password),
                    'company_id' => $company->id,
                    'role' => 'admin',
                    'status' => 1,
                    'is_super_admin' => false,
                    'email_verified_at' => now(),
                ]
            );

            $this->command->line("  - {$company->name}: {$email} / {$password}");

            return $admin->wasRecentlyCreated ? 'created' : 'updated';
        });
    }

    /**
     * Seed super admin user.
     */
    private function seedSuperAdmin(): void
    {
        $superAdminEmail = 'superadmin@erp.test';
        $superAdminPassword = '123456';

        $superAdmin = User::query()->updateOrCreate(
            ['email' => $superAdminEmail],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($superAdminPassword),
                'company_id' => null,
                'role' => 'admin',
                'status' => 1,
                'is_super_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command->line("");
        $this->command->info("🔑 Super Admin: {$superAdminEmail} / {$superAdminPassword}");
    }

    /**
     * Generate admin email for a company.
     */
    private function generateAdminEmail(Company $company): string
    {
        // تنظيف اسم الشركة لاستخدامه في الإيميل
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $company->name));
        return "admin@{$slug}.test";
    }

    /**
     * Get default password.
     */
    private function getDefaultPassword(): string
    {
        return config('seeder.default_password', '123456');
    }

    /**
     * Create additional doctors for each company.
     */
    public function withDoctors(int $count = 2): self
    {
        $companies = Company::whereIn('status', ['active', 'trial'])->get();

        foreach ($companies as $company) {
            Tenant::forCompany($company->id, function () use ($company, $count) {
                for ($i = 1; $i <= $count; $i++) {
                    $email = "doctor{$i}@{$this->getCompanySlug($company)}.test";

                    User::query()->updateOrCreate(
                        ['email' => $email],
                        [
                            'name' => "Dr. {$i} - {$company->name}",
                            'password' => Hash::make($this->getDefaultPassword()),
                            'company_id' => $company->id,
                            'role' => 'doctor',
                            'status' => 1,
                            'is_super_admin' => false,
                            'email_verified_at' => now(),
                        ]
                    );
                }
            });
        }

        $this->command->info("✅ {$count} doctors created per company.");

        return $this;
    }

    /**
     * Create additional receptionists for each company.
     */
    public function withReceptionists(int $count = 1): self
    {
        $companies = Company::whereIn('status', ['active', 'trial'])->get();

        foreach ($companies as $company) {
            Tenant::forCompany($company->id, function () use ($company, $count) {
                for ($i = 1; $i <= $count; $i++) {
                    $email = "receptionist{$i}@{$this->getCompanySlug($company)}.test";

                    User::query()->updateOrCreate(
                        ['email' => $email],
                        [
                            'name' => "Receptionist {$i} - {$company->name}",
                            'password' => Hash::make($this->getDefaultPassword()),
                            'company_id' => $company->id,
                            'role' => 'receptionist',
                            'status' => 1,
                            'is_super_admin' => false,
                            'email_verified_at' => now(),
                        ]
                    );
                }
            });
        }

        $this->command->info("✅ {$count} receptionists created per company.");

        return $this;
    }

    /**
     * Get company slug for email.
     */
    private function getCompanySlug(Company $company): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $company->name));
    }
}
