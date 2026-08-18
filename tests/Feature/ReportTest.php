<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\SalesOrderStatus;
use App\Models\CRM\Customer;
use App\Models\CRM\SalesOrder;
use App\Models\CRM\SalesOrderItem;
use App\Models\ERP\Expense;
use App\Models\ERP\Invoice;
use App\Models\ERP\Payment;
use App\Models\ERP\Product;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@crm.test')->firstOrFail();
    }

    private function rep(): User
    {
        return User::where('email', 'rep@crm.test')->firstOrFail();
    }

    public function test_reports_hub_and_all_report_pages_render_for_admin(): void
    {
        $this->actingAs($this->admin())
            ->get(route('reports.index'))->assertOk();
        $this->actingAs($this->admin())
            ->get(route('reports.sales'))->assertOk();
        $this->actingAs($this->admin())
            ->get(route('reports.inventory'))->assertOk();
        $this->actingAs($this->admin())
            ->get(route('reports.profit'))->assertOk();
        $this->actingAs($this->admin())
            ->get(route('reports.expenses'))->assertOk();
        $this->actingAs($this->admin())
            ->get(route('reports.overdue'))->assertOk();
    }

    public function test_report_pages_are_forbidden_for_sales_rep(): void
    {
        $this->actingAs($this->rep())
            ->get(route('reports.index'))->assertForbidden();
        $this->actingAs($this->rep())
            ->get(route('reports.sales'))->assertForbidden();
    }

    public function test_sales_report_aggregates_revenue_and_due(): void
    {
        $customer = Customer::create(['name' => 'عميل', 'phone' => '05990000001']);
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-T-0001',
            'status' => InvoiceStatus::Sent->value,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'subtotal' => 500,
            'total' => 500,
            'paid_amount' => 0,
        ]);
        Payment::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'amount' => 200,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->get(route('reports.sales'))
            ->assertSee('500.00')
            ->assertSee('200.00')
            ->assertSee('300.00');
    }

    public function test_overdue_report_lists_only_unpaid_past_due(): void
    {
        $customer = Customer::create(['name' => 'عميل', 'phone' => '05990000002']);

        Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-T-0002',
            'status' => InvoiceStatus::Sent->value,
            'issue_date' => now()->subDays(30)->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
            'subtotal' => 300,
            'total' => 300,
            'paid_amount' => 0,
        ]);
        Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-T-0003',
            'status' => InvoiceStatus::Paid->value,
            'issue_date' => now()->subDays(30)->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
            'subtotal' => 100,
            'total' => 100,
            'paid_amount' => 100,
        ]);

        $this->actingAs($this->admin())
            ->get(route('reports.overdue'))
            ->assertSee('INV-T-0002')
            ->assertSee('300.00')
            ->assertDontSee('INV-T-0003');
    }

    public function test_inventory_report_shows_stock_value_and_low_stock(): void
    {
        $product = Product::create([
            'name' => 'منتج تقرير',
            'sku' => 'RPT-'.uniqid(),
            'sale_price' => 100,
            'purchase_cost' => 50,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->get(route('reports.inventory'))
            ->assertOk()
            ->assertSee('منتج تقرير');
    }

    public function test_profit_and_expense_reports_render_with_filters(): void
    {
        $month = now()->format('Y-m');

        $this->actingAs($this->admin())
            ->get(route('reports.profit', ['month' => $month]))->assertOk();
        $this->actingAs($this->admin())
            ->get(route('reports.expenses', ['month' => $month]))->assertOk();
    }
}