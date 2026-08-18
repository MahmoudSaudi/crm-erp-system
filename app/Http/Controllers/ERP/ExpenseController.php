<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\ERP\StoreExpenseRequest;
use App\Http\Requests\ERP\UpdateExpenseRequest;
use App\Models\ERP\Expense;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $expenses = Expense::query()
            ->with('creator:id,name')
            ->search($request->get('search'))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->get('category')))
            ->when($request->filled('month'), function ($q) use ($request) {
                $q->whereYear('date', substr($request->get('month'), 0, 4))
                    ->whereMonth('date', substr($request->get('month'), 5, 2));
            })
            ->latest('date')
            ->paginate(20)
            ->withQueryString();

        $total = $expenses->getCollection()->sum(fn ($e) => (float) $e->amount);

        return view('erp.expenses.index', [
            'expenses' => $expenses,
            'categories' => Expense::categories(),
            'total' => $total,
            'activeCategory' => $request->get('category'),
            'activeMonth' => $request->get('month'),
        ]);
    }

    public function create(): View
    {
        return view('erp.expenses.create', ['categories' => Expense::categories()]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $expense = Expense::create($data + ['created_by' => auth()->id()]);

        AuditLogger::log('created', $expense, null, $expense->toArray());

        return redirect()->route('expenses.index')->with('success', 'تم تسجيل المصروف بنجاح');
    }

    public function edit(Expense $expense): View
    {
        return view('erp.expenses.edit', [
            'expense' => $expense,
            'categories' => Expense::categories(),
        ]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $data = $request->validated();

        $old = $expense->only(array_keys($data));
        $expense->update($data);

        AuditLogger::log('updated', $expense, $old, $expense->fresh()->only(array_keys($data)));

        return redirect()->route('expenses.index')->with('success', 'تم تحديث المصروف');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        AuditLogger::log('deleted', $expense, $expense->toArray(), null);
        $expense->delete();

        return redirect()->route('expenses.index')->with('success', 'تم حذف المصروف');
    }
}
