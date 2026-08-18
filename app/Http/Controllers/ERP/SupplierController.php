<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\ERP\StoreSupplierRequest;
use App\Http\Requests\ERP\UpdateSupplierRequest;
use App\Models\ERP\PurchaseOrder;
use App\Models\ERP\Supplier;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $suppliers = Supplier::query()
            ->withCount('purchaseOrders')
            ->search($request->get('search'))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('erp.suppliers.index', [
            'suppliers' => $suppliers,
            'activeStatus' => $request->get('is_active'),
        ]);
    }

    public function create(): View
    {
        return view('erp.suppliers.create');
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $supplier = Supplier::create($data + ['is_active' => $request->boolean('is_active', true)]);

        AuditLogger::log('created', $supplier, null, $supplier->toArray());

        return redirect()->route('suppliers.index')->with('success', 'تم إضافة المورد بنجاح');
    }

    public function edit(Supplier $supplier): View
    {
        return view('erp.suppliers.edit', ['supplier' => $supplier]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validated();

        $old = $supplier->only(array_keys($data));
        $supplier->update($data + ['is_active' => $request->boolean('is_active', true)]);

        AuditLogger::log('updated', $supplier, $old, $supplier->fresh()->only(array_keys($data)));

        return redirect()->route('suppliers.index')->with('success', 'تم تحديث المورد بنجاح');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $hasActiveOrders = PurchaseOrder::where('supplier_id', $supplier->id)
            ->whereIn('status', ['draft', 'confirmed'])
            ->exists();

        if ($hasActiveOrders) {
            return back()->with('error', 'لا يمكن حذف مورد لديه أوامر شراء نشطة');
        }

        AuditLogger::log('deleted', $supplier, $supplier->toArray(), null);
        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', 'تم حذف المورد');
    }
}