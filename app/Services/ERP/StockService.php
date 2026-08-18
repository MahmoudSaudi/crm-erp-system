<?php

namespace App\Services\ERP;

use App\Exceptions\InsufficientStockException;
use App\Models\ERP\Product;
use App\Models\ERP\StockItem;
use App\Models\ERP\StockMovement;
use App\Models\ERP\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockService
{
    /**
     * Adds stock into a warehouse. Records an atomic stock movement.
     */
    public function in(
        Product $product,
        Warehouse $warehouse,
        float $quantity,
        string $type = 'adjustment',
        ?Model $reference = null,
        ?string $note = null
    ): StockMovement {
        if ($quantity <= 0) {
            throw new RuntimeException('الكمية يجب أن تكون أكبر من صفر');
        }

        return DB::transaction(function () use ($product, $warehouse, $quantity, $type, $reference, $note) {
            $item = $this->item($product, $warehouse);
            $before = (float) $item->quantity;
            $after = $before + $quantity;

            $item->update(['quantity' => $after]);

            return $this->movement($product, $warehouse, $type, $reference, 'in', $quantity, $before, $after, $note);
        });
    }

    /**
     * Removes stock from a warehouse. Throws when quantity is insufficient.
     */
    public function out(
        Product $product,
        Warehouse $warehouse,
        float $quantity,
        string $type = 'adjustment',
        ?Model $reference = null,
        ?string $note = null,
        bool $allowNegative = false
    ): StockMovement {
        if ($quantity <= 0) {
            throw new RuntimeException('الكمية يجب أن تكون أكبر من صفر');
        }

        return DB::transaction(function () use ($product, $warehouse, $quantity, $type, $reference, $note, $allowNegative) {
            $item = $this->item($product, $warehouse);
            $before = (float) $item->quantity;

            if (! $allowNegative && $quantity > $before) {
                throw new InsufficientStockException("الكمية المتوفرة للمنتج {$product->name} هي {$before} فقط");
            }

            $after = $allowNegative ? $before - $quantity : max(0, $before - $quantity);

            $item->update(['quantity' => $after]);

            return $this->movement($product, $warehouse, $type, $reference, 'out', $quantity, $before, $after, $note);
        });
    }

    /**
     * Adjusts stock to an absolute count (stocktaking/correction).
     */
    public function adjust(
        Product $product,
        Warehouse $warehouse,
        float $newQuantity,
        ?string $note = null
    ): StockMovement {
        if ($newQuantity < 0) {
            throw new RuntimeException('الكمية لا يمكن أن تكون سالبة');
        }

        return DB::transaction(function () use ($product, $warehouse, $newQuantity, $note) {
            $item = $this->item($product, $warehouse);
            $before = (float) $item->quantity;
            $diff = $newQuantity - $before;
            $item->update(['quantity' => $newQuantity]);

            return $this->movement(
                $product,
                $warehouse,
                'adjustment',
                null,
                $diff >= 0 ? 'in' : 'out',
                abs($diff),
                $before,
                $newQuantity,
                $note,
                true
            );
        });
    }

    /**
     * Transfers stock from one warehouse to another (two atomic movements).
     */
    public function transfer(
        Product $product,
        Warehouse $from,
        Warehouse $to,
        float $quantity,
        ?string $note = null
    ): array {
        if ($from->id === $to->id) {
            throw new RuntimeException('لا يمكن النقل إلى نفس المستودع');
        }

        return DB::transaction(function () use ($product, $from, $to, $quantity, $note) {
            $out = $this->out($product, $from, $quantity, 'transfer', null, $note);
            $in = $this->in($product, $to, $quantity, 'transfer', null, $note);

            return [$out, $in];
        });
    }

    public function quantityIn(Product $product, ?Warehouse $warehouse = null): float
    {
        return (float) StockItem::where('product_id', $product->id)
            ->when($warehouse, fn ($q) => $q->where('warehouse_id', $warehouse->id))
            ->sum('quantity');
    }

    private function item(Product $product, Warehouse $warehouse): StockItem
    {
        return StockItem::firstOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
            ['quantity' => 0]
        );
    }

    private function movement(
        Product $product,
        Warehouse $warehouse,
        string $type,
        ?Model $reference,
        string $direction,
        float $quantity,
        float $before,
        float $after,
        ?string $note,
        bool $isAdjustment = false
    ): StockMovement {
        $payload = [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'type' => $type,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference ? $reference->getKey() : null,
            'direction' => $direction,
            'quantity' => $quantity,
            'before_qty' => $before,
            'after_qty' => $after,
            'note' => $note,
            'created_by' => auth()->id(),
        ];

        if ($isAdjustment) {
            $payload['note'] = ($note ? $note.' · ' : '').'جرد: من '.number_format($before, 2).' إلى '.number_format($after, 2);
        }

        return StockMovement::create($payload);
    }
}
