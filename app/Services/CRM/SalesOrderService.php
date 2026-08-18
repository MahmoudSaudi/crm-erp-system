<?php

namespace App\Services\CRM;

use App\Enums\SalesOrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\CRM\SalesOrder;
use App\Models\CRM\SalesOrderItem;
use App\Services\AuditLogger;
use App\Services\ERP\StockService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SalesOrderService
{
    public function __construct(private readonly StockService $stockService)
    {
    }

    /**
     * Recalculates order totals from its items.
     */
    public function recalculateTotals(SalesOrder $order): SalesOrder
    {
        $items = $order->items;
        $subtotal = $items->sum(fn (SalesOrderItem $item) => (float) $item->total);

        $discountAmount = $order->discount_amount ?? 0;
        $taxRate = 0;

        $afterDiscount = max(0, $subtotal - (float) $discountAmount);
        $taxAmount = round($afterDiscount * $taxRate, 2);

        $order->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => max(0, $afterDiscount + (float) $order->shipping_amount + $taxAmount),
        ]);

        return $order->fresh();
    }

    /**
     * Confirms an order: validates stock then deducts it for each line
     * (stock deduction happens automatically on confirmation).
     */
    public function confirm(SalesOrder $order): SalesOrder
    {
        if ($order->status === SalesOrderStatus::Cancelled->value) {
            throw new RuntimeException('لا يمكن تأكيد أمر ملغي');
        }

        return DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                if (! $item->product) {
                    continue;
                }

                $available = $this->stockService->quantityIn($item->product);

                if ((float) $item->quantity > $available) {
                    throw new InsufficientStockException(
                        "الكمية المتوفرة من {$item->product->name} هي {$available} والمطلوبة {$item->quantity}"
                    );
                }
            }

            $order->update([
                'status' => SalesOrderStatus::Confirmed->value,
                'confirmed_at' => now(),
            ]);

            foreach ($order->items as $item) {
                if (! $item->product) {
                    continue;
                }

                $this->stockService->out(
                    $item->product,
                    $this->defaultWarehouse(),
                    (float) $item->quantity,
                    'sales_order',
                    $order,
                    "أمر بيع {$order->order_number}"
                );
            }

            AuditLogger::log('confirmed', $order, null, $order->fresh()->only(['status', 'confirmed_at']));

            return $order->fresh();
        });
    }

    /**
     * Fulfills a confirmed order: marks it fulfilled.
     * Stock was already deducted on confirmation.
     */
    public function fulfill(SalesOrder $order): SalesOrder
    {
        if ($order->status !== SalesOrderStatus::Confirmed->value) {
            throw new RuntimeException('يجب تأكيد الأمر أولًا قبل تنفيذه');
        }

        $old = $order->only('status');
        $order->update([
            'status' => SalesOrderStatus::Fulfilled->value,
            'fulfilled_at' => now(),
        ]);

        AuditLogger::log('fulfilled', $order, $old, ['status' => SalesOrderStatus::Fulfilled->value]);

        return $order;
    }

    public function cancel(SalesOrder $order): SalesOrder
    {
        if (in_array($order->status, [SalesOrderStatus::Fulfilled->value, SalesOrderStatus::Cancelled->value], true)) {
            throw new RuntimeException('لا يمكن إلغاء أمر منفذ أو ملغي مسبقًا');
        }

        return DB::transaction(function () use ($order) {
            $old = $order->only('status');

            if ($order->status === SalesOrderStatus::Confirmed->value) {
                foreach ($order->items as $item) {
                    if (! $item->product) {
                        continue;
                    }

                    $this->stockService->in(
                        $item->product,
                        $this->defaultWarehouse(),
                        (float) $item->quantity,
                        'sales_order_return',
                        $order,
                        "إلغاء أمر بيع {$order->order_number}"
                    );
                }
            }

            $order->update(['status' => SalesOrderStatus::Cancelled->value]);

            AuditLogger::log('cancelled', $order, $old, ['status' => SalesOrderStatus::Cancelled->value]);

            return $order->fresh();
        });
    }

    private function defaultWarehouse()
    {
        return \App\Models\ERP\Warehouse::active()->first() ?? \App\Models\ERP\Warehouse::first();
    }
}
