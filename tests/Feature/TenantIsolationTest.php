<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Models\Customer;
use App\Services\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected $companyA;
    protected $companyB;
    protected $adminA;
    protected $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        // إنشاء شركتين
        $this->companyA = Company::create([
            'name' => 'Clinic A',
            'slug' => 'clinic-a',
            'status' => 'active',
        ]);

        $this->companyB = Company::create([
            'name' => 'Clinic B',
            'slug' => 'clinic-b',
            'status' => 'active',
        ]);

        // إنشاء Admin لكل شركة
        $this->adminA = User::create([
            'company_id' => $this->companyA->id,
            'name' => 'Admin A',
            'email' => 'adminA@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->adminB = User::create([
            'company_id' => $this->companyB->id,
            'name' => 'Admin B',
            'email' => 'adminB@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
    }

    /** @test */
    public function user_can_only_see_their_company_data()
    {
        // إنشاء مريض في كل شركة
        Tenant::setId($this->companyA->id);
        Customer::create([
            'company_id' => $this->companyA->id,
            'name' => 'Patient A',
        ]);

        Tenant::setId($this->companyB->id);
        Customer::create([
            'company_id' => $this->companyB->id,
            'name' => 'Patient B',
        ]);

        // Admin A يشوف مرضى شركته فقط
        Tenant::setId($this->companyA->id);
        $response = $this->actingAs($this->adminA, 'sanctum')
            ->getJson('/api/erp/customers');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');

        $data = $response->json('data');
        // ✅ أول عنصر في الـ Collection (Resource)
        $this->assertEquals('Patient A', $data[0]['name'] ?? $data['data'][0]['name'] ?? '');
    }

    /** @test */
    public function user_cannot_access_other_company_data()
    {
        // إنشاء مريض في شركة B
        Tenant::setId($this->companyB->id);
        $customer = Customer::create([
            'company_id' => $this->companyB->id,
            'name' => 'Patient B',
        ]);

        // Admin A يحاول يوصل لمريض في شركة B
        Tenant::setId($this->companyA->id);
        $response = $this->actingAs($this->adminA, 'sanctum')
            ->getJson("/api/erp/customers/{$customer->id}");

        $response->assertStatus(404); // مش هيلاقيه
    }
}
