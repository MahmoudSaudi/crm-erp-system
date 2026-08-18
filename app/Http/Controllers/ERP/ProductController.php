<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Controllers\CRM\ActivityController;
use App\Http\Requests\ERP\StoreProductRequest;
use App\Http\Requests\ERP\UpdateProductRequest;
use App\Http\Requests\ERP\UpdateProductStockRequest;
use App\Models\ERP\Category;
use App\Models\ERP\Product;
use App\Models\ERP\Unit;
use App\Models\ERP\Warehouse;
use App\Services\AuditLogger;
use App\Services\ERP\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class ProductController extends Controller
{
    public function __construct(private readonly StockService $stockService)
    {
    }

    public function index(Request $request): View
    {
        $products = Product::query()
            ->with(['category:id,name', 'unit:id,name', 'stockItems.warehouse:id,name'])
            ->search($request->get('search'))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->get('category_id')))
            ->when($request->get('stock_status') === 'low', function ($q) {
                $q->where('min_stock', '>', 0)
                    ->whereIn('id', function ($sub) {
                        $sub->select('product_id')
                            ->from('stock_items')
                            ->groupBy('product_id')
                            ->havingRaw('SUM(quantity) <= (SELECT min_stock FROM products WHERE products.id = stock_items.product_id)');
                    });
            })
            ->when($request->get('stock_status') === 'out', function ($q) {
                $q->where('min_stock', '>', 0)->where(function ($inner) {
                    $inner->whereNotIn('id', function ($sub) {
                        $sub->select('product_id')->from('stock_items')->where('quantity', '>', 0);
                    });
                });
            })
            ->when($request->filled('is_active') && in_array($request->get('is_active'), ['0', '1']), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $products->each(function (Product $product) {
            $product->setAttribute('stock_quantity', $product->stockItems->sum(fn ($item) => (float) $item->quantity));
        });

        return view('erp.products.index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'activeCategory' => $request->get('category_id'),
            'activeStatus' => $request->get('stock_status'),
        ]);
    }

    public function create(): View
    {
        return view('erp.products.create', $this->formData());
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $product = DB::transaction(function () use ($data, $request) {
            $product = Product::create($data);

            $stocks = $request->get('stocks', []) ?? [];
            foreach ($stocks as $warehouseId => $quantity) {
                $quantity = (float) ($quantity['quantity'] ?? 0);
                if ($quantity <= 0 || ! Warehouse::whereKey($warehouseId)->exists()) {
                    continue;
                }
                $this->stockService->in(
                    $product,
                    Warehouse::findOrFail($warehouseId),
                    $quantity,
                    'opening',
                    null,
                    'رصيد افتتاحي'
                );
            }

            return $product;
        });

        ActivityController::autoLog('note', 'إضافة منتج جديد', $product, ['name' => $product->name, 'sku' => $product->sku]);
        AuditLogger::log('created', $product, null, $product->toArray());

        return redirect()->route('products.index')->with('success', 'تم إضافة المنتج بنجاح');
    }

    public function edit(Product $product): View
    {
        $product->load(['stockItems.warehouse:id,name']);

        return view('erp.products.edit', ['product' => $product] + $this->formData());
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();

        $old = $product->only(array_keys($data));
        $product->update($data);

        ActivityController::autoLog('note', 'تحديث بيانات المنتج', $product, ['name' => $product->name, 'sku' => $product->sku]);
        AuditLogger::log('updated', $product, $old, $product->fresh()->only(array_keys($data)));

        return redirect()->route('products.index')->with('success', 'تم تحديث المنتج بنجاح');
    }

    public function destroy(Product $product): RedirectResponse
    {
        AuditLogger::log('deleted', $product, $product->toArray(), null);
        $product->delete();

        return redirect()->route('products.index')->with('success', 'تم حذف المنتج');
    }

    /**
     * One-click stock adjustment from the product edit/index (add, remove, adjust).
     */
    public function updateStock(UpdateProductStockRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();

        $warehouse = Warehouse::findOrFail($data['warehouse_id']);

        try {
            $movement = match ($data['action']) {
                'in' => $this->stockService->in($product, $warehouse, (float) $data['quantity'], 'manual', null, $data['note'] ?? null),
                'out' => $this->stockService->out($product, $warehouse, (float) $data['quantity'], 'manual', null, $data['note'] ?? null),
                'adjust' => $this->stockService->adjust($product, $warehouse, (float) $data['quantity'], $data['note'] ?? null),
            };
        } catch (\App\Exceptions\InsufficientStockException | RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLogger::log('stock_updated', $movement, null, $movement->toArray());

        return back()->with('success', 'تم تحديث المخزون بنجاح');
    }

    private function formData(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'units' => Unit::orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::active()->orderBy('name')->get(['id', 'name']),
        ];
    }
}
