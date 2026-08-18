<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SalesOrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\CRM\Customer;
use App\Models\CRM\SalesOrder;
use App\Models\CRM\SalesOrderItem;
use App\Models\ERP\Expense;
use App\Models\ERP\Invoice;
use App\Models\ERP\Product;
use App\Models\ERP\PurchaseOrder;
use App\Models\ERP\PurchaseOrderItem;
use App\Models\ERP\Supplier;
use App\Models\ERP\Warehouse;
use App\Models\User;
use App\Services\CRM\SalesOrderService;
use App\Services\ERP\InvoiceService;
use App\Services\ERP\PurchaseOrderService;
use Illuminate\Database\Seeder;
use RuntimeException;

class FinalDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSuppliers();
        $this->seedPurchaseOrders();
        $this->seedExtendedSales();
    }

    private function seedSuppliers(): void
    {
        if (Supplier::exists()) {
            $this->command->warn('تم تجاوز موردين Seeder النهائي: توجد موردون سابقة.');

            return;
        }

        $suppliers = [
            ['شركة الأفق للتجارة العامة', 'فادي الأغا', 'التخزين والتوزيع'],
            ['مؤسسة النخبة للاستيراد', 'سامي قطينة', 'استيراد إلكترونيات'],
            ['شركة القدس للمواد المكتبية', 'ربى الجيوسي', 'قرطاسية وأثاث'],
            ['مصنع العناية للصناعات', 'نبيل رمضان', 'منتجات عناية شخصية'],
            ['شركة المشرق للتوزيع', 'حنان عودة', 'توزيع مواد منزلية'],
            ['مؤسسة البركة للأغذية', 'إبراهيم عودة', 'مواد غذائية'],
        ];

        foreach ($suppliers as [$name, $contact, $desc]) {
            Supplier::create([
                'name' => $name,
                'contact_name' => $contact,
                'phone' => '0597'.str_pad((string) rand(100000, 999999), 6, '0', STR_PAD_LEFT),
                'email' => strtolower(str_replace(['شركة', 'مؤسسة', 'مصنع', ' '], ['', '', '', '.'], $name)).'@supplier.ps',
                'tax_number' => (string) rand(100000000, 999999999),
                'address' => $desc,
                'is_active' => true,
            ]);
        }

        $this->command->info('الموردون: '.Supplier::count());
    }

    private function seedPurchaseOrders(): void
    {
        if (PurchaseOrder::exists()) {
            $this->command->warn('تم تجاوز أوامر شراء Seeder النهائي: توجد أوامر شراء سابقة.');

            return;
        }

        $users = User::whereIn('email', ['purchasing@crm.test', 'warehouse@crm.test'])->get();
        $purchasing = $users->firstWhere('email', 'purchasing@crm.test') ?? User::first();
        $warehouseUser = $users->firstWhere('email', 'warehouse@crm.test') ?? User::first();
        $manager = User::where('email', 'manager@crm.test')->first() ?? User::first();

        $suppliers = Supplier::get();
        $products = Product::where('is_active', true)->get();
        $warehouse = Warehouse::active()->first() ?? Warehouse::first();

        $service = app(PurchaseOrderService::class);

        // [monthsAgo, status, lines count]
        $plan = [
            [5, PurchaseOrderStatus::Received->value, 3],
            [5, PurchaseOrderStatus::Received->value, 2],
            [4, PurchaseOrderStatus::Received->value, 3],
            [4, PurchaseOrderStatus::Received->value, 2],
            [3, PurchaseOrderStatus::Received->value, 3],
            [3, PurchaseOrderStatus::Confirmed->value, 2],
            [2, PurchaseOrderStatus::Received->value, 3],
            [2, PurchaseOrderStatus::Draft->value, 2],
            [1, PurchaseOrderStatus::Confirmed->value, 3],
            [1, PurchaseOrderStatus::Draft->value, 2],
            [0, PurchaseOrderStatus::Cancelled->value, 2],
            [0, PurchaseOrderStatus::Confirmed->value, 2],
        ];

        $base = (int) (PurchaseOrder::withTrashed()->max('id') ?? 0) + 1;
        $created = 0;
        $received = 0;

        auth()->login($purchasing);

        foreach ($plan as $i => [$monthsAgo, $status, $lines]) {
            $orderDate = now()->subMonths($monthsAgo)->startOfMonth()->addDays(min(rand(1, 20), 25));

            $order = PurchaseOrder::create([
                'order_number' => 'PO-'.str_pad((string) ($base + $i), 6, '0', STR_PAD_LEFT),
                'supplier_id' => $suppliers->random()->id,
                'status' => PurchaseOrderStatus::Draft->value,
                'order_date' => $orderDate->toDateString(),
                'expected_date' => $orderDate->copy()->addDays(rand(5, 12))->toDateString(),
                'warehouse_id' => $warehouse->id,
                'notes' => 'أمر شراء تجريبي ضمن بيانات العرض التوضيحي.',
                'created_by' => $purchasing->id,
            ]);

            $picked = $products->random($lines);
            foreach ($picked as $product) {
                $qty = rand(8, 40);
                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'received_qty' => 0,
                    'unit_cost' => $product->purchase_cost,
                    'total' => round($qty * (float) $product->purchase_cost, 2),
                ]);
            }
            $service->recalculateTotals($order);
            $created++;

            if ($status === PurchaseOrderStatus::Draft->value) {
                continue;
            }

            try {
                $service->confirm($order);
            } catch (RuntimeException $e) {
                $this->command->error("تعذر تأكيد أمر الشراء {$order->order_number}: {$e->getMessage()}");

                continue;
            }

            if ($status === PurchaseOrderStatus::Cancelled->value) {
                $service->cancel($order);

                continue;
            }

            $quantities = $order->items->mapWithKeys(fn ($item) => [$item->id => (float) $item->quantity])->all();
            try {
                auth()->login($warehouseUser);
                $service->receive($order, $quantities);
                $order->update([
                    'received_at' => $order->order_date->copy()->addDays(rand(3, 10)),
                ]);
                $received++;
            } catch (RuntimeException $e) {
                $this->command->error("تعذر استلام أمر الشراء {$order->order_number}: {$e->getMessage()}");
            }

            auth()->login($purchasing);
        }

        auth()->logout();

        $this->command->info('أوامر الشراء: '.$created.' (مستلم: '.$received.')');
    }

    private function seedExtendedSales(): void
    {
        if (SalesOrder::where('order_date', '<', now()->subMonths(2)->toDateString())->exists()) {
            $this->command->warn('تم تجاوز مبيعات Seeder النهائي: توجد مبيعات ممتدة سابقة.');

            return;
        }

        $users = User::whereIn('email', ['manager@crm.test', 'rep@crm.test', 'accountant@crm.test'])->get();
        $manager = $users->firstWhere('email', 'manager@crm.test') ?? User::first();
        $rep = $users->firstWhere('email', 'rep@crm.test') ?? User::first();
        $accountant = $users->firstWhere('email', 'accountant@crm.test') ?? User::first();

        $customers = Customer::inRandomOrder()->limit(12)->get();
        if ($customers->isEmpty()) {
            $this->command->warn('لا يوجد عملاء لتوليد بيانات مبيعات ممتدة. شغّل CrmDemoDataSeeder أولاً.');

            return;
        }

        $products = Product::where('is_active', true)->get();

        $orderService = app(SalesOrderService::class);
        $invoiceService = app(InvoiceService::class);

        // [monthsAgo, status, paidRatio (0 = unpaid/overdue, null = partial, 1 = full)]
        $plan = [
            [5, SalesOrderStatus::Fulfilled->value, 1],
            [5, SalesOrderStatus::Fulfilled->value, 0],
            [4, SalesOrderStatus::Fulfilled->value, 1],
            [4, SalesOrderStatus::Fulfilled->value, null],
            [3, SalesOrderStatus::Fulfilled->value, 1],
            [3, SalesOrderStatus::Fulfilled->value, null],
            [3, SalesOrderStatus::Confirmed->value, 0],
            [2, SalesOrderStatus::Fulfilled->value, 1],
            [2, SalesOrderStatus::Fulfilled->value, null],
            [2, SalesOrderStatus::Confirmed->value, 0],
            [1, SalesOrderStatus::Fulfilled->value, 1],
            [1, SalesOrderStatus::Confirmed->value, 0],
            [1, SalesOrderStatus::Draft->value, null],
            [0, SalesOrderStatus::Fulfilled->value, 1],
            [0, SalesOrderStatus::Confirmed->value, 0],
            [0, SalesOrderStatus::Draft->value, null],
        ];

        $base = (int) (SalesOrder::withTrashed()->max('id') ?? 0) + 1;
        $createdOrders = 0;
        $createdInvoices = 0;
        $createdPayments = 0;

        auth()->login($rep);

        foreach ($plan as $i => [$monthsAgo, $status, $paidRatio]) {
            $customer = $customers->get($i % $customers->count());
            $orderDate = now()->subMonths($monthsAgo)->startOfMonth()->addDays(min(rand(1, 20), 25));

            $order = SalesOrder::create([
                'order_number' => 'SO-'.str_pad((string) ($base + $i), 6, '0', STR_PAD_LEFT),
                'customer_id' => $customer->id,
                'status' => SalesOrderStatus::Draft->value,
                'order_date' => $orderDate->toDateString(),
                'delivery_date' => $orderDate->copy()->addDays(7)->toDateString(),
                'discount_amount' => rand(0, 2) === 0 ? rand(10, 100) : 0,
                'shipping_amount' => rand(0, 2) === 0 ? rand(10, 30) : 0,
                'notes' => 'أمر بيع ضمن بيانات العرض التوضيحي الموسعة.',
                'created_by' => $rep->id,
            ]);

            $lineCount = 1 + ($i % 3);
            $picked = $products->random($lineCount);
            foreach ($picked as $product) {
                $qty = 1 + ($i % 3);
                SalesOrderItem::create([
                    'sales_order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $qty,
                    'unit_price' => $product->sale_price,
                    'discount' => 0,
                    'total' => round($qty * (float) $product->sale_price, 2),
                ]);
            }
            $orderService->recalculateTotals($order);
            $createdOrders++;

            if ($status === SalesOrderStatus::Draft->value) {
                continue;
            }

            try {
                $orderService->confirm($order);
            } catch (RuntimeException|InsufficientStockException $e) {
                $this->command->error("تعذر تأكيد أمر بيع {$order->order_number}: {$e->getMessage()}");

                continue;
            }

            $isFulfilled = $status === SalesOrderStatus::Fulfilled->value;

            try {
                $invoice = $invoiceService->createFromOrder($order, [
                    'issue_date' => $orderDate->copy()->addDays(rand(1, 4))->toDateString(),
                    'due_date' => $orderDate->copy()->addDays(rand(12, 25))->toDateString(),
                    'notes' => 'فاتورة ضمن بيانات العرض التوضيحي الموسعة.',
                ]);
                $createdInvoices++;

                if ($paidRatio === 0) {
                    $invoice->update([
                        'status' => InvoiceStatus::Sent->value,
                        'created_by' => $manager->id,
                    ]);
                } else {
                    $invoice->update(['created_by' => $manager->id]);

                    $due = (float) $invoice->total;
                    $amount = $paidRatio === null
                        ? round($due * (rand(30, 70) / 100), 2)
                        : $due;

                    if ($amount > 0 && $amount <= $due) {
                        $method = PaymentMethod::cases()[$i % count(PaymentMethod::cases())];

                        auth()->login($accountant);
                        $invoiceService->recordPayment($invoice, [
                            'amount' => $amount,
                            'method' => $method->value,
                            'reference' => 'REF-EXT-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                            'paid_at' => $orderDate->copy()->addDays(rand(6, 15))->toDateString(),
                            'notes' => null,
                            'created_by' => $accountant->id,
                        ]);
                        $createdPayments++;
                        auth()->login($rep);
                    }
                }
            } catch (RuntimeException $e) {
                $this->command->warn("تعذر إنشاء فاتورة لأمر بيع {$order->order_number}: {$e->getMessage()}");
            }

            if ($isFulfilled) {
                $orderService->fulfill($order);
            }
        }

        auth()->logout();

        $this->command->info('المبيعات الموسعة:');
        $this->command->line('  - Sales Orders: '.$createdOrders);
        $this->command->line('  - Invoices: '.$createdInvoices);
        $this->command->line('  - Payments: '.$createdPayments);
    }
}
