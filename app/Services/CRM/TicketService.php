<?php

namespace App\Services\CRM;

use App\Enums\TicketStatus;
use App\Models\CRM\Ticket;
use App\Models\CRM\TicketMessage;
use App\Models\User;
use App\Services\AuditLogger;
use RuntimeException;

class TicketService
{
    public function store(array $data, ?User $actor = null): Ticket
    {
        $actor ??= auth()->user();
        $ticket = Ticket::create($data + [
            'ticket_number' => $this->nextNumber(),
            'status' => TicketStatus::Open->value,
            'priority' => $data['priority'] ?? 'medium',
            'created_by' => $actor?->id,
        ]);

        AuditLogger::log('created', $ticket, null, $ticket->fresh()->toArray());

        return $ticket->fresh();
    }

    public function reply(Ticket $ticket, array $data, ?User $actor = null): TicketMessage
    {
        $actor ??= auth()->user();

        if ($ticket->status === TicketStatus::Closed->value) {
            $this->reopen($ticket, $actor);
        }

        $message = $ticket->messages()->create([
            'user_id' => $actor?->id,
            'body' => $data['body'],
            'is_internal' => $data['is_internal'] ?? false,
        ]);

        if ($ticket->status === TicketStatus::Open->value) {
            $ticket->update(['status' => TicketStatus::InProgress->value]);
        }

        AuditLogger::log('message_added', $message, null, $message->toArray());

        return $message;
    }

    public function assign(Ticket $ticket, ?int $userId, ?User $actor = null): Ticket
    {
        $actor ??= auth()->user();

        $old = $ticket->only(['assigned_to', 'status']);
        $ticket->update([
            'assigned_to' => $userId,
            'status' => $userId ? ($ticket->status === TicketStatus::Open->value ? TicketStatus::InProgress->value : $ticket->status) : $ticket->status,
        ]);

        AuditLogger::log('assigned', $ticket, $old, $ticket->fresh()->only(['assigned_to', 'status']));

        return $ticket->fresh();
    }

    public function updateStatus(Ticket $ticket, string $status, ?User $actor = null): Ticket
    {
        $actor ??= auth()->user();

        $next = TicketStatus::tryFrom($status);
        if (! $next) {
            throw new RuntimeException('حالة غير صالحة');
        }

        if ($next === TicketStatus::Closed && ! $actor?->hasPermission('resolve_tickets')) {
            throw new RuntimeException('ليس لديك صلاحية غلق التذاكر');
        }

        if ($next === TicketStatus::Resolved && ! $actor?->hasPermission('resolve_tickets')) {
            throw new RuntimeException('ليس لديك صلاحية حل التذاكر');
        }

        if (in_array($next, [TicketStatus::Open, TicketStatus::InProgress], true)
            && ! $actor?->hasPermission('reply_tickets')) {
            throw new RuntimeException('ليس لديك صلاحية تغيير حالة التذكرة');
        }

        $old = $ticket->only('status');
        $ticket->update([
            'status' => $status,
            'resolved_at' => $next === TicketStatus::Resolved ? now() : ($next === TicketStatus::Open ? null : $ticket->resolved_at),
        ]);

        AuditLogger::log('status_changed', $ticket, $old, $ticket->fresh()->only(['status', 'resolved_at']));

        return $ticket->fresh();
    }

    public function destroy(Ticket $ticket, ?User $actor = null): void
    {
        $actor ??= auth()->user();

        if ($ticket->status === TicketStatus::Closed->value) {
            throw new RuntimeException('لا يمكن حذف تذكرة مغلقة');
        }

        AuditLogger::log('deleted', $ticket, $ticket->toArray(), null);
        $ticket->delete();
    }

    private function reopen(Ticket $ticket, User $actor): void
    {
        $old = $ticket->only(['status', 'resolved_at']);
        $ticket->update([
            'status' => TicketStatus::InProgress->value,
            'resolved_at' => null,
        ]);

        AuditLogger::log('reopened', $ticket, $old, $ticket->fresh()->only(['status', 'resolved_at']));
    }

    public function nextNumber(): string
    {
        $max = Ticket::withTrashed()->max('id') ?? 0;

        return 'TK-'.str_pad((string) ($max + 1), 6, '0', STR_PAD_LEFT);
    }
}
