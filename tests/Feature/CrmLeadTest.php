<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\CRM\Customer;
use App\Models\CRM\Lead;
use App\Models\CRM\Opportunity;
use App\Models\CRM\OpportunityStage;
use App\Models\User;
use Database\Seeders\OpportunityStagesSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmLeadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        $this->seed(OpportunityStagesSeeder::class);
    }

    private function admin()
    {
        return User::where('email', 'admin@crm.test')->firstOrFail();
    }

    private function rep()
    {
        return User::where('email', 'rep@crm.test')->firstOrFail();
    }

    private function support()
    {
        return User::where('email', 'support@crm.test')->firstOrFail();
    }

    public function test_admin_can_create_lead(): void
    {
        $this->actingAs($this->admin())
            ->post('/leads', [
                'first_name' => 'علي',
                'last_name' => 'أحمد',
                'phone' => '0599000001',
                'email' => 'ali@example.com',
                'status' => 'new',
            ])
            ->assertRedirect(route('leads.index'));

        $this->assertDatabaseHas('leads', ['email' => 'ali@example.com', 'status' => 'new']);
    }

    public function test_lead_requires_first_name(): void
    {
        $this->actingAs($this->admin())
            ->post('/leads', ['status' => 'new'])
            ->assertSessionHasErrors(['first_name']);
    }

    public function test_sales_rep_can_create_lead_but_support_cannot(): void
    {
        $this->actingAs($this->rep())
            ->post('/leads', ['first_name' => 'سارة', 'status' => 'new'])
            ->assertRedirect(route('leads.index'));

        $this->actingAs($this->support())
            ->post('/leads', ['first_name' => 'محظور', 'status' => 'new'])
            ->assertForbidden();
    }

    public function test_converting_lead_creates_customer_and_opportunity(): void
    {
        $this->actingAs($this->admin());

        $lead = Lead::create([
            'first_name' => 'أحمد',
            'last_name' => 'خالد',
            'phone' => '0599123456',
            'email' => 'ahmed@example.com',
            'status' => 'qualified',
            'value' => 5000,
            'assigned_to' => $this->rep()->id,
            'created_by' => $this->admin()->id,
        ]);

        $this->post(route('leads.convert', $lead))
            ->assertRedirect();

        $this->assertDatabaseHas('customers', ['email' => 'ahmed@example.com']);
        $this->assertDatabaseHas('opportunities', [
            'customer_id' => Customer::where('email', 'ahmed@example.com')->first()->id,
            'lead_id' => $lead->id,
            'amount' => 5000,
        ]);

        $lead->refresh();
        $this->assertSame(LeadStatus::Converted->value, $lead->status);
        $this->assertNotNull($lead->converted_at);

        $this->assertDatabaseHas('activities', [
            'related_type' => Opportunity::class,
        ]);
    }

    public function test_lead_cannot_be_converted_twice(): void
    {
        $this->actingAs($this->admin());

        $lead = Lead::create([
            'first_name' => 'محمد',
            'last_name' => 'سمير',
            'status' => 'new',
            'created_by' => $this->admin()->id,
        ]);

        $this->post(route('leads.convert', $lead))->assertRedirect();
        $this->post(route('leads.convert', $lead))
            ->assertSessionHas('error');
    }

    public function test_sales_rep_can_view_lead_pipeline(): void
    {
        $this->actingAs($this->rep())
            ->get(route('leads.pipeline'))
            ->assertOk();
    }

    public function test_only_users_with_customers_permission_can_see_customer(): void
    {
        $customer = Customer::create([
            'name' => 'شركة المستقبل',
            'type' => 'company',
            'status' => 'active',
            'created_by' => $this->admin()->id,
        ]);

        $this->actingAs($this->rep())->get(route('customers.show', $customer))->assertOk();
        $this->actingAs($this->support())->get(route('customers.show', $customer))->assertOk();
    }

    public function test_sales_rep_can_create_opportunity(): void
    {
        $this->actingAs($this->admin());

        $customer = Customer::create([
            'name' => 'عميل تجريبي',
            'type' => 'individual',
            'status' => 'active',
            'created_by' => $this->admin()->id,
        ]);

        $this->actingAs($this->rep())
            ->post('/opportunities', [
                'title' => 'فرصة كبيرة',
                'customer_id' => $customer->id,
                'stage_id' => OpportunityStage::where('slug', 'new')->first()->id,
                'amount' => 10000,
                'probability' => 30,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('opportunities', [
            'title' => 'فرصة كبيرة',
            'amount' => 10000,
        ]);
    }

    public function test_support_agent_cannot_access_sales_pipeline(): void
    {
        $this->actingAs($this->support())
            ->get(route('opportunities.pipeline'))
            ->assertForbidden();
    }
}
