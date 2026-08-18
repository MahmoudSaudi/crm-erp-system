<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderStatus;
use App\Models\ERP\Product;
use App\Models\ERP\PurchaseOrder;
use App\Models\ERP\StockMovement;
use App\Models\ERP\Supplier;
use App\Models\ERP\Warehouse;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseTest extends TestCase
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

    private function purchasing(): User
    {
        return User::where('email', 'purchasing@crm.test')->firstOrFail();
    }

    private function warehouseManager(): User
    {
        return User::where('email', 'warehouse@crm.test')->firstOrFail();
    }

    private function support(): User
    {
        return User::where('email', 'support@crm.test')->firstOrFail();
    }

    private function makeSupplier(): Supplier
    {
        return Supplier::create(['name' => 'مورد مشتريات', 'phone' => '0599'.random_int(100000, 999999), 'is_active' => true]);
    }

    private function makeWarehouse(): Warehouse
    {
        return Warehouse::create(['name' => 'مخزن استلام', 'code' => 'WH-'.uniqid(), 'is_active' => true]);
    }

    private function makeProduct(): Product
    {
        return Product::create([
            'name' => 'منتج مشتريات',
            'sku' => 'PUR-'.uniqid(),
            'sale_price' => 200,
            'purchase_cost' => 150,
            'min_stock' => 2,
            'is_active' => true,
        ]);
    }

    private function makeOrder(string $status = 'draft', bool $withItems = true): PurchaseOrder
    {
        $supplier = $this->makeSupplier();
        $warehouse = $this->makeWarehouse();

        $order = PurchaseOrder::create([
            'order_number' => 'PO-'.str_pad((string) ((int) PurchaseOrder::withTrashed()->max('id') + 1), 6, '0', STR_PAD_LEFT),
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => $status,
            'order_date' => now()->toDateString(),
            'created_by' => $this->admin()->id,
        ]);

        if ($withItems) {
            $order->items()->create([
                'product_id' => $this->makeProduct()->id,
                'quantity' => 10,
                'unit_cost' => 150,
                'total' => 1500,
            ]);
        }

        return $order;
    }

    public function test_purchasing_officer_can_create_po_with_items(): void
    {
        $supplier = $this->makeSupplier();
        $warehouse = $this->makeWarehouse();
        $product = $this->makeProduct();

        $this->actingAs($this->purchasing())
            ->post(route('purchase-orders.store'), [
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'order_date' => now()->toDateString(),
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 5, 'unit_cost' => 150],
                ],
            ])
            ->assertRedirect();

        $order = PurchaseOrder::first();
        $this->assertSame(PurchaseOrderStatus::Draft->value, $order->status);
        $this->assertSame('PO-000001', $order->order_number);
        $this->assertSame('750.00', $order->total);
    }

    public function test_po_requires_supplier_and_warehouse(): void
    {
        $product = $this->makeProduct();

        $this->actingAs($this->purchasing())
            ->post(route('purchase-orders.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 10]],
            ])
            ->assertSessionHasErrors(['supplier_id', 'warehouse_id']);
    }

    public function test_confirm_changes_status_and_does_not_touch_stock(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->purchasing())
            ->post(route('purchase-orders.confirm', $order))
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(PurchaseOrderStatus::Confirmed->value, $order->status);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_partial_receive_adds_stock_and_stays_confirmed(): void
    {
        $order = $this->makeOrder('confirmed');
        $item = $order->items->first();
        $product = $item->product;

        $this->actingAs($this->warehouseManager())
            ->post(route('purchase-orders.receive', $order), [
                'quantities' => [$item->id => 4],
            ])
            ->assertSessionHas('success');

        $order->refresh();
        $item->refresh();

        $this->assertSame('4.00', (string) $item->received_qty);
        $this->assertSame(PurchaseOrderStatus::Confirmed->value, $order->status);

        $this->assertEquals(4.0, (float) $product->stockItems()->where('warehouse_id', $order->warehouse_id)->value('quantity'));
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'purchase_order',
            'direction' => 'in',
            'quantity' => 4,
        ]);
    }

    public function test_full_receive_marks_order_received(): void
    {
        $order = $this->makeOrder('confirmed');
        $item = $order->items->first();

        $this->actingAs($this->warehouseManager())
            ->post(route('purchase-orders.receive', $order), [
                'quantities' => [$item->id => 10],
            ])
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(PurchaseOrderStatus::Received->value, $order->status);
        $this->assertNotNull($order->received_at);
    }

    public function test_receive_rejects_quantity_over_remaining(): void
    {
        $order = $this->makeOrder('confirmed');
        $item = $order->items->first();

        $this->actingAs($this->warehouseManager())
            ->post(route('purchase-orders.receive', $order), [
                'quantities' => [$item->id => 11],
            ])
            ->assertSessionHas('error');

        $item->refresh();
        $this->assertSame('0.00', (string) $item->received_qty);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_receive_rejects_zero_or_negative(): void
    {
        $order = $this->makeOrder('confirmed');
        $item = $order->items->first();

        $this->actingAs($this->warehouseManager())
            ->post(route('purchase-orders.receive', $order), [
                'quantities' => [$item->id => 0],
            ])
            ->assertSessionHas('success'); // 0 = skip، أي لا حركة (مطابقة التسامح للنموذج)

        $this->assertSame(0, StockMovement::count());
        $item->refresh();
        $this->assertSame('0.00', (string) $item->received_qty);
    }

    public function test_receive_rejected_unless_confirmed(): void
    {
        $draft = $this->makeOrder('draft');
        $item = $draft->items->first();

        $this->actingAs($this->warehouseManager())
            ->post(route('purchase-orders.receive', $draft), [
                'quantities' => [$item->id => 5],
            ])
            ->assertSessionHas('error');

        $received = $this->makeOrder('received');
        $ritem = $received->items->first();

        $this->actingAs($this->warehouseManager())
            ->post(route('purchase-orders.receive', $received), [
                'quantities' => [$ritem->id => 1],
            ])
            ->assertSessionHas('error');
    }

    public function test_cancel_allowed_from_draft_and_confirmed_without_receive(): void
    {
        $draft = $this->makeOrder('draft');
        $this->actingAs($this->purchasing())
            ->post(route('purchase-orders.cancel', $draft))
            ->assertSessionHas('success');

        $draft->refresh();
        $this->assertSame(PurchaseOrderStatus::Cancelled->value, $draft->status);

        $confirmed = $this->makeOrder('confirmed');
        $confirmedItem = $confirmed->items->first();

        $this->actingAs($this->purchasing())
            ->post(route('purchase-orders.receive', $confirmed), ['quantities' => [$confirmedItem->id => 5]])
            ->assertSessionHas('success');

        $this->actingAs($this->purchasing())
            ->post(route('purchase-orders.cancel', $confirmed))
            ->assertSessionHas('error');

        $confirmed->refresh();
        $this->assertSame(PurchaseOrderStatus::Confirmed->value, $confirmed->status);
    }

    public function test_destroy_only_works_on_draft(): void
    {
        $draft = $this->makeOrder('draft');
        $this->actingAs($this->purchasing())
            ->delete(route('purchase-orders.destroy', $draft))
            ->assertRedirect(route('purchase-orders.index'));

        $this->assertSoftDeleted('purchase_orders', ['id' => $draft->id]);

        $confirmed = $this->makeOrder('confirmed');
        $this->actingAs($this->purchasing())
            ->delete(route('purchase-orders.destroy', $confirmed))
            ->assertSessionHas('error');
    }

    public function test_support_cannot_access_purchase_orders(): void
    {
        $this->actingAs($this->support())
            ->get(route('purchase-orders.index'))
            ->assertForbidden();
    }

    public function test_warehouse_manager_can_receive_but_not_create(): void
    {
        $this->actingAs($this->warehouseManager())
            ->get(route('purchase-orders.index'))->assertOk();

        $this->actingAs($this->warehouseManager())
            ->get(route('purchase-orders.create'))->assertForbidden();

        $this->actingAs($this->warehouseManager())
            ->post(route('purchase-orders.store'), [
                'supplier_id' => $this->makeSupplier()->id,
                'warehouse_id' => $this->makeWarehouse()->id,
                'items' => [['product_id' => $this->makeProduct()->id, 'quantity' => 1, 'unit_cost' => 1]],
            ])
            ->assertForbidden();
    }
}