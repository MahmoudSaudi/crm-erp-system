<?php

namespace App\Http\Controllers\CRM;

use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\StoreActivityRequest;
use App\Http\Requests\CRM\UpdateActivityRequest;
use App\Models\CRM\Activity;
use App\Models\CRM\Customer;
use App\Models\CRM\Lead;
use App\Models\CRM\Opportunity;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $activities = Activity::query()
            ->with(['creator:id,name', 'assignedTo:id,name'])
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->get('type')))
            ->when($request->get('scope') === 'pending', fn ($q) => $q->pending())
            ->when($request->get('scope') === 'completed', fn ($q) => $q->completed())
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('crm.activities.index', [
            'activities' => $activities,
            'types' => ActivityType::list(),
            'activeType' => $request->get('type'),
            'activeScope' => $request->get('scope', 'all'),
        ]);
    }

    public function create(): View
    {
        return view('crm.activities.create', [
            'types' => ActivityType::list(),
            'leads' => Lead::select('id', 'first_name', 'last_name')->get(),
            'customers' => Customer::select('id', 'name')->get(),
            'opportunities' => Opportunity::select('id', 'title')->get(),
        ]);
    }

    public function store(StoreActivityRequest $request): RedirectResponse
    {
        $activity = Activity::create($request->validated() + ['created_by' => auth()->id()]);

        AuditLogger::log('created', $activity, null, $activity->toArray());

        return redirect()->route('activities.index')->with('success', 'تم إضافة النشاط بنجاح');
    }

    public function edit(Activity $activity): View
    {
        return view('crm.activities.edit', [
            'activity' => $activity,
            'types' => ActivityType::list(),
            'leads' => Lead::select('id', 'first_name', 'last_name')->get(),
            'customers' => Customer::select('id', 'name')->get(),
            'opportunities' => Opportunity::select('id', 'title')->get(),
        ]);
    }

    public function update(UpdateActivityRequest $request, Activity $activity): RedirectResponse
    {
        $activity->update($request->validated() + ['created_by' => auth()->id()]);

        AuditLogger::log('updated', $activity, null, $activity->fresh()->toArray());

        return redirect()->route('activities.index')->with('success', 'تم تحديث النشاط بنجاح');
    }

    public function destroy(Activity $activity): RedirectResponse
    {
        AuditLogger::log('deleted', $activity, $activity->toArray(), null);
        $activity->delete();

        return redirect()->route('activities.index')->with('success', 'تم حذف النشاط');
    }

    public function complete(Activity $activity): RedirectResponse
    {
        $activity->update(['completed_at' => now()]);

        AuditLogger::log('completed', $activity, ['completed_at' => null], ['completed_at' => $activity->completed_at]);

        return back()->with('success', 'تم إكمال النشاط');
    }

    /**
     * Auto-log an activity whenever an entity changes (shared with other controllers).
     */
    public static function autoLog(string $type, string $subject, Model $model, ?array $details = null): void
    {
        $valid = ActivityType::tryFrom($type);
        Activity::create([
            'type' => $valid?->value ?? ActivityType::Note->value,
            'subject' => $subject,
            'description' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            'related_type' => $model::class,
            'related_id' => $model->getKey(),
            'created_by' => auth()->id(),
        ]);
    }
}
