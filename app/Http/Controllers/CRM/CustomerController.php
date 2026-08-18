<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\StoreCustomerRequest;
use App\Http\Requests\CRM\UpdateCustomerRequest;
use App\Models\CRM\Customer;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = Customer::query()
            ->with(['creator:id,name'])
            ->search($request->get('search'))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->get('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('crm.customers.index', [
            'customers' => $customers,
            'activeType' => $request->get('type'),
            'activeStatus' => $request->get('status'),
        ]);
    }

    public function create(): View
    {
        return view('crm.customers.create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data += ['balance' => 0, 'created_by' => auth()->id()];

        $customer = Customer::create($data);

        ActivityController::autoLog('note', "إضافة عميل {$customer->name}", $customer, ['type' => $data['type']]);
        AuditLogger::log('created', $customer, null, $customer->toArray());

        return redirect()->route('customers.show', $customer)->with('success', 'تمت إضافة العميل بنجاح');
    }

    public function show(Customer $customer): View
    {
        $customer->load([
            'createdFromLead:id,first_name,last_name',
            'opportunities.stage',
            'opportunities.assignedTo:id,name',
            'invoices',
            'tickets:id,subject,status,priority,created_at',
            'salesOrders:id,order_number,total,status,created_at',
            'payments:id,amount,method,paid_at,status',
        ]);

        return view('crm.customers.show', compact('customer'));
    }

    public function edit(Customer $customer): View
    {
        return view('crm.customers.edit', compact('customer'));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $data = $request->validated();

        $old = $customer->only(array_keys($data));
        $customer->update($data);

        ActivityController::autoLog('note', "تحديث بيانات {$customer->name}", $customer, $data);
        AuditLogger::log('updated', $customer, $old, $customer->fresh()->only(array_keys($data)));

        return redirect()->route('customers.show', $customer)->with('success', 'تم تحديث العميل بنجاح');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        AuditLogger::log('deleted', $customer, $customer->toArray(), null);
        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'تم حذف العميل');
    }
}
