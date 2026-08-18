<?php

namespace Tests\Feature;

use App\Models\CRM\Customer;
use App\Models\CRM\Ticket;
use App\Models\Notification;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@crm.test')->firstOrFail();
    }

    private function support(): User
    {
        return User::where('email', 'support@crm.test')->firstOrFail();
    }

    private function makeTicket(): Ticket
    {
        $customer = Customer::create(['name' => 'عميل إشعار', 'phone' => '0599'.random_int(100000, 999999), 'status' => 'active', 'created_by' => $this->admin()->id]);

        return Ticket::create([
            'ticket_number' => 'TK-'.str_pad((string) (Ticket::withTrashed()->max('id') + 1), 6, '0', STR_PAD_LEFT),
            'customer_id' => $customer->id,
            'subject' => 'موضوع الإشعار',
            'message' => 'تفاصيل الإشعار',
            'priority' => 'medium',
            'status' => 'open',
            'created_by' => $this->admin()->id,
        ]);
    }

    private function makeNotification(User $user, bool $read = false): Notification
    {
        return Notification::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'type' => 'test',
            'title' => 'إشعار تجريبي',
            'body' => null,
            'link' => route('tickets.index'),
            'read_at' => $read ? now() : null,
        ]);
    }

    public function test_admin_can_view_notifications_page(): void
    {
        $this->actingAs($this->admin())
            ->get(route('notifications.index'))
            ->assertOk();
    }

    public function test_creating_ticket_notifies_admin(): void
    {
        $this->actingAs($this->support());

        $ticket = $this->makeTicket();

        (new \App\Services\NotificationService)->notifyTicketCreated($ticket, $this->support());

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->admin()->id,
            'type' => 'ticket_created',
            'title' => 'تذكرة جديدة: '.$ticket->subject,
        ]);
    }

    public function test_mark_read_sets_read_at(): void
    {
        $notification = $this->makeNotification($this->admin());

        $this->actingAs($this->admin())
            ->post(route('notifications.read', $notification))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_read_denied_for_other_user(): void
    {
        $notification = $this->makeNotification($this->admin());

        $this->actingAs($this->support())
            ->post(route('notifications.read', $notification))
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read(): void
    {
        $this->makeNotification($this->admin());
        $this->makeNotification($this->admin());

        $this->actingAs($this->admin())
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, Notification::where('user_id', $this->admin()->id)->whereNull('read_at')->count());
    }
}