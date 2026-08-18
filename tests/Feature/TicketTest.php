<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\CRM\Customer;
use App\Models\CRM\Ticket;
use App\Models\CRM\TicketMessage;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CrmDemoDataSeeder;
use Database\Seeders\OpportunityStagesSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTest extends TestCase
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

    private function salesRep(): User
    {
        return User::where('email', 'rep@crm.test')->firstOrFail();
    }

    private function support(): User
    {
        return User::where('email', 'support@crm.test')->firstOrFail();
    }

    private function makeCustomer(): Customer
    {
        return Customer::create(['name' => 'عميل دعم', 'phone' => '0599'.random_int(100000, 999999), 'status' => 'active', 'created_by' => $this->support()->id]);
    }

    private function makeTicket(string $status = TicketStatus::Open->value, ?int $assignedTo = null, ?int $createdBy = null): Ticket
    {
        $customer = $this->makeCustomer();

        return Ticket::create([
            'ticket_number' => $this->ticketNumber(),
            'customer_id' => $customer->id,
            'subject' => 'مشكلة في النظام',
            'message' => 'تفاصيل المشكلة',
            'category' => 'فني',
            'priority' => 'medium',
            'status' => $status,
            'assigned_to' => $assignedTo,
            'created_by' => $createdBy ?? $this->support()->id,
            'resolved_at' => in_array($status, [TicketStatus::Resolved->value, TicketStatus::Closed->value], true) ? now() : null,
        ]);
    }

    private function ticketNumber(): string
    {
        $max = Ticket::withTrashed()->max('id') ?? 0;

        return 'TK-'.str_pad((string) ($max + 1), 6, '0', STR_PAD_LEFT);
    }

    private function limitedUser(string $email): User
    {
        $role = Role::updateOrCreate(['slug' => 'limited-agent'], [
            'name' => 'أخصائي محدود',
            'is_system' => false,
        ]);
        $role->permissions()->sync(
            Permission::whereIn('slug', ['view_tickets', 'reply_tickets'])->pluck('id')
        );

        return User::updateOrCreate(['email' => $email], [
            'name' => 'أخصائي محدود',
            'role_id' => $role->id,
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    private function viewerUser(string $email): User
    {
        $role = Role::updateOrCreate(['slug' => 'ticket-viewer'], [
            'name' => 'متابع تذاكر',
            'is_system' => false,
        ]);
        $role->permissions()->sync(
            Permission::where('slug', 'view_tickets')->value('id')
        );

        return User::updateOrCreate(['email' => $email], [
            'name' => 'متابع تذاكر',
            'role_id' => $role->id,
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    public function test_support_can_create_ticket(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($this->support())
            ->post(route('tickets.store'), [
                'customer_id' => $customer->id,
                'subject' => 'مشكلة في تسجيل الدخول',
                'message' => 'لا أستطيع الدخول إلى الحساب',
                'category' => 'فني',
                'priority' => 'high',
            ])
            ->assertRedirect();

        $ticket = Ticket::firstOrFail();

        $this->assertSame('TK-000001', $ticket->ticket_number);
        $this->assertSame(TicketStatus::Open->value, $ticket->status);
    }

    public function test_ticket_requires_subject_and_message(): void
    {
        $this->actingAs($this->support())
            ->post(route('tickets.store'), ['customer_id' => null])
            ->assertSessionHasErrors(['subject', 'message']);
    }

    public function test_support_can_reply_and_status_becomes_in_progress(): void
    {
        $ticket = $this->makeTicket();

        $this->actingAs($this->support())
            ->post(route('tickets.reply', $ticket), ['body' => 'جارٍ المتابعة الآن'])
            ->assertRedirect();

        $this->assertSame(TicketStatus::InProgress->value, $ticket->fresh()->status);
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'body' => 'جارٍ المتابعة الآن',
            'is_internal' => false,
        ]);
    }

    public function test_reply_reopens_closed_ticket(): void
    {
        $ticket = $this->makeTicket(TicketStatus::Closed->value);

        $this->actingAs($this->support())
            ->post(route('tickets.reply', $ticket), ['body' => 'أعد فتح هذه التذكرة'])
            ->assertRedirect();

        $ticket->refresh();

        $this->assertSame(TicketStatus::InProgress->value, $ticket->status);
        $this->assertNull($ticket->resolved_at);
    }

    public function test_assign_ticket_sets_in_progress(): void
    {
        $ticket = $this->makeTicket();

        $this->actingAs($this->support())
            ->post(route('tickets.assign', $ticket), ['assigned_to' => $this->support()->id])
            ->assertRedirect();

        $ticket->refresh();

        $this->assertSame($this->support()->id, $ticket->assigned_to);
        $this->assertSame(TicketStatus::InProgress->value, $ticket->status);
    }

    public function test_support_can_resolve_ticket(): void
    {
        $ticket = $this->makeTicket();

        $this->actingAs($this->support())
            ->post(route('tickets.status', $ticket), ['status' => TicketStatus::Resolved->value])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(TicketStatus::Resolved->value, $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->resolved_at);
    }

    public function test_user_without_resolve_permission_cannot_resolve(): void
    {
        $ticket = $this->makeTicket();
        $user = $this->limitedUser('limited@crm.test');

        $this->actingAs($user)
            ->post(route('tickets.status', $ticket), ['status' => TicketStatus::Resolved->value])
            ->assertSessionHas('error');

        $this->assertNotSame(TicketStatus::Resolved->value, $ticket->fresh()->status);
    }

    public function test_delete_ticket_from_open(): void
    {
        $ticket = $this->makeTicket();

        $this->actingAs($this->support())
            ->delete(route('tickets.destroy', $ticket))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSoftDeleted('tickets', ['id' => $ticket->id]);
    }

    public function test_delete_closed_ticket_denied(): void
    {
        $ticket = $this->makeTicket(TicketStatus::Closed->value);

        $this->actingAs($this->support())
            ->delete(route('tickets.destroy', $ticket))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'deleted_at' => null]);
    }

    public function test_sales_rep_cannot_access_tickets(): void
    {
        $this->actingAs($this->salesRep())
            ->get(route('tickets.index'))
            ->assertForbidden();
    }

    public function test_creating_ticket_notifies_viewers(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($this->support())
            ->post(route('tickets.store'), [
                'customer_id' => $customer->id,
                'subject' => 'إشعار إنشاء',
                'message' => 'يجب إبلاغ الفريق',
            ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->admin()->id,
            'type' => 'ticket_created',
        ]);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->support()->id,
        ]);

        $notification = Notification::where('user_id', $this->admin()->id)->firstOrFail();
        $this->assertNull($notification->read_at);
    }

    public function test_reply_notifies_creator_and_assignee(): void
    {
        $ticket = $this->makeTicket(
            status: TicketStatus::Open->value,
            assignedTo: $this->support()->id,
            createdBy: $this->admin()->id,
        );

        $this->actingAs($this->support())
            ->post(route('tickets.reply', $ticket), ['body' => 'رد جديد، تم المتابعة'])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->admin()->id,
            'type' => 'ticket_replied',
        ]);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->support()->id,
        ]);
    }

    public function test_crm_demo_seeder_creates_support_data(): void
    {
        $this->seed(OpportunityStagesSeeder::class);
        $this->seed(CrmDemoDataSeeder::class);

        $this->assertSame(8, Ticket::count());

        $reopened = Ticket::where('status', TicketStatus::Open->value)->first();
        $this->assertNotNull($reopened);
    }

    public function test_internal_messages_hidden_from_users_without_reply_permission(): void
    {
        $ticket = $this->makeTicket();

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $this->support()->id,
            'body' => 'ملاحظة داخلية سرية',
            'is_internal' => true,
        ]);

        $this->actingAs($this->admin())->get(route('tickets.show', $ticket))->assertSee('ملاحظة داخلية سرية');
        $this->actingAs($this->viewerUser('viewer@crm.test'))->get(route('tickets.show', $ticket))->assertOk()->assertDontSee('ملاحظة داخلية سرية');
    }
}