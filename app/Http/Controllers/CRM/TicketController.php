<?php

namespace App\Http\Controllers\CRM;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\AssignTicketRequest;
use App\Http\Requests\CRM\StoreTicketRequest;
use App\Http\Requests\CRM\TicketReplyRequest;
use App\Models\CRM\Customer;
use App\Models\CRM\Ticket;
use App\Models\User;
use App\Services\CRM\TicketService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class TicketController extends Controller
{
    public function __construct(
        private readonly TicketService $ticketService,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function index(Request $request): View
    {
        $tickets = Ticket::query()
            ->with(['customer:id,name', 'assignee:id,name', 'creator:id,name'])
            ->search($request->get('search'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->get('priority')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('crm.tickets.index', [
            'tickets' => $tickets,
            'statuses' => TicketStatus::list(),
            'priorities' => TicketPriority::list(),
            'activeStatus' => $request->get('status'),
            'activePriority' => $request->get('priority'),
        ]);
    }

    public function create(): View
    {
        return view('crm.tickets.create', [
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'categories' => ['فني', 'محاسبي', 'مبيعات', 'شحن', 'أخرى'],
            'priorities' => TicketPriority::list(),
        ]);
    }

    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $ticket = $this->ticketService->store($request->validated());

        $this->notificationService->notifyTicketCreated($ticket);

        return redirect()->route('tickets.show', $ticket)->with('success', 'تم إنشاء التذكرة بنجاح');
    }

    public function show(Ticket $ticket): View
    {
        $canSeeInternal = auth()->user()->hasPermission('reply_tickets');

        $ticket->load([
            'customer:id,name,phone',
            'assignee:id,name',
            'creator:id,name',
            'messages' => fn ($q) => $canSeeInternal ? $q : $q->public(),
            'messages.user:id,name',
        ]);

        return view('crm.tickets.show', [
            'ticket' => $ticket,
            'agents' => $this->agents(),
            'statuses' => TicketStatus::list(),
        ]);
    }

    public function reply(TicketReplyRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->ticketService->reply($ticket, $request->validated());

        $this->notificationService->notifyTicketReplied($ticket, auth()->id());

        return back()->with('success', 'تم إرسال الرد');
    }

    public function assign(AssignTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        try {
            $this->ticketService->assign($ticket, $request->validated('assigned_to'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم تحديث التعيين');
    }

    public function status(Request $request, Ticket $ticket): RedirectResponse
    {
        try {
            $this->ticketService->updateStatus($ticket, (string) $request->get('status'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم تحديث حالة التذكرة');
    }

    public function destroy(Ticket $ticket): RedirectResponse
    {
        try {
            $this->ticketService->destroy($ticket);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('tickets.index')->with('success', 'تم حذف التذكرة');
    }

    private function agents(): array
    {
        return User::where('is_active', true)
            ->whereHas('role.permissions', fn ($q) => $q->where('slug', 'view_tickets'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();
    }
}