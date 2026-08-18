<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\ERP\Category;
use App\Models\ERP\Product;
use App\Models\ERP\StockMovement;
use App\Models\ERP\Unit;
use App\Models\ERP\Warehouse;
use App\Models\User;
use App\Services\ERP\StockService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
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

    private function warehouseManager(): User
    {
        return User::where('email', 'warehouse@crm.test')->firstOrFail();
    }

    private function support(): User
    {
        return User::where('email', 'support@crm.test')->firstOrFail();
    }

    private function makeProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'منتج تجريبي',
            'sku' => 'TEST-'.uniqid(),
            'sale_price' => 100,
            'min_stock' => 5,
            'is_active' => true,
        ], $overrides));
    }

    private function makeWarehouse(string $code = 'WH-01'): Warehouse
    {
        return Warehouse::create(['name' => 'مستودع', 'code' => $code, 'is_active' => true]);
    }

    public function test_admin_can_create_product_with_opening_stock(): void
    {
        $warehouse = $this->makeWarehouse();
        $category = Category::create(['name' => 'إلكترونيات', 'slug' => 'electronics']);
        $unit = Unit::create(['name' => 'قطعة', 'code' => 'PCS']);

        $this->actingAs($this->admin())
            ->post('/products', [
                'name' => 'حاسوب',
                'sku' => 'LAP-001',
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'sale_price' => 3000,
                'purchase_cost' => 2500,
                'min_stock' => 5,
                'is_active' => '1',
                'stocks' => [$warehouse->id => ['quantity' => 10]],
            ])
            ->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', ['name' => 'حاسوب', 'sku' => 'LAP-001']);
        $this->assertDatabaseHas('stock_items', [
            'product_id' => Product::where('sku', 'LAP-001')->first()->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'type' => 'opening',
            'direction' => 'in',
            'quantity' => 10,
            'before_qty' => 0,
            'after_qty' => 10,
        ]);
    }

    public function test_product_requires_unique_sku(): void
    {
        $this->makeProduct(['name' => 'أول', 'sku' => 'DUP-1']);

        $this->actingAs($this->admin())
            ->post('/products', ['name' => 'ثانٍ', 'sku' => 'DUP-1', 'sale_price' => 10])
            ->assertSessionHasErrors(['sku']);
    }

    public function test_warehouse_manager_can_view_products_and_stock(): void
    {
        $this->actingAs($this->warehouseManager())
            ->get(route('products.index'))
            ->assertOk();
        $this->actingAs($this->warehouseManager())
            ->get(route('stock.index'))
            ->assertOk();
    }

    public function test_support_cannot_access_products(): void
    {
        $this->actingAs($this->support())
            ->get(route('products.index'))
            ->assertForbidden();
    }

    public function test_stock_service_add_and_out(): void
    {
        $this->actingAs($this->admin());

        $product = $this->makeProduct();
        $warehouse = $this->makeWarehouse();

        $service = app(StockService::class);
        $service->in($product, $warehouse, 20, 'opening');
        $service->out($product, $warehouse, 7, 'sale');

        $this->assertEquals(13.0, (float) $product->stockItems()->where('warehouse_id', $warehouse->id)->value('quantity'));

        $outMovement = StockMovement::where('direction', 'out')->first();
        $this->assertSame(20.0, (float) $outMovement->before_qty);
        $this->assertSame(13.0, (float) $outMovement->after_qty);
    }

    public function test_stock_service_blocks_overdraw(): void
    {
        $this->actingAs($this->admin());

        $product = $this->makeProduct();
        $warehouse = $this->makeWarehouse();

        $service = app(StockService::class);
        $service->in($product, $warehouse, 5, 'opening');

        $this->expectException(InsufficientStockException::class);
        $service->out($product, $warehouse, 10, 'sale');
    }

    public function test_stock_service_adjust_records_before_and_after(): void
    {
        $this->actingAs($this->admin());

        $product = $this->makeProduct();
        $warehouse = $this->makeWarehouse();

        $service = app(StockService::class);
        $service->in($product, $warehouse, 10, 'opening');
        $movement = $service->adjust($product, $warehouse, 4, 'جرد شهر يناير');

        $this->assertEquals(4.0, (float) $product->stockItems()->where('warehouse_id', $warehouse->id)->value('quantity'));
        $this->assertSame('adjustment', $movement->type);
        $this->assertSame('out', $movement->direction);
        $this->assertSame(10.0, (float) $movement->before_qty);
        $this->assertSame(4.0, (float) $movement->after_qty);
    }

    public function test_stock_service_transfer_moves_quantity_between_warehouses(): void
    {
        $this->actingAs($this->admin());

        $product = $this->makeProduct();
        $from = $this->makeWarehouse('WH-A');
        $to = $this->makeWarehouse('WH-B');

        $service = app(StockService::class);
        $service->in($product, $from, 30, 'opening');
        [$out, $in] = $service->transfer($product, $from, $to, 12);

        $this->assertEquals(18.0, (float) $from->stockItems()->where('product_id', $product->id)->value('quantity'));
        $this->assertEquals(12.0, (float) $to->stockItems()->where('product_id', $product->id)->value('quantity'));
        $this->assertSame('transfer', $out->type);
        $this->assertSame('transfer', $in->type);
    }

    public function test_adjust_stock_via_controller(): void
    {
        $this->actingAs($this->admin());

        $product = $this->makeProduct();
        $warehouse = $this->makeWarehouse();

        app(StockService::class)->in($product, $warehouse, 50, 'opening');

        $this->actingAs($this->warehouseManager())
            ->post(route('stock.adjust'), [
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'new_quantity' => 33,
                'note' => 'جرد',
            ])
            ->assertSessionHas('success');

        $this->assertEquals(33.0, (float) $product->stockItems()->where('warehouse_id', $warehouse->id)->value('quantity'));
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'adjustment',
            'quantity' => 17,
            'after_qty' => 33,
        ]);
    }

    public function test_low_stock_filter_returns_only_products_below_minimum(): void
    {
        $this->actingAs($this->admin());

        $warehouse = $this->makeWarehouse();
        $service = app(StockService::class);

        $low = $this->makeProduct(['name' => 'منخفض', 'sku' => 'LOW-1', 'min_stock' => 10]);
        $ok = $this->makeProduct(['name' => 'متوفر', 'sku' => 'OK-1', 'min_stock' => 10]);
        $service->in($low, $warehouse, 3, 'opening');
        $service->in($ok, $warehouse, 25, 'opening');

        $response = $this->get(route('products.index', ['stock_status' => 'low']));
        $response->assertOk()
            ->assertSee('منخفض')
            ->assertDontSee('متوفر');
    }

    public function test_all_inventory_pages_render_for_admin(): void
    {
        $this->actingAs($this->admin());

        $category = Category::create(['name' => 'إلكترونيات', 'slug' => 'elec']);
        $unit = Unit::create(['name' => 'قطعة', 'code' => 'PCS']);
        $warehouse = $this->makeWarehouse();
        $product = $this->makeProduct(['category_id' => $category->id, 'unit_id' => $unit->id]);
        app(StockService::class)->in($product, $warehouse, 10, 'opening');

        $this->get(route('categories.index'))->assertOk();
        $this->get(route('categories.create'))->assertOk();
        $this->get(route('categories.edit', $category))->assertOk();

        $this->get(route('units.index'))->assertOk();
        $this->get(route('units.create'))->assertOk();
        $this->get(route('units.edit', $unit))->assertOk();

        $this->get(route('warehouses.index'))->assertOk();
        $this->get(route('warehouses.create'))->assertOk();
        $this->get(route('warehouses.edit', $warehouse))->assertOk();

        $this->get(route('products.create'))->assertOk();
        $this->get(route('products.edit', $product))->assertOk();

        $this->get(route('stock.movements'))->assertOk();
    }

    public function test_category_cannot_be_deleted_with_products(): void
    {
        $this->actingAs($this->admin());

        $category = Category::create(['name' => 'إلكترونيات', 'slug' => 'elec']);
        $this->makeProduct(['category_id' => $category->id]);

        $this->delete(route('categories.destroy', $category))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_warehouse_cannot_be_deleted_with_stock_balance(): void
    {
        $this->actingAs($this->admin());

        $warehouse = $this->makeWarehouse();
        $product = $this->makeProduct();
        app(StockService::class)->in($product, $warehouse, 5, 'opening');

        $this->delete(route('warehouses.destroy', $warehouse))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id]);
    }
}
