<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\ERP\StoreWarehouseRequest;
use App\Http\Requests\ERP\UpdateWarehouseRequest;
use App\Models\ERP\Warehouse;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(): View
    {
        $warehouses = Warehouse::withCount('stockItems')
            ->withSum('stockItems', 'quantity')
            ->orderBy('name')
            ->paginate(20);

        return view('erp.warehouses.index', ['warehouses' => $warehouses]);
    }

    public function create(): View
    {
        return view('erp.warehouses.create');
    }

    public function store(StoreWarehouseRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $warehouse = Warehouse::create($data + ['is_active' => $request->boolean('is_active', true)]);

        AuditLogger::log('created', $warehouse, null, $warehouse->toArray());

        return redirect()->route('warehouses.index')->with('success', 'تم إضافة المستودع بنجاح');
    }

    public function edit(Warehouse $warehouse): View
    {
        return view('erp.warehouses.edit', ['warehouse' => $warehouse]);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $data = $request->validated();

        $old = $warehouse->only(array_keys($data));
        $warehouse->update($data + ['is_active' => $request->boolean('is_active', true)]);

        AuditLogger::log('updated', $warehouse, $old, $warehouse->fresh()->only(array_keys($data)));

        return redirect()->route('warehouses.index')->with('success', 'تم تحديث المستودع بنجاح');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        if ($warehouse->stockItems()->where('quantity', '>', 0)->exists()) {
            return back()->with('error', 'لا يمكن حذف مستودع يحتوي على رصيد مخزون');
        }

        AuditLogger::log('deleted', $warehouse, $warehouse->toArray(), null);
        $warehouse->delete();

        return redirect()->route('warehouses.index')->with('success', 'تم حذف المستودع');
    }
}
