<?php

namespace App\Services;

use App\Models\CRM\Ticket;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Str;

class NotificationService
{
    public function send(array $userIds, string $type, string $title, ?string $body = null, ?string $link = null): void
    {
        foreach (array_unique(array_filter($userIds)) as $userId) {
            Notification::create([
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'link' => $link,
                'read_at' => null,
            ]);
        }
    }

    public function notifyTicketCreated(Ticket $ticket, ?User $actor = null): void
    {
        $actor ??= auth()->user();

        $userIds = User::where('is_active', true)
            ->whereHas('role.permissions', fn ($q) => $q->where('slug', 'view_tickets'))
            ->where('id', '!=', $actor?->id ?? 0)
            ->pluck('id')
            ->all();

        $this->send($userIds, 'ticket_created', 'تذكرة جديدة: '.$ticket->subject, $ticket->message, route('tickets.show', $ticket));
    }

    public function notifyTicketReplied(Ticket $ticket, int $messageUserId): void
    {
        $userIds = collect([$ticket->created_by, $ticket->assigned_to])
            ->push($messageUserId)
            ->reject(fn ($id) => ! $id || (int) $id === $messageUserId)
            ->unique()
            ->all();

        $this->send($userIds, 'ticket_replied', 'رد جديد على '.$ticket->ticket_number, $ticket->subject, route('tickets.show', $ticket));
    }
}
