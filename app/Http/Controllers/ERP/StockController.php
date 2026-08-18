<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\ERP\StockAdjustRequest;
use App\Models\ERP\Product;
use App\Models\ERP\StockItem;
use App\Models\ERP\StockMovement;
use App\Models\ERP\Warehouse;
use App\Services\AuditLogger;
use App\Services\ERP\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class StockController extends Controller
{
    public function __construct(private readonly StockService $stockService)
    {
    }

    public function index(Request $request): View
    {
        $query = StockItem::query()
            ->with(['product:id,name,sku,min_stock,sale_price,category_id', 'product.category:id,name', 'warehouse:id,name'])
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->get('warehouse_id')))
            ->when($request->get('scope') === 'low', function ($q) {
                $q->whereHas('product', fn ($p) => $p->where('min_stock', '>', 0))
                    ->where('quantity', '<=', DB::raw('(SELECT min_stock FROM products WHERE products.id = stock_items.product_id)'))
                    ->where('quantity', '>', 0);
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->whereHas('product', fn ($p) => $p->search($request->get('search')));
            });

        $items = $query->orderBy('quantity')->paginate(20)->withQueryString();

        $lowCount = StockItem::where('quantity', '>', 0)
            ->whereHas('product', fn ($p) => $p->where('min_stock', '>', 0))
            ->whereRaw('quantity <= (SELECT min_stock FROM products WHERE products.id = stock_items.product_id)')
            ->count();

        return view('erp.stock.index', [
            'items' => $items,
            'warehouses' => Warehouse::active()->orderBy('name')->get(['id', 'name']),
            'activeWarehouse' => $request->get('warehouse_id'),
            'activeScope' => $request->get('scope', 'all'),
            'lowCount' => $lowCount,
            'products' => Product::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function movements(Request $request): View
    {
        $movements = StockMovement::query()
            ->with(['product:id,name,sku', 'warehouse:id,name', 'creator:id,name'])
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->get('product_id')))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->get('warehouse_id')))
            ->when($request->filled('direction'), fn ($q) => $q->where('direction', $request->get('direction')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('erp.stock.movements', [
            'movements' => $movements,
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'activeProduct' => $request->get('product_id'),
            'activeWarehouse' => $request->get('warehouse_id'),
            'activeDirection' => $request->get('direction'),
        ]);
    }

    public function adjust(StockAdjustRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $product = Product::findOrFail($data['product_id']);
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);

        try {
            $movement = $this->stockService->adjust(
                $product,
                $warehouse,
                (float) $data['new_quantity'],
                $data['note'] ?? null
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLogger::log('stock_adjusted', $movement, null, $movement->toArray());

        return back()->with('success', 'تم تسوية المخزون بنجاح (جرد/تصحيح)');
    }
}
