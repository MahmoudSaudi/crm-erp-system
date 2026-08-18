<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\ERP\StoreUnitRequest;
use App\Http\Requests\ERP\UpdateUnitRequest;
use App\Models\ERP\Unit;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(): View
    {
        $units = Unit::withCount('products')->orderBy('name')->paginate(20);

        return view('erp.units.index', ['units' => $units]);
    }

    public function create(): View
    {
        return view('erp.units.create');
    }

    public function store(StoreUnitRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $unit = Unit::create($data);

        AuditLogger::log('created', $unit, null, $unit->toArray());

        return redirect()->route('units.index')->with('success', 'تم إضافة الوحدة بنجاح');
    }

    public function edit(Unit $unit): View
    {
        return view('erp.units.edit', ['unit' => $unit]);
    }

    public function update(UpdateUnitRequest $request, Unit $unit): RedirectResponse
    {
        $data = $request->validated();

        $old = $unit->only(array_keys($data));
        $unit->update($data);

        AuditLogger::log('updated', $unit, $old, $unit->fresh()->only(array_keys($data)));

        return redirect()->route('units.index')->with('success', 'تم تحديث الوحدة بنجاح');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        if ($unit->products()->exists()) {
            return back()->with('error', 'لا يمكن حذف وحدة مرتبطة بمنتجات');
        }

        AuditLogger::log('deleted', $unit, $unit->toArray(), null);
        $unit->delete();

        return redirect()->route('units.index')->with('success', 'تم حذف الوحدة');
    }
}
