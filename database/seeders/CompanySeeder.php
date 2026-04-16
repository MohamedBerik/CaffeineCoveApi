<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Services\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding companies...');

        // ✅ إنشاء شركات افتراضية
        $defaultCompanies = $this->getDefaultCompanies();

        $created = 0;
        $updated = 0;

        foreach ($defaultCompanies as $companyData) {
            $result = $this->seedCompany($companyData);

            if ($result === 'created') {
                $created++;
            } elseif ($result === 'updated') {
                $updated++;
            }
        }

        $this->command->info("✅ Companies: {$created} created, {$updated} updated.");

        // ✅ إنشاء شركات إضافية عشوائية لو مطلوب
        if ($this->command->option('count')) {
            $count = (int) $this->command->option('count');
            $this->createRandomCompanies($count);
        }
    }

    /**
     * Seed a single company.
     */
    private function seedCompany(array $data): string
    {
        // ✅ استخدام Tenant Context
        return Tenant::asSuperAdmin(function () use ($data) {
            $company = Company::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'status' => $data['status'],
                    'trial_ends_at' => $data['trial_ends_at'] ?? null,
                    'branding' => $data['branding'] ?? null,
                ]
            );

            $this->command->line("  - {$company->name} ({$company->status})");

            return $company->wasRecentlyCreated ? 'created' : 'updated';
        });
    }

    /**
     * Create random companies using factory.
     */
    private function createRandomCompanies(int $count): void
    {
        $this->command->info("Creating {$count} random companies...");

        Company::factory()->count($count)->create();

        $this->command->info("✅ {$count} random companies created.");
    }

    /**
     * Get default companies configuration.
     */
    private function getDefaultCompanies(): array
    {
        return [
            [
                'name' => 'Bright Smile Dental',
                'slug' => 'bright-smile-dental',
                'status' => Company::STATUS_ACTIVE,
                'trial_ends_at' => null,
                'branding' => [
                    'app_name' => 'Bright Smile Dental',
                    'primary_color' => '#1a237e',
                    'secondary_color' => '#64b5f6',
                ],
            ],
            [
                'name' => 'Perfect Teeth Clinic',
                'slug' => 'perfect-teeth-clinic',
                'status' => Company::STATUS_ACTIVE,
                'trial_ends_at' => null,
                'branding' => [
                    'app_name' => 'Perfect Teeth Clinic',
                    'primary_color' => '#0ea5e9',
                    'secondary_color' => '#38bdf8',
                ],
            ],
            [
                'name' => 'Dental Care Center',
                'slug' => 'dental-care-center',
                'status' => Company::STATUS_TRIAL,
                'trial_ends_at' => now()->addDays(14),
                'branding' => [
                    'app_name' => 'Dental Care Center',
                    'primary_color' => '#059669',
                    'secondary_color' => '#34d399',
                ],
            ],
            [
                'name' => 'Smile Studio',
                'slug' => 'smile-studio',
                'status' => Company::STATUS_TRIAL,
                'trial_ends_at' => now()->addDays(7),
                'branding' => [
                    'app_name' => 'Smile Studio',
                    'primary_color' => '#7c3aed',
                    'secondary_color' => '#a78bfa',
                ],
            ],
        ];
    }

    /**
     * Create a custom company.
     */
    public function createCustom(string $name, string $status = 'active', ?int $trialDays = null): Company
    {
        $slug = Str::slug($name);

        // ضمان uniqueness
        $originalSlug = $slug;
        $counter = 1;
        while (Company::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $trialEndsAt = null;
        if ($status === Company::STATUS_TRIAL) {
            $trialEndsAt = now()->addDays($trialDays ?? 14);
        }

        return Tenant::asSuperAdmin(function () use ($name, $slug, $status, $trialEndsAt) {
            return Company::create([
                'name' => $name,
                'slug' => $slug,
                'status' => $status,
                'trial_ends_at' => $trialEndsAt,
                'branding' => [
                    'app_name' => $name,
                    'primary_color' => '#1a237e',
                    'secondary_color' => '#64b5f6',
                ],
            ]);
        });
    }
}
