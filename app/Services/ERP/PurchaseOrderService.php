<?php

namespace App\Services\ERP;

use App\Enums\PurchaseOrderStatus;
use App\Models\ERP\PurchaseOrder;
use App\Models\ERP\PurchaseOrderItem;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseOrderService
{
    public function __construct(private readonly StockService $stockService)
    {
    }

    /**
     * Recalculates order total from its items.
     */
    public function recalculateTotals(PurchaseOrder $order): PurchaseOrder
    {
        $order->update([
            'total' => $order->items->sum(fn (PurchaseOrderItem $item) => (float) $item->total),
        ]);

        return $order->fresh();
    }

    /**
     * Confirms a purchase order. Does NOT touch stock.
     */
    public function confirm(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->status !== PurchaseOrderStatus::Draft->value) {
            throw new RuntimeException('لا يمكن تأكيد أمر غير مسودة');
        }

        $old = $order->only('status');
        $order->update(['status' => PurchaseOrderStatus::Confirmed->value]);

        AuditLogger::log('confirmed', $order, $old, $order->fresh()->only(['status']));

        return $order->fresh();
    }

    /**
     * Partially or fully receives stock for a confirmed order.
     * Uses the warehouse saved on the order. Every received line
     * creates its own stock movement. Any rule violation rolls back.
     */
    public function receive(PurchaseOrder $order, array $quantities): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $quantities) {
            if ($order->status !== PurchaseOrderStatus::Confirmed->value) {
                throw new RuntimeException('يجب تأكيد أمر الشراء أولًا قبل الاستلام');
            }

            if (! $order->warehouse) {
                throw new RuntimeException('لا يوجد مستودع محدد لأمر الشراء');
            }

            foreach ($order->items as $item) {
                $qty = (float) ($quantities[$item->id] ?? 0);

                if ($qty <= 0) {
                    continue;
                }

                if ($qty > $item->remaining()) {
                    throw new RuntimeException(
                        "الكمية المستلمة من {$item->product?->name} تتجاوز المتبقي ({$item->remaining()})"
                    );
                }

                $item->update(['received_qty' => (float) $item->received_qty + $qty]);

                if ($item->product) {
                    $this->stockService->in(
                        $item->product,
                        $order->warehouse,
                        $qty,
                        'purchase_order',
                        $order,
                        "أمر شراء {$order->order_number}"
                    );
                }
            }

            $order->refresh();

            if ($order->items->every(fn (PurchaseOrderItem $item) => $item->isFullyReceived())) {
                $old = $order->only('status');
                $order->update([
                    'status' => PurchaseOrderStatus::Received->value,
                    'received_at' => now(),
                ]);
                AuditLogger::log('received', $order, $old, ['status' => PurchaseOrderStatus::Received->value]);
            }

            return $order->fresh();
        });
    }

    /**
     * Cancels a draft/confirmed order. Forbidden once anything was received.
     */
    public function cancel(PurchaseOrder $order): PurchaseOrder
    {
        if (in_array($order->status, [
            PurchaseOrderStatus::Received->value,
            PurchaseOrderStatus::Cancelled->value,
        ], true)) {
            throw new RuntimeException('لا يمكن إلغاء أمر مستلم أو ملغي مسبقًا');
        }

        foreach ($order->items as $item) {
            if ((float) $item->received_qty > 0) {
                throw new RuntimeException('لا يمكن إلغاء أمر شراء تم استلام كميات منه');
            }
        }

        $old = $order->only('status');
        $order->update(['status' => PurchaseOrderStatus::Cancelled->value]);

        AuditLogger::log('cancelled', $order, $old, ['status' => PurchaseOrderStatus::Cancelled->value]);

        return $order->fresh();
    }
}