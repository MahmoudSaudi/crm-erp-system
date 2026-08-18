<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\StoreOpportunityRequest;
use App\Http\Requests\CRM\UpdateOpportunityRequest;
use App\Http\Requests\CRM\UpdateOpportunityStageRequest;
use App\Models\CRM\Customer;
use App\Models\CRM\Opportunity;
use App\Models\CRM\OpportunityStage;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpportunityController extends Controller
{
    public function index(Request $request): View
    {
        $opportunities = Opportunity::query()
            ->with(['customer:id,name', 'stage', 'assignedTo:id,name'])
            ->search($request->get('search'))
            ->when($request->filled('stage_id'), fn (Builder $q) => $q->where('stage_id', $request->get('stage_id')))
            ->when($request->filled('assigned_to'), fn (Builder $q) => $q->where('assigned_to', $request->get('assigned_to')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('crm.opportunities.index', [
            'opportunities' => $opportunities,
            'stages' => OpportunityStage::ordered(),
            'users' => User::where('is_active', true)->get(['id', 'name']),
            'activeStage' => $request->get('stage_id'),
            'activeAssignee' => $request->get('assigned_to'),
        ]);
    }

    public function pipeline(): View
    {
        $stages = OpportunityStage::ordered();

        $opportunities = Opportunity::with(['customer:id,name', 'assignedTo:id,name'])
            ->orderBy('amount', 'desc')
            ->get()
            ->groupBy('stage_id');

        return view('crm.opportunities.pipeline', [
            'stages' => $stages,
            'opportunitiesByStage' => $opportunities,
        ]);
    }

    public function create(): View
    {
        return view('crm.opportunities.create', [
            'stages' => OpportunityStage::ordered(),
            'customers' => Customer::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'users' => User::where('is_active', true)->get(['id', 'name']),
        ]);
    }

    public function store(StoreOpportunityRequest $request): RedirectResponse
    {
        $data = $request->validated() + ['created_by' => auth()->id()];

        $opportunity = Opportunity::create($data);

        ActivityController::autoLog('note', "إنشاء فرصة {$opportunity->title}", $opportunity, ['amount' => $data['amount']]);
        AuditLogger::log('created', $opportunity, null, $opportunity->toArray());

        return redirect()->route('opportunities.show', $opportunity)->with('success', 'تم إنشاء الفرصة بنجاح');
    }

    public function show(Opportunity $opportunity): View
    {
        $opportunity->load([
            'customer:id,name,phone,email',
            'lead:id,first_name,last_name',
            'stage',
            'assignedTo:id,name',
            'creator:id,name',
            'activities.creator:id,name',
        ]);

        return view('crm.opportunities.show', compact('opportunity'));
    }

    public function edit(Opportunity $opportunity): View
    {
        return view('crm.opportunities.edit', [
            'opportunity' => $opportunity,
            'stages' => OpportunityStage::ordered(),
            'customers' => Customer::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'users' => User::where('is_active', true)->get(['id', 'name']),
        ]);
    }

    public function update(UpdateOpportunityRequest $request, Opportunity $opportunity): RedirectResponse
    {
        $data = $request->validated();

        $old = $opportunity->only(array_keys($data));
        $opportunity->update($data);

        ActivityController::autoLog('note', "تحديث فرصة {$opportunity->title}", $opportunity, $data);
        AuditLogger::log('updated', $opportunity, $old, $opportunity->fresh()->only(array_keys($data)));

        return redirect()->route('opportunities.show', $opportunity)->with('success', 'تم تحديث الفرصة بنجاح');
    }

    public function updateStage(UpdateOpportunityStageRequest $request, Opportunity $opportunity): RedirectResponse
    {
        $data = $request->validated();

        $old = $opportunity->stage_id;
        $opportunity->update(['stage_id' => $data['stage_id']]);

        if ($opportunity->fresh()->isClosed()) {
            $opportunity->update(['closed_at' => now()]);
        } else {
            $opportunity->update(['closed_at' => null]);
        }

        $newStage = OpportunityStage::find($data['stage_id']);

        ActivityController::autoLog('note', "نقل {$opportunity->title} إلى مرحلة {$newStage->name}", $opportunity, [
            'stage' => $newStage->name,
        ]);
        AuditLogger::log('stage_changed', $opportunity, ['stage_id' => $old], ['stage_id' => $data['stage_id']]);

        return back()->with('success', 'تم نقل الفرصة إلى مرحلة جديدة');
    }

    public function destroy(Opportunity $opportunity): RedirectResponse
    {
        $opportunity->activities()->delete();
        AuditLogger::log('deleted', $opportunity, $opportunity->toArray(), null);
        $opportunity->delete();

        return redirect()->route('opportunities.index')->with('success', 'تم حذف الفرصة');
    }
}
