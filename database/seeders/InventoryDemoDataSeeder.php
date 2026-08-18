<?php

namespace Database\Seeders;

use App\Models\ERP\Category;
use App\Models\ERP\Product;
use App\Models\ERP\StockMovement;
use App\Models\ERP\Unit;
use App\Models\ERP\Warehouse;
use App\Services\ERP\StockService;
use App\Models\User;
use Illuminate\Database\Seeder;

class InventoryDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::exists()) {
            $this->command->warn('تم تجاوز سيدر Inventory Demo: توجد منتجات سابقة.');

            return;
        }

        $warehouseUser = User::where('email', 'warehouse@crm.test')->first() ?? User::first();

        $categories = [
            'إلكترونيات' => ['أجهزة كمبيوتر', 'ملحقات'],
            'مكتبية' => ['قرطاسية', 'أثاث'],
            'منزلية' => [],
            'عناية شخصية' => [],
        ];

        foreach ($categories as $name => $children) {
            $parent = Category::create(['name' => $name, 'slug' => str()->slug($name).'-'.uniqid()]);
            foreach ($children as $child) {
                Category::create([
                    'name' => $child,
                    'slug' => str()->slug($child).'-'.uniqid(),
                    'parent_id' => $parent->id,
                ]);
            }
        }

        $units = [
            ['name' => 'قطعة', 'code' => 'PCS'],
            ['name' => 'كرتونة', 'code' => 'CTN'],
            ['name' => 'متر', 'code' => 'MTR'],
            ['name' => 'كيلوغرام', 'code' => 'KG'],
            ['name' => 'علبة', 'code' => 'BOX'],
        ];

        foreach ($units as $unit) {
            Unit::create($unit);
        }

        $warehouses = [
            ['name' => 'المستودع الرئيسي', 'code' => 'WH-01', 'address' => 'رام الله - شارع الإرسال'],
            ['name' => 'مستودع الخليل', 'code' => 'WH-02', 'address' => 'الخليل - المنطقة الصناعية'],
            ['name' => 'مستودع جنين', 'code' => 'WH-03', 'address' => 'جنين - شارع السلام'],
        ];

        foreach ($warehouses as $warehouse) {
            Warehouse::create($warehouse);
        }

        $service = app(StockService::class);

        $products = $this->productsData();

        foreach ($products as $index => $data) {
            $category = Category::where('name', $data['category'])->first()
                ?? Category::where('name', 'منزلية')->first();

            $unit = Unit::where('code', $data['unit'])->first() ?? Unit::first();

            $product = Product::create([
                'name' => $data['name'],
                'sku' => $data['sku'],
                'barcode' => $data['sku'],
                'category_id' => $category?->id,
                'unit_id' => $unit?->id,
                'sale_price' => $data['sale_price'],
                'purchase_cost' => $data['purchase_cost'],
                'min_stock' => $data['min_stock'],
                'description' => 'منتج تجريبي ضمن بيانات العرض التوضيحي.',
                'is_active' => true,
            ]);

            $mainWarehouse = Warehouse::active()->orderBy('id')->first();
            $warehouse = $mainWarehouse ?? Warehouse::first();
            $quantity = $data['quantity'];

            auth()->login($warehouseUser);
            $service->in($product, $warehouse, $quantity, 'opening', null, 'رصيد افتتاحي');

            if ($index % 4 === 0 && $quantity > 10) {
                $otherWarehouse = Warehouse::whereKeyNot($warehouse->id)->inRandomOrder()->first();
                if ($otherWarehouse) {
                    $transferQty = min(5, $quantity / 2);
                    $service->transfer($product, $warehouse, $otherWarehouse, $transferQty, 'نقل بين المستودعات');
                }
            }
        }

        auth()->logout();

        $this->command->info('تم إدخال بيانات المخزون التجريبية:');
        $this->command->line('  - Categories: '.Category::count());
        $this->command->line('  - Units: '.Unit::count());
        $this->command->line('  - Warehouses: '.Warehouse::count());
        $this->command->line('  - Products: '.Product::count());
        $this->command->line('  - Stock Movements: '.StockMovement::count());
    }

    private function productsData(): array
    {
        return [
            ['name' => 'حاسوب محمول Dell Latitude', 'sku' => 'LAP-DELL-01', 'category' => 'أجهزة كمبيوتر', 'unit' => 'PCS', 'sale_price' => 4200, 'purchase_cost' => 3600, 'min_stock' => 5, 'quantity' => 18],
            ['name' => 'حاسوب مكتبي HP Pro', 'sku' => 'PC-HP-01', 'category' => 'أجهزة كمبيوتر', 'unit' => 'PCS', 'sale_price' => 2800, 'purchase_cost' => 2300, 'min_stock' => 5, 'quantity' => 12],
            ['name' => 'شاشة 24 بوصة Samsung', 'sku' => 'MON-SAM-24', 'category' => 'أجهزة كمبيوتر', 'unit' => 'PCS', 'sale_price' => 750, 'purchase_cost' => 600, 'min_stock' => 8, 'quantity' => 26],
            ['name' => 'لوحة مفاتيح لاسلكية', 'sku' => 'KB-WL-01', 'category' => 'ملحقات', 'unit' => 'PCS', 'sale_price' => 120, 'purchase_cost' => 75, 'min_stock' => 15, 'quantity' => 60],
            ['name' => 'ماوس لاسلكي Logitech', 'sku' => 'MS-LG-01', 'category' => 'ملحقات', 'unit' => 'PCS', 'sale_price' => 90, 'purchase_cost' => 55, 'min_stock' => 15, 'quantity' => 3],
            ['name' => 'ورق A4 - رزمة', 'sku' => 'PAP-A4-500', 'category' => 'قرطاسية', 'unit' => 'BOX', 'sale_price' => 45, 'purchase_cost' => 32, 'min_stock' => 30, 'quantity' => 120],
            ['name' => 'قلم حبر جاف (علبة 12)', 'sku' => 'PEN-BLU-12', 'category' => 'قرطاسية', 'unit' => 'BOX', 'sale_price' => 18, 'purchase_cost' => 10, 'min_stock' => 40, 'quantity' => 25],
            ['name' => 'دباسة مكتبية كبيرة', 'sku' => 'STP-LRG-01', 'category' => 'قرطاسية', 'unit' => 'PCS', 'sale_price' => 35, 'purchase_cost' => 22, 'min_stock' => 20, 'quantity' => 45],
            ['name' => 'كرسي مكتبي دوار', 'sku' => 'CHR-ROT-01', 'category' => 'أثاث', 'unit' => 'PCS', 'sale_price' => 650, 'purchase_cost' => 480, 'min_stock' => 5, 'quantity' => 8],
            ['name' => 'مكتب خشبي بسطح زجاجي', 'sku' => 'DSK-GLS-01', 'category' => 'أثاث', 'unit' => 'PCS', 'sale_price' => 1800, 'purchase_cost' => 1400, 'min_stock' => 3, 'quantity' => 4],
            ['name' => 'مفرمة كهربائية 500 واط', 'sku' => 'GRN-500W', 'category' => 'منزلية', 'unit' => 'PCS', 'sale_price' => 320, 'purchase_cost' => 240, 'min_stock' => 10, 'quantity' => 22],
            ['name' => 'غلاية ماء كهربائية', 'sku' => 'KTL-01', 'category' => 'منزلية', 'unit' => 'PCS', 'sale_price' => 180, 'purchase_cost' => 120, 'min_stock' => 10, 'quantity' => 2],
            ['name' => 'شامبو بالبابونج 500 مل', 'sku' => 'SHP-500', 'category' => 'عناية شخصية', 'unit' => 'PCS', 'sale_price' => 40, 'purchase_cost' => 25, 'min_stock' => 25, 'quantity' => 80],
            ['name' => 'معجون أسنان بالنعناع', 'sku' => 'TPT-MNT', 'category' => 'عناية شخصية', 'unit' => 'PCS', 'sale_price' => 15, 'purchase_cost' => 8, 'min_stock' => 30, 'quantity' => 150],
        ];
    }
}
