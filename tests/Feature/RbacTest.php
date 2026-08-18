<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    public function test_admin_can_access_dashboard(): void
    {
        $this->actingAs($this->user('admin@crm.test'))
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_sales_rep_can_access_leads_and_products_but_not_reports(): void
    {
        $rep = $this->user('rep@crm.test');

        $this->actingAs($rep)->get('/leads')->assertOk();

        // المندوب يحتاج كتالوج المنتجات عند إنشاء طلبات البيع
        $this->actingAs($rep)->get('/products')->assertOk();

        // لكن لا يملك صلاحية التقارير
        $this->actingAs($rep)->get('/reports')->assertForbidden();
    }

    public function test_support_agent_cannot_access_sales_orders(): void
    {
        $this->actingAs($this->user('support@crm.test'))
            ->get('/sales-orders')
            ->assertForbidden();
    }

    public function test_accountant_cannot_access_warehouse_module(): void
    {
        $this->actingAs($this->user('accountant@crm.test'))
            ->get('/stock')
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/leads')->assertRedirect('/login');
    }
}
