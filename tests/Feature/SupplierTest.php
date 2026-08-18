<?php

namespace Tests\Feature;

use App\Models\ERP\PurchaseOrder;
use App\Models\ERP\Supplier;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
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

    private function makeSupplier(array $overrides = []): Supplier
    {
        return Supplier::create(array_merge([
            'name' => 'مورد تجريبي',
            'phone' => '0599'.random_int(100000, 999999),
            'is_active' => true,
        ], $overrides));
    }

    public function test_admin_can_create_supplier(): void
    {
        $this->actingAs($this->admin())
            ->post(route('suppliers.store'), [
                'name' => 'شركة النور للمستلزمات',
                'contact_name' => 'أحمد',
                'phone' => '0599000001',
                'email' => 'ahmed@noor.test',
                'tax_number' => 'TAX-123',
                'is_active' => '1',
            ])
            ->assertRedirect(route('suppliers.index'));

        $this->assertDatabaseHas('suppliers', ['name' => 'شركة النور للمستلزمات']);
    }

    public function test_supplier_requires_name(): void
    {
        $this->actingAs($this->admin())
            ->post(route('suppliers.store'), ['name' => ''])
            ->assertSessionHasErrors(['name']);
    }

    public function test_purchasing_officer_has_full_access(): void
    {
        $supplier = $this->makeSupplier();

        $this->actingAs($this->purchasing())
            ->get(route('suppliers.index'))->assertOk();

        $this->actingAs($this->purchasing())
            ->post(route('suppliers.store'), ['name' => 'مورد جديد'])
            ->assertRedirect(route('suppliers.index'));

        $this->actingAs($this->purchasing())
            ->put(route('suppliers.update', $supplier), ['name' => 'مورد محدث'])
            ->assertRedirect(route('suppliers.index'));

        $this->actingAs($this->purchasing())
            ->delete(route('suppliers.destroy', $supplier))
            ->assertRedirect(route('suppliers.index'));
    }

    public function test_warehouse_manager_can_only_view(): void
    {
        $this->actingAs($this->warehouseManager())
            ->get(route('suppliers.index'))->assertOk();

        $this->actingAs($this->warehouseManager())
            ->post(route('suppliers.store'), ['name' => 'مورد']) // حفظ/حذف يجب أن يفشل
            ->assertForbidden();
    }

    public function test_support_cannot_access_suppliers(): void
    {
        $this->actingAs($this->support())
            ->get(route('suppliers.index'))
            ->assertForbidden();
    }

    public function test_supplier_cannot_be_deleted_with_active_purchase_order(): void
    {
        $supplier = $this->makeSupplier();

        PurchaseOrder::create([
            'order_number' => 'PO-000001',
            'supplier_id' => $supplier->id,
            'status' => 'confirmed',
            'order_date' => now()->toDateString(),
            'created_by' => $this->admin()->id,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('suppliers.destroy', $supplier))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }

    public function test_supplier_can_be_deleted_with_only_historic_orders(): void
    {
        $supplier = $this->makeSupplier();

        PurchaseOrder::create([
            'order_number' => 'PO-000001',
            'supplier_id' => $supplier->id,
            'status' => 'received',
            'order_date' => now()->toDateString(),
            'created_by' => $this->admin()->id,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('suppliers.destroy', $supplier))
            ->assertRedirect(route('suppliers.index'));

        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }
}