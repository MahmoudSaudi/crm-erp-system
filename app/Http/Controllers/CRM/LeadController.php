<?php

namespace App\Http\Controllers\CRM;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\StoreLeadRequest;
use App\Http\Requests\CRM\UpdateLeadRequest;
use App\Http\Requests\CRM\UpdateLeadStatusRequest;
use App\Models\CRM\Lead;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CRM\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function __construct(private readonly LeadService $leadService) {}

    public function index(Request $request): View
    {
        $leads = Lead::query()
            ->with(['assignedTo:id,name', 'customer:id,name'])
            ->search($request->get('search'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->get('assigned_to')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('crm.leads.index', [
            'leads' => $leads,
            'statuses' => LeadStatus::list(),
            'users' => User::where('is_active', true)->get(['id', 'name']),
            'activeStatus' => $request->get('status'),
            'activeAssignee' => $request->get('assigned_to'),
        ]);
    }

    public function pipeline(Request $request): View
    {
        $leads = Lead::with(['assignedTo:id,name', 'customer:id,name'])
            ->orderBy('next_follow_up_at')
            ->get()
            ->groupBy(fn (Lead $lead) => $lead->status);

        return view('crm.leads.pipeline', [
            'leadsByStatus' => $leads,
            'statuses' => LeadStatus::list(),
        ]);
    }

    public function create(): View
    {
        return view('crm.leads.create', [
            'statuses' => LeadStatus::list(),
            'users' => User::where('is_active', true)->get(['id', 'name']),
        ]);
    }

    public function store(StoreLeadRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $lead = Lead::create($data + ['created_by' => auth()->id()]);

        ActivityController::autoLog('create', 'إضافة عميل محتمل', $lead, $data);

        AuditLogger::log('created', $lead, null, $data);

        return redirect()->route('leads.index')->with('success', 'تمت إضافة العميل المحتمل بنجاح');
    }

    public function edit(Lead $lead): View
    {
        return view('crm.leads.edit', [
            'lead' => $lead,
            'statuses' => LeadStatus::list(),
            'users' => User::where('is_active', true)->get(['id', 'name']),
        ]);
    }

    public function update(UpdateLeadRequest $request, Lead $lead): RedirectResponse
    {
        $data = $request->validated();

        $old = $lead->only(array_keys($data));
        $lead->update($data);

        ActivityController::autoLog('update', "تحديث بيانات {$lead->full_name}", $lead, $data);
        AuditLogger::log('updated', $lead, $old, $lead->fresh()->only(array_keys($data)));

        return redirect()->route('leads.index')->with('success', 'تم تحديث العميل المحتمل بنجاح');
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $lead->activities()->delete();
        AuditLogger::log('deleted', $lead, $lead->toArray(), null);
        $lead->delete();

        return redirect()->route('leads.index')->with('success', 'تم حذف العميل المحتمل');
    }

    public function convert(Request $request, Lead $lead): RedirectResponse
    {
        if ($lead->customer) {
            return back()->with('error', 'هذا العميل المحتمل تم تحويله مسبقًا');
        }

        $customer = $this->leadService->convert($lead, $request->get('opportunity_title'));

        return redirect()->route('customers.show', $customer)->with('success', 'تم تحويل العميل المحتمل إلى عميل بنجاح');
    }

    public function updateStatus(UpdateLeadStatusRequest $request, Lead $lead): RedirectResponse
    {
        $old = $lead->status;
        $lead->update(['status' => $request->get('status')]);

        if ($request->get('status') !== $old) {
            ActivityController::autoLog('update', 'تغيير حالة إلى '.LeadStatus::from($request->get('status'))->label(), $lead, ['status' => $request->get('status')]);
        }

        AuditLogger::log('status_changed', $lead, ['status' => $old], ['status' => $request->get('status')]);

        return back()->with('success', 'تم تحديث الحالة');
    }
}
