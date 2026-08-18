<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\SalesOrderStatus;
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
use App\Exceptions\InsufficientStockException;
use Illuminate\Database\Seeder;
use RuntimeException;

class SalesDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (SalesOrder::exists()) {
            $this->command->warn('تم تجاوز سيدر Sales Demo: توجد أوامر بيع سابقة.');

            return;
        }

        $users = User::whereIn('email', ['manager@crm.test', 'rep@crm.test', 'accountant@crm.test'])->get();
        $manager = $users->firstWhere('email', 'manager@crm.test') ?? User::first();
        $rep = $users->firstWhere('email', 'rep@crm.test') ?? User::first();
        $accountant = $users->firstWhere('email', 'accountant@crm.test') ?? User::first();

        $customers = Customer::inRandomOrder()->limit(10)->get();
        if ($customers->isEmpty()) {
            $this->command->warn('لا يوجد عملاء لتوليد بيانات مبيعات. شغّل CrmDemoDataSeeder أولاً.');

            return;
        }

        $products = Product::where('is_active', true)->get();

        $orderService = app(SalesOrderService::class);
        $invoiceService = app(InvoiceService::class);

        $createdOrders = 0;
        $createdInvoices = 0;
        $createdPayments = 0;

        $sample = [
            // [order_day_offset, status, discount, shipping]
            [45, SalesOrderStatus::Fulfilled->value, 50, 20],
            [40, SalesOrderStatus::Fulfilled->value, 0, 25],
            [33, SalesOrderStatus::Fulfilled->value, 100, 0],
            [27, SalesOrderStatus::Confirmed->value, 25, 15],
            [20, SalesOrderStatus::Confirmed->value, 0, 0],
            [14, SalesOrderStatus::Draft->value, 0, 30],
            [8, SalesOrderStatus::Draft->value, 40, 0],
            [3, SalesOrderStatus::Fulfilled->value, 0, 15],
        ];

        foreach ($sample as $i => [$dayOffset, $status, $discount, $shipping]) {
            $customer = $customers->get($i % $customers->count());
            $lineCount = 1 + ($i % 3);
            $picked = $products->random($lineCount);

            auth()->login($rep);
            $order = SalesOrder::create([
                'order_number' => 'SO-'.str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT),
                'customer_id' => $customer->id,
                'status' => SalesOrderStatus::Draft->value,
                'order_date' => now()->subDays($dayOffset)->toDateString(),
                'delivery_date' => now()->subDays($dayOffset)->addDays(7)->toDateString(),
                'discount_amount' => $discount,
                'shipping_amount' => $shipping,
                'notes' => 'أمر بيع تجريبي ضمن بيانات العرض التوضيحي.',
                'created_by' => $rep->id,
            ]);

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

            $warehouse = Warehouse::active()->first();
            try {
                $orderService->confirm($order);
            } catch (RuntimeException|InsufficientStockException $e) {
                $this->command->error("تعذر تأكيد أمر {$order->order_number}: {$e->getMessage()}");

                continue;
            }
            try {
                $invoice = $invoiceService->createFromOrder($order, [
                    'issue_date' => now()->subDays(max(0, $dayOffset - 5))->toDateString(),
                    'due_date' => now()->subDays(max(0, $dayOffset - 20))->toDateString(),
                    'notes' => 'فاتورة تجريبية ضمن بيانات العرض التوضيحي.',
                ]);
                $createdInvoices++;

                $invoice->update([
                    'status' => InvoiceStatus::Sent->value,
                    'created_by' => $manager->id,
                ]);
            } catch (RuntimeException $e) {
                $this->command->error("تعذر إنشاء فاتورة لأمر {$order->order_number}: {$e->getMessage()}");

                continue;
            }

            $order->update(['status' => $status]);

            if ($status === SalesOrderStatus::Fulfilled->value && $i % 3 !== 1) {
                try {
                    $payRatio = in_array($i, [0, 2]) ? 1.0 : 0.5;
                    $amount = round((float) $invoice->total * $payRatio, 2);
                    $method = PaymentMethod::cases()[$i % count(PaymentMethod::cases())];

                    auth()->login($accountant);
                    $payment = $invoiceService->recordPayment($invoice, [
                        'amount' => $amount,
                        'method' => $method->value,
                        'reference' => 'REF-DEMO-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                        'paid_at' => now()->subDays(max(0, $dayOffset - 10))->toDateString(),
                        'notes' => null,
                        'created_by' => $accountant->id,
                    ]);
                    $createdPayments++;
                } catch (RuntimeException $e) {
                    $this->command->warn("تعذر تسجيل دفعة للفاتورة {$invoice->invoice_number}: {$e->getMessage()}");
                }
            }
        }

        auth()->logout();

        $expenses = [
            ['إيجار', 2500, 'إيجار المكتب الرئيسي', 35],
            ['رواتب', 12000, 'رواتب الشهر', 30],
            ['نقل', 350, 'وقود وتوصيل طلبات', 25],
            ['تسويق', 800, 'حملة إعلانات فيسبوك', 18],
            ['صيانة', 220, 'صيانة المكيف', 12],
            ['اتصالات', 180, 'فواتير إنترنت وهاتف', 6],
            ['مصاريف تشغيلية', 400, 'قرطاسية ولوازم مكتب', 4],
            ['أخرى', 150, 'مصاريف متفرقة', 2],
        ];

        foreach ($expenses as [$category, $amount, $description, $dayOffset]) {
            Expense::create([
                'category' => $category,
                'amount' => $amount,
                'description' => $description,
                'date' => now()->subDays($dayOffset)->toDateString(),
                'is_reimbursable' => $category === 'نقل',
                'created_by' => $manager->id,
            ]);
        }

        $this->command->info('تم إدخال بيانات المبيعات التجريبية:');
        $this->command->line('  - Sales Orders: '.SalesOrder::count());
        $this->command->line('  - Invoices: '.Invoice::count());
        $this->command->line('  - Payments: '.Payment::count());
        $this->command->line('  - Expenses: '.Expense::count());
    }
}
