<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\SalesOrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\CRM\Customer;
use App\Models\CRM\SalesOrder;
use App\Models\CRM\SalesOrderItem;
use App\Models\ERP\Expense;
use App\Models\ERP\Invoice;
use App\Models\ERP\Payment;
use App\Models\ERP\Product;
use App\Models\ERP\Warehouse;
use App\Models\User;
use App\Services\CRM\SalesOrderService;
use App\Services\ERP\InvoiceService;
use App\Services\ERP\StockService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesTest extends TestCase
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

    private function salesManager(): User
    {
        return User::where('email', 'manager@crm.test')->firstOrFail();
    }

    private function salesRep(): User
    {
        return User::where('email', 'rep@crm.test')->firstOrFail();
    }

    private function accountant(): User
    {
        return User::where('email', 'accountant@crm.test')->firstOrFail();
    }

    private function makeCustomer(): Customer
    {
        return Customer::create([
            'name' => 'عميل تجريبي',
            'email' => 'customer-'.uniqid().'@test.com',
            'phone' => '0599'.random_int(100000, 999999),
        ]);
    }

    private function makeProduct(float $price = 100, int $qty = 20): Product
    {
        $product = Product::create([
            'name' => 'منتج مبيعات',
            'sku' => 'SLS-'.uniqid(),
            'sale_price' => $price,
            'min_stock' => 2,
            'is_active' => true,
        ]);

        $warehouse = Warehouse::create(['name' => 'مخزن رئيسي', 'code' => 'WH-'.uniqid(), 'is_active' => true]);
        $this->actingAs($this->admin());
        app(StockService::class)->in($product, $warehouse, $qty, 'opening');
        auth()->logout();

        return $product;
    }

    private function makeOrder(array $overrides = []): SalesOrder
    {
        $this->actingAs($this->salesRep());
        $order = SalesOrder::create(array_merge([
            'order_number' => 'SO-'.str_pad((string) (SalesOrder::max('id') + 1), 6, '0', STR_PAD_LEFT),
            'customer_id' => $this->makeCustomer()->id,
            'status' => SalesOrderStatus::Draft->value,
            'order_date' => now()->toDateString(),
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'created_by' => $this->salesRep()->id,
        ], $overrides));
        auth()->logout();

        return $order;
    }

    public function test_sales_rep_can_create_sales_order_with_items(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        $response = $this->actingAs($this->salesRep())
            ->post(route('sales-orders.store'), [
                'customer_id' => $customer->id,
                'order_date' => now()->toDateString(),
                'discount_amount' => 10,
                'shipping_amount' => 5,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 100],
                ],
            ]);

        $response->assertRedirect();

        $order = SalesOrder::first();
        $this->assertSame(SalesOrderStatus::Draft->value, $order->status);
        $this->assertSame('SO-000001', $order->order_number);
        $this->assertSame('295.00', $order->total);
        $this->assertDatabaseHas('sales_order_items', [
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);
    }

    public function test_order_number_is_sequential(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        foreach (range(1, 2) as $i) {
            $this->actingAs($this->salesRep())
                ->post(route('sales-orders.store'), [
                    'customer_id' => $customer->id,
                    'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 50]],
                ]);
        }

        $first = SalesOrder::orderBy('id')->first();
        $last = SalesOrder::orderByDesc('id')->first();
        $this->assertSame('SO-000001', $first->order_number);
        $this->assertSame('SO-'.str_pad((string) ($first->id + 1), 6, '0', STR_PAD_LEFT), $last->order_number);
    }

    public function test_confirm_deducts_stock_and_marks_confirmed(): void
    {
        $product = $this->makeProduct(100, 20);
        $order = $this->makeOrder();
        SalesOrderItem::create([
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 5,
            'unit_price' => 100,
            'total' => 500,
        ]);
        app(SalesOrderService::class)->recalculateTotals($order);

        $this->actingAs($this->salesManager())
            ->post(route('sales-orders.confirm', $order))
            ->assertSessionHas('success');

        $this->assertSame(SalesOrderStatus::Confirmed->value, $order->fresh()->status);
        $this->assertEquals(15.0, (float) $product->stockItems()->sum('quantity'));
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'sales_order',
            'direction' => 'out',
            'quantity' => 5,
        ]);
    }

    public function test_confirm_fails_when_stock_insufficient(): void
    {
        $product = $this->makeProduct(100, 3);
        $order = $this->makeOrder();
        SalesOrderItem::create([
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 10,
            'unit_price' => 100,
            'total' => 1000,
        ]);
        app(SalesOrderService::class)->recalculateTotals($order);

        $this->actingAs($this->salesManager())
            ->post(route('sales-orders.confirm', $order))
            ->assertSessionHas('error');

        $this->assertSame(SalesOrderStatus::Draft->value, $order->fresh()->status);
        $this->assertEquals(3.0, (float) $product->stockItems()->sum('quantity'));
    }

    public function test_cancel_restores_stock(): void
    {
        $product = $this->makeProduct(100, 20);
        $order = $this->makeOrder();
        SalesOrderItem::create([
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 5,
            'unit_price' => 100,
            'total' => 500,
        ]);
        app(SalesOrderService::class)->recalculateTotals($order);

        $service = app(SalesOrderService::class);
        $service->confirm($order);
        $this->assertEquals(15.0, (float) $product->stockItems()->sum('quantity'));

        $this->actingAs($this->salesManager())
            ->post(route('sales-orders.cancel', $order))
            ->assertSessionHas('success');

        $this->assertSame(SalesOrderStatus::Cancelled->value, $order->fresh()->status);
        $this->assertEquals(20.0, (float) $product->stockItems()->sum('quantity'));
    }

    public function test_fulfill_marks_order_fulfilled(): void
    {
        $product = $this->makeProduct(100, 20);
        $order = $this->makeOrder();
        SalesOrderItem::create([
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 100,
            'total' => 200,
        ]);
        app(SalesOrderService::class)->recalculateTotals($order);
        app(SalesOrderService::class)->confirm($order);

        $this->actingAs($this->salesManager())
            ->post(route('sales-orders.fulfill', $order))
            ->assertSessionHas('success');

        $this->assertSame(SalesOrderStatus::Fulfilled->value, $order->fresh()->status);
    }

    public function test_confirm_order_cannot_be_edited_or_deleted(): void
    {
        $product = $this->makeProduct(100, 20);
        $order = $this->makeOrder();
        SalesOrderItem::create([
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 100,
            'total' => 200,
        ]);
        app(SalesOrderService::class)->recalculateTotals($order);
        app(SalesOrderService::class)->confirm($order);

        $this->actingAs($this->salesRep())
            ->put(route('sales-orders.update', $order), ['customer_id' => $order->customer_id])
            ->assertSessionHas('error');

        $this->actingAs($this->salesManager())
            ->delete(route('sales-orders.destroy', $order))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('sales_orders', ['id' => $order->id]);
    }

    public function test_support_cannot_access_sales_orders(): void
    {
        $this->actingAs($this->support())
            ->get(route('sales-orders.index'))
            ->assertForbidden();
    }

    private function support(): User
    {
        return User::where('email', 'support@crm.test')->firstOrFail();
    }

    public function test_invoice_created_from_confirmed_order(): void
    {
        $product = $this->makeProduct(100, 20);
        $order = $this->makeOrder();
        SalesOrderItem::create([
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 100,
            'total' => 200,
        ]);
        app(SalesOrderService::class)->recalculateTotals($order);
        app(SalesOrderService::class)->confirm($order);

        $this->actingAs($this->accountant())
            ->post(route('invoices.store'), ['order_id' => $order->id])
            ->assertRedirect();

        $invoice = Invoice::first();
        $this->assertNotNull($invoice);
        $this->assertSame('INV-000001', $invoice->invoice_number);
        $this->assertSame('200.00', $invoice->total);
        $this->assertSame(InvoiceStatus::Draft->value, $invoice->status);
        $this->assertSame($order->id, $invoice->sales_order_id);
        $this->assertDatabaseHas('invoice_items', ['invoice_id' => $invoice->id]);
    }

    public function test_invoice_cannot_be_created_for_draft_or_uninvoiced_twice(): void
    {
        $product = $this->makeProduct(100, 20);
        $draftOrder = $this->makeOrder();
        SalesOrderItem::create([
            'sales_order_id' => $draftOrder->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 100,
            'total' => 100,
        ]);
        app(SalesOrderService::class)->recalculateTotals($draftOrder);

        $this->actingAs($this->accountant())
            ->post(route('invoices.store'), ['order_id' => $draftOrder->id])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_payment_updates_invoice_status_and_due(): void
    {
        $product = $this->makeProduct(100, 20);
        $order = $this->makeOrder();
        SalesOrderItem::create([
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 100,
            'total' => 200,
        ]);
        app(SalesOrderService::class)->recalculateTotals($order);
        app(SalesOrderService::class)->confirm($order);

        $invoice = app(InvoiceService::class)->createFromOrder($order);

        $this->actingAs($this->accountant())
            ->post(route('payments.store'), [
                'invoice_id' => $invoice->id,
                'amount' => 120,
                'method' => PaymentMethod::Cash->value,
                'paid_at' => now()->toDateString(),
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame('120.00', $invoice->paid_amount);
        $this->assertSame(80.0, $invoice->dueAmount());
        $this->assertSame(InvoiceStatus::Partial->value, $invoice->status);
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 120,
        ]);
    }

    public function test_full_payment_marks_invoice_paid(): void
    {
        $product = $this->makeProduct(100, 20);
        $order = $this->makeOrder();
        SalesOrderItem::create([
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 200,
            'total' => 200,
        ]);
        app(SalesOrderService::class)->recalculateTotals($order);
        app(SalesOrderService::class)->confirm($order);

        $invoice = app(InvoiceService::class)->createFromOrder($order);

        $this->actingAs($this->accountant())
            ->post(route('payments.store'), [
                'invoice_id' => $invoice->id,
                'amount' => 200,
                'method' => PaymentMethod::BankTransfer->value,
            ]);

        $invoice->refresh();
        $this->assertSame('200.00', $invoice->paid_amount);
        $this->assertSame(0.0, $invoice->dueAmount());
        $this->assertSame(InvoiceStatus::Paid->value, $invoice->status);
    }

    public function test_payment_cannot_exceed_due_amount(): void
    {
        $product = $this->makeProduct(100, 20);
        $order = $this->makeOrder();
        SalesOrderItem::create([
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 100,
            'total' => 100,
        ]);
        app(SalesOrderService::class)->recalculateTotals($order);
        app(SalesOrderService::class)->confirm($order);

        $invoice = app(InvoiceService::class)->createFromOrder($order);

        $this->actingAs($this->accountant())
            ->post(route('payments.store'), [
                'invoice_id' => $invoice->id,
                'amount' => 150,
                'method' => PaymentMethod::Cash->value,
            ])
            ->assertSessionHas('error');

        $invoice->refresh();
        $this->assertSame('0.00', $invoice->paid_amount);
    }

    public function test_expense_crud_via_controller(): void
    {
        $this->actingAs($this->accountant())
            ->post(route('expenses.store'), [
                'category' => 'إيجار',
                'amount' => 1500,
                'description' => 'إيجار مكتب',
                'date' => now()->toDateString(),
                'is_reimbursable' => '0',
            ])
            ->assertRedirect(route('expenses.index'));

        $expense = Expense::first();
        $this->assertSame('1500.00', $expense->amount);
        $this->assertSame('إيجار', $expense->category);

        $this->put(route('expenses.update', $expense), [
            'category' => 'صيانة',
            'amount' => 200,
            'date' => now()->toDateString(),
        ])->assertRedirect(route('expenses.index'));

        $this->assertSame('200.00', $expense->fresh()->amount);
        $this->assertSame('صيانة', $expense->fresh()->category);

        $this->delete(route('expenses.destroy', $expense))
            ->assertRedirect(route('expenses.index'));

        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
    }

    public function test_all_sales_pages_render_for_admin(): void
    {
        $this->actingAs($this->admin());

        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        $order = $this->makeOrder(['customer_id' => $customer->id]);
        SalesOrderItem::create([
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 100,
            'total' => 100,
        ]);
        app(SalesOrderService::class)->recalculateTotals($order);
        app(SalesOrderService::class)->confirm($order);

        $invoice = app(InvoiceService::class)->createFromOrder($order);

        $this->actingAs($this->admin());

        $this->get(route('sales-orders.index'))->assertOk();
        $this->get(route('sales-orders.create'))->assertOk();
        $this->get(route('sales-orders.show', $order))->assertOk();
        $this->get(route('invoices.index'))->assertOk();
        $this->get(route('invoices.create'))->assertOk();
        $this->get(route('invoices.show', $invoice))->assertOk();
        $this->get(route('invoices.print', $invoice))->assertOk();
        $this->get(route('payments.index'))->assertOk();
        $this->get(route('payments.create'))->assertOk();
        $this->get(route('expenses.index'))->assertOk();
        $this->get(route('expenses.create'))->assertOk();
    }
}
