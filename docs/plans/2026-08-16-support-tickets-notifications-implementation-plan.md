# Phase 5: الدعم (Tickets + Notifications) — خطة التنفيذ

> **For agentic workers:** تنفَّذ بحسب المهام بالترتيب مع checkpoint مراجعة. Steps بصيغة checkbox.

**Goal:** بناء تذاكر الدعم الكاملة (حالات/أولوية/تصنيف/تعيين/رسائل داخلية وخارجية/حذف ناعم) + إشعارات داخلية في جدول `notifications` المخصص + ربط بيل الإشعارات في الـ topbar.

**Architecture:** خادم Laravel 11 Monolith (Blade/Tailwind/Alpine). منطق الانتقالات والإشعارات في `TicketService` (سيل واحد عبر `mustBe` شروط) و `NotificationService` (كتابة في جدول `notifications` المخصص بـ `user_id`). RBAC عبر `RbacSeeder` (تُضاف `assign_tickets` + `delete_tickets`). الموديل القديم `CRM\Ticket` **يُعاد بناؤه** ليطابق الجدول (كان يشير لأعمدة `description`/`department` غير الموجودة).

**Tech Stack:** Laravel 11 · Blade · Alpine.js · Tailwind · MySQL (بدون حزم جديدة).

---

## Global Constraints

- لا توجد مكتبات/حزم جديدة — فقط ما هو موجود في `composer.json`.
- جميع أسماء الملفات والنصوص بالعربية RTL؛ الرسائل ثنائية (أجنبي/عربي رخيم كالمعتاد).
- ترقيم التذاكر تسلسلي `TK-000001` عبر `withTrashed()->max('id')` (نمط `PO-` في Phase 4).
- الجدول `notifications` **مخصص** (أعمدة: `id uuid, user_id, type, title, body, link, read_at`) — لا نستخدم `Illuminate\Notifications\DatabaseNotification` بل نموذج خاص `App\Models\Notification` مربوط بـ `user_id` فقط.
- كل العمليات المحظورة تعود بـ `RuntimeException` → `back()->with('error')` في الـ controller (نمط Phase 4).
- الحالات: `open → in_progress → resolved → closed` + إعادة فتح عبر الرد (`reopen` عند `status in (closed, resolved)`).
- Form Requests جديدة للـ store/reply/assign (نمط Gap 1) — **لا validation inline** في الـ controllers.
- دالة `can($this->user())` في الخدمة تتحقق من `hasPermission` لأفعال معينة (close/reply).

---

## Task 1: إعادة بناء Models + Enums

**Files:**
- Modify: `app/Models/CRM/Ticket.php` (إعادة بناء كاملة لتطابق الجدول)
- Create: `app/Models/CRM/TicketMessage.php`
- Create: `app/Models/Notification.php`
- Create: `app/Enums/TicketStatus.php`
- Create: `app/Enums/TicketPriority.php`

**Interfaces:**
- Produces:
  - `Ticket` (fillable يطابق الجدول: `ticket_number, customer_id, subject, message, category, priority, status, assigned_to, created_by, resolved_at`) + `SoftDeletes` + `messages()/customer()/assignee()/creator()` + `statusLabel()/priorityLabel()` + `scopeSearch` + `scopeStatus`.
  - `TicketMessage` (fillable `ticket_id, user_id, body, is_internal`) + `user()` + `scopePublic` (إخفاء الداخلي للمستخدمين دون `reply_tickets`).
  - `Notification` (fillable `user_id, type, title, body, link, read_at` + cast `read_at`) + `user()` + `scopeUnread`.
  - `TicketStatus` (Open/InProgress/Resolved/Closed + label/color/list).
  - `TicketPriority` (Low/Medium/High + label/color/list).

**ملاحظة:** موديل `Ticket` القديم يشير إلى `description` و `department` بلا `SoftDeletes` — **يُستبدل كليًا** بالمحتوى أدناه.

- [ ] **Step 1: إعادة بناء `app/Models/CRM/Ticket.php`**

```php
<?php

namespace App\Models\CRM;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_number',
        'customer_id',
        'subject',
        'message',
        'category',
        'priority',
        'status',
        'assigned_to',
        'created_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages()
    {
        return $this->hasMany(TicketMessage::class);
    }

    public function statusLabel(): string
    {
        return \App\Enums\TicketStatus::from($this->status)?->label() ?? $this->status;
    }

    public function priorityLabel(): string
    {
        return \App\Enums\TicketPriority::from($this->priority)?->label() ?? $this->priority;
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('ticket_number', 'like', "%{$term}%")
                ->orWhere('subject', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%"));
        });
    }
}
```

- [ ] **Step 2: إنشاء `app/Models/CRM/TicketMessage.php`**

```php
<?php

namespace App\Models\CRM;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'body',
        'is_internal',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopePublic($query)
    {
        return $query->where('is_internal', false);
    }
}
```

- [ ] **Step 3: إنشاء `app/Models/Notification.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'type',
        'title',
        'body',
        'link',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
}
```

- [ ] **Step 4: إنشاء `app/Enums/TicketStatus.php`**

```php
<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'مفتوحة',
            self::InProgress => 'قيد المعالجة',
            self::Resolved => 'تم الحل',
            self::Closed => 'مغلقة',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
            self::InProgress => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
            self::Resolved => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
            self::Closed => 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
        };
    }

    public static function list(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
```

- [ ] **Step 5: إنشاء `app/Enums/TicketPriority.php`**

```php
<?php

namespace App\Enums;

enum TicketPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'منخفضة',
            self::Medium => 'متوسطة',
            self::High => 'عالية',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
            self::Medium => 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
            self::High => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
        };
    }

    public static function list(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
```

- [ ] **Step 6: فحص صياغة**
  - Run: `php -l app/Models/CRM/Ticket.php`, `php -l app/Models/CRM/TicketMessage.php`, `php -l app/Models/Notification.php`, `php -l app/Enums/TicketStatus.php`, `php -l app/Enums/TicketPriority.php`
  - Expected: "No syntax errors" في كل ملف.

---

## Task 2: TicketService + NotificationService

**Files:**
- Create: `app/Services/CRM/TicketService.php`
- Create: `app/Services/NotificationService.php`

**Interfaces:**
- Consumes: `Ticket`/`TicketMessage`/`TicketStatus`/`TicketPriority` (Task 1) · `AuditLogger`.
- Produces:
  - `TicketService::store(array $data, User $creator): Ticket` — ينشئ الرقم `TK-000000` ويكتب التذكرة.
  - `TicketService::reply(Ticket $ticket, array $data, User $actor): TicketMessage` — يكتب رسالة، ويعيد فتح التذكرة المغلقة/المحلولة، ويحوّل `open → in_progress` (إذا كانت open) عند رسالة الرد.
  - `TicketService::assign(Ticket $ticket, ?int $userId, User $actor): Ticket`.
  - `TicketService::updateStatus(Ticket $ticket, string $status, User $actor): Ticket` — بـ `can()` قواعد: close تحتاج `resolve_tickets`، reply تحتاج `reply_tickets`.
  - `TicketService::destroy(Ticket $ticket, User $actor): void` — الحذف الناعم، يمنع إن كانت `closed`.
  - `TicketService::nextNumber(): string`.
  - `NotificationService::send(array $userIds, string $type, string $title, ?string $body, ?string $link): void`.
  - `NotificationService::notifyTicketCreated(Ticket $ticket, User $actor): void` — لكل المستخدمين النشطين بصلاحية `view_tickets` باستثناء `$actor`.
  - `NotificationService::notifyTicketReplied(Ticket $ticket, int $messageUserId): void` — لمنشئ التذكرة + `assigned_to` (باستثناء كاتب الرسالة).

- [ ] **Step 1: إنشاء `app/Services/CRM/TicketService.php`**

```php
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

        if ($next === TicketStatus::Closed->value && ! $actor?->hasPermission('resolve_tickets')) {
            throw new RuntimeException('ليس لديك صلاحية غلق التذاكر');
        }

        if ($next === TicketStatus::Resolved->value && ! $actor?->hasPermission('resolve_tickets')) {
            throw new RuntimeException('ليس لديك صلاحية حل التذاكر');
        }

        if (in_array($next, [TicketStatus::Open->value, TicketStatus::InProgress->value], true)
            && ! $actor?->hasPermission('reply_tickets')) {
            throw new RuntimeException('ليس لديك صلاحية تغيير حالة التذكرة');
        }

        $old = $ticket->only('status');
        $ticket->update([
            'status' => $status,
            'resolved_at' => $next === TicketStatus::Resolved->value ? now() : ($next === TicketStatus::Open->value ? null : $ticket->resolved_at),
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
```

- [ ] **Step 2: إنشاء `app/Services/NotificationService.php`**

```php
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
```

- [ ] **Step 3: فحص صياغة**
  - Run: `php -l app/Services/CRM/TicketService.php`, `php -l app/Services/NotificationService.php`
  - Expected: "No syntax errors".

---

## Task 3: Form Requests

**Files:**
- Create: `app/Http/Requests/CRM/StoreTicketRequest.php`
- Create: `app/Http/Requests/CRM/TicketReplyRequest.php`
- Create: `app/Http/Requests/CRM/AssignTicketRequest.php`

**Interfaces:**
- Consumes: قواعد الجدول (Task 1).
- Produces: طلبات مُحقَّقة تُستهلك في `TicketController` (Task 4).

- [ ] **Step 1: `StoreTicketRequest.php`**

```php
<?php

namespace App\Http\Requests\CRM;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'exists:customers,id'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', 'in:low,medium,high'],
        ];
    }
}
```

- [ ] **Step 2: `TicketReplyRequest.php`**

```php
<?php

namespace App\Http\Requests\CRM;

use Illuminate\Foundation\Http\FormRequest;

class TicketReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string'],
            'is_internal' => ['nullable', 'boolean'],
        ];
    }
}
```

- [ ] **Step 3: `AssignTicketRequest.php`**

```php
<?php

namespace App\Http\Requests\CRM;

use Illuminate\Foundation\Http\FormRequest;

class AssignTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assigned_to' => ['nullable', 'exists:users,id'],
        ];
    }
}
```

- [ ] **Step 4: فحص صياغة**
  - Run: `php -l app/Http/Requests/CRM/StoreTicketRequest.php` وغيرها.
  - Expected: "No syntax errors".

---

## Task 4: TicketController

**Files:**
- Create: `app/Http/Controllers/CRM/TicketController.php`

**Interfaces:**
- Consumes: `TicketService` (Task 2) · `NotificationService` (Task 2) · Form Requests (Task 3) · `TicketStatus`/`TicketPriority` (Task 1).
- Produces: `index/create/store/show/reply/assign/status/destroy` — للـ Routes (Task 5) و Views (Task 6) و Tests (Task 9).

- [ ] **Step 1: إنشاء الملف**

```php
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
        $ticket->load([
            'customer:id,name,phone',
            'assignee:id,name',
            'creator:id,name',
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
```

- [ ] **Step 2: فحص صياغة**
  - Run: `php -l app/Http/Controllers/CRM/TicketController.php`
  - Expected: "No syntax errors".

---

## Task 5: Routes + RBAC + Permission

**Files:**
- Modify: `routes/web.php` (استبدال Placeholder لـ `/tickets` + إضافة إشعارات)
- Modify: `database/seeders/RbacSeeder.php` (صلاحيتا `assign_tickets` + `delete_tickets`)

**Interfaces:**
- Consumes: Controllers (Task 4).
- Produces: routes `tickets.*` + `notifications.*` · صلاحيات جديدة.

- [ ] **Step 1: تعديل `routes/web.php`**
  - أضف import: `use App\Http\Controllers\CRM\TicketController;` و `use App\Http\Controllers\NotificationController;` (يُبنى في Task 7).
  - استبدل سطر الـ Placeholder:
    ```php
    Route::get('/tickets', [PlaceholderController::class, 'show'])->defaults('module', 'tickets')->name('tickets.index')->middleware('permission:view_tickets');
    ```
    بهذا:
    ```php
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index')->middleware('permission:view_tickets');
    Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create')->middleware('permission:create_tickets');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store')->middleware('permission:create_tickets');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show')->middleware('permission:view_tickets');
    Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('tickets.reply')->middleware('permission:reply_tickets');
    Route::post('/tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign')->middleware('permission:assign_tickets');
    Route::post('/tickets/{ticket}/status', [TicketController::class, 'status'])->name('tickets.status')->middleware('permission:reply_tickets');
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])->name('tickets.destroy')->middleware('permission:delete_tickets');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    ```

- [ ] **Step 2: تعديل `database/seeders/RbacSeeder.php`**
  - في `permissions()` بعد `resolve_tickets` (سطر 222):
    ```php
    ['name' => 'تعيين تذكرة', 'slug' => 'assign_tickets', 'group' => 'tickets'],
    ['name' => 'حذف تذكرة', 'slug' => 'delete_tickets', 'group' => 'tickets'],
    ```
  - في `roles()` تحت `support-agent` (سطر 124) حدّث قائمة tickets:
    ```php
    'tickets' => ['view_tickets', 'create_tickets', 'reply_tickets', 'resolve_tickets', 'assign_tickets', 'delete_tickets'],
    ```

- [ ] **Step 3: تشغيل الـ seeder**
  - Run: `php artisan db:seed --class=RbacSeeder`
  - Expected: الصلاحيتان الجديدتان موجودتان و`cancel` غير متأثرة.

- [ ] **Step 4: التحقق من routes**
  - Run: `php artisan route:list --name=tickets`
  - Expected: 8 routes باسم ‏tickets.*.

---

## Task 6: Ticket Views

**Files:**
- Create: `resources/views/crm/tickets/index.blade.php`
- Create: `resources/views/crm/tickets/create.blade.php`
- Create: `resources/views/crm/tickets/show.blade.php`

**Interfaces:**
- Consumes: `TicketController` (Task 4) · `TicketStatus::list/color` · `TicketPriority::list/color`.

- [ ] **Step 1: `index.blade.php`** (بحث + فلاتر الحالة/الأولوية + جدول)

```blade
<x-app-layout>
    <x-slot name="title">{{ __('تذاكر الدعم') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('تذاكر الدعم') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">متابعة طلبات الدعم وحلّها</p>
            </div>
            @can('permission.create_tickets')
                <a href="{{ route('tickets.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    {{ __('إنشاء تذكرة') }}
                </a>
            @endcan
        </div>

        <form method="GET" action="{{ route('tickets.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div>
                    <x-input-label for="search" value="بحث" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" value="{{ request('search') }}" placeholder="الرقم أو الموضوع أو العميل..." />
                </div>
                <div>
                    <x-input-label for="status" value="الحالة" />
                    <select id="status" name="status" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">كل الحالات</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($activeStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="priority" value="الأولوية" />
                    <select id="priority" name="priority" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">كل الأولويات</option>
                        @foreach ($priorities as $value => $label)
                            <option value="{{ $value }}" @selected($activePriority === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600">تصفية</button>
                    @if (request()->hasAny(['search', 'status', 'priority']))
                        <a href="{{ route('tickets.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">مسح</a>
                    @endif
                </div>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الرقم</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الموضوع</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">العميل</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الأولوية</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الحالة</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المسؤول</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($tickets as $ticket)
                            @php
                                $ticketStatus = \App\Enums\TicketStatus::from($ticket->status);
                                $ticketPriority = \App\Enums\TicketPriority::from($ticket->priority);
                            @endphp
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400" dir="ltr">{{ $ticket->ticket_number }}</a>
                                </td>
                                <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $ticket->subject }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $ticket->customer?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $ticketPriority?->color() }}">{{ $ticketPriority?->label() }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $ticketStatus?->color() }}">{{ $ticketStatus?->label() }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $ticket->assignee?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300" title="عرض">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm-9 0a9 9 0 0118 0 9 9 0 01-18 0z" /></svg>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-16 text-center text-sm text-slate-500 dark:text-slate-400">لا توجد تذاكر بعد</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($tickets->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">{{ $tickets->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 2: `create.blade.php`**

```blade
<x-app-layout>
    <x-slot name="title">{{ __('إنشاء تذكرة') }}</x-slot>

    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('إنشاء تذكرة دعم') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">سجّل طلب الدعم وأرفق التفاصيل</p>
        </div>

        <form method="POST" action="{{ route('tickets.store') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="customer_id" value="العميل (اختياري)" />
                    <select id="customer_id" name="customer_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">اختر العميل</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('customer_id')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="category" value="التصنيف" />
                    <select id="category" name="category" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">اختر التصنيف</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('category')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="priority" value="الأولوية" />
                    <select id="priority" name="priority" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        @foreach ($priorities as $value => $label)
                            <option value="{{ $value }}" @selected(old('priority', 'medium') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('priority')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="subject" value="الموضوع" />
                    <x-text-input id="subject" name="subject" type="text" class="mt-1 block w-full" value="{{ old('subject') }}" required />
                    <x-input-error :messages="$errors->get('subject')" class="mt-2" />
                </div>
            </div>

            <div class="mt-4">
                <x-input-label for="message" value="التفاصيل" />
                <textarea id="message" name="message" rows="4" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200" required>{{ old('message') }}</textarea>
                <x-input-error :messages="$errors->get('message')" class="mt-2" />
            </div>

            <div class="mt-6 flex items-center gap-3">
                <x-primary-button>{{ __('حفظ') }}</x-primary-button>
                <a href="{{ route('tickets.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">{{ __('إلغاء') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
```

- [ ] **Step 3: `show.blade.php`** (سلسلة رسائل + تعيين + تغيير حالة + حذف + نموذج رد داخلي/خارجي)

```blade
<x-app-layout>
    <x-slot name="title">{{ $ticket->ticket_number }} — {{ $ticket->subject }}</x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white"><span class="font-mono" dir="ltr">{{ $ticket->ticket_number }}</span></h2>
                    @php
                        $ticketStatus = \App\Enums\TicketStatus::from($ticket->status);
                        $ticketPriority = \App\Enums\TicketPriority::from($ticket->priority);
                    @endphp
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $ticketStatus?->color() }}">{{ $ticketStatus?->label() }}</span>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $ticketPriority?->color() }}">{{ $ticketPriority?->label() }}</span>
                </div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $ticket->category ?? 'بدون تصنيف' }} · أنشأها {{ $ticket->creator?->name ?? '—' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($ticket->status !== 'closed')
                    @can('permission.resolve_tickets')
                        <form method="POST" action="{{ route('tickets.status', $ticket) }}" onsubmit="return confirm('تحديد التذكرة كمحلولة؟')">
                            @csrf
                            <input type="hidden" name="status" value="resolved" />
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                حلّ التذكرة
                            </button>
                        </form>
                    @endcan
                @endif
                @if (in_array($ticket->status, ['resolved', 'closed'], true))
                    @can('permission.reply_tickets')
                        <form method="POST" action="{{ route('tickets.status', $ticket) }}" onsubmit="return confirm('إعادة فتح التذكرة؟')">
                            @csrf
                            <input type="hidden" name="status" value="open" />
                            <button type="submit" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">إعادة فتح</button>
                        </form>
                    @endcan
                @endif
                @can('permission.resolve_tickets')
                    @if ($ticket->status !== 'closed')
                        <form method="POST" action="{{ route('tickets.status', $ticket) }}" onsubmit="return confirm('غلق التذكرة؟')">
                            @csrf
                            <input type="hidden" name="status" value="closed" />
                            <button type="submit" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">غلق</button>
                        </form>
                    @endif
                @endcan
                @can('permission.delete_tickets')
                    @if ($ticket->status !== 'closed')
                        <form method="POST" action="{{ route('tickets.destroy', $ticket) }}" onsubmit="return confirm('حذف التذكرة؟')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center rounded-lg border border-rose-200 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">حذف</button>
                        </form>
                    @endif
                @endcan
            </div>
        </div>

        @if ($ticket->status === 'closed')
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800/40 dark:text-slate-300">هذه التذكرة مغلقة.</div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Thread --}}
            <div class="lg:col-span-2 space-y-4">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <div class="flex items-start gap-3">
                        <span class="flex items-center justify-center w-9 h-9 rounded-full bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ mb_substr($ticket->creator?->name ?? '?', 0, 1) }}</span>
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ $ticket->creator?->name ?? '—' }}</span>
                                <span class="text-xs text-slate-400">{{ $ticket->created_at->diffForHumans() }}</span>
                            </div>
                            <h3 class="mt-1 font-medium text-slate-900 dark:text-white">{{ $ticket->subject }}</h3>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ $ticket->message }}</p>
                        </div>
                    </div>
                </div>

                @foreach ($ticket->messages as $message)
                    <div class="rounded-xl border p-5 shadow-sm {{ $message->is_internal ? 'border-amber-200 bg-amber-50/50 dark:border-amber-500/20 dark:bg-amber-500/5' : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900' }}">
                        <div class="flex items-start gap-3">
                            <span class="flex items-center justify-center w-9 h-9 rounded-full bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ mb_substr($message->user?->name ?? '?', 0, 1) }}</span>
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ $message->user?->name }}</span>
                                    <span class="text-xs text-slate-400">{{ $message->created_at->diffForHumans() }}</span>
                                    @if ($message->is_internal)
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">ملاحظة داخلية</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ $message->body }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Sidebar --}}
            <div class="space-y-4">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">بيانات التذكرة</h3>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex items-center justify-between"><dt class="text-slate-500 dark:text-slate-400">العميل</dt><dd class="font-medium text-slate-900 dark:text-white">{{ $ticket->customer?->name ?? '—' }}</dd></div>
                        <div class="flex items-center justify-between"><dt class="text-slate-500 dark:text-slate-400">التصنيف</dt><dd class="font-medium text-slate-900 dark:text-white">{{ $ticket->category ?? '—' }}</dd></div>
                        @if ($ticket->resolved_at)
                            <div class="flex items-center justify-between"><dt class="text-slate-500 dark:text-slate-400">تاريخ الحل</dt><dd class="font-medium text-slate-900 dark:text-white">{{ $ticket->resolved_at->format('Y-m-d H:i') }}</dd></div>
                        @endif
                    </dl>
                </div>

                @can('permission.assign_tickets')
                    <form method="POST" action="{{ route('tickets.assign', $ticket) }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                        @csrf
                        <x-input-label for="assigned_to" value="تعيين إلى" />
                        <select id="assigned_to" name="assigned_to" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                            <option value="">غير معيّنة</option>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent['id'] }}" @selected($ticket->assigned_to == $agent['id'])>{{ $agent['name'] }}</option>
                            @endforeach
                        </select>
                        <x-primary-button class="mt-3">{{ __('حفظ التعيين') }}</x-primary-button>
                    </form>
                @endcan
            </div>
        </div>

        {{-- Reply --}}
        @if ($ticket->status !== 'closed')
            @can('permission.reply_tickets')
                <form method="POST" action="{{ route('tickets.reply', $ticket) }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    @csrf
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">الرد</h3>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <input type="checkbox" name="is_internal" value="1" class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500 dark:border-slate-600 dark:bg-slate-800" />
                            ملاحظة داخلية (لا تُرى للعميل)
                        </label>
                    </div>
                    <textarea name="body" rows="3" class="mt-4 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200" placeholder="اكتب ردًا أو ملاحظة..." required></textarea>
                    <x-input-error :messages="$errors->get('body')" class="mt-2" />
                    <div class="mt-4">
                        <x-primary-button>{{ __('إرسال') }}</x-primary-button>
                    </div>
                </form>
            @endcan
        @endif
    </div>
</x-app-layout>
```

- [ ] **Step 4: فحص**
  - تحقق يدوي بعد Task 9 (الاختبارات تغطي تقديم الصفحات).

---

## Task 7: NotificationController + Topbar

**Files:**
- Create: `app/Http/Controllers/NotificationController.php`
- Modify: `resources/views/layouts/partials/topbar.blade.php` (بيل إشعارات فعلي)
- Create: `resources/views/notifications/index.blade.php`

**Interfaces:**
- Consumes: `Notification` model (Task 1).
- Produces: `NotificationController@index/markRead/markAllRead` + بيل بعدّاد غير المقروء + صفحة إشعارات.

- [ ] **Step 1: إنشاء `app/Http/Controllers/NotificationController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->latest()
            ->paginate(20);

        return view('notifications.index', ['notifications' => $notifications]);
    }

    public function markRead(Notification $notification): RedirectResponse
    {
        if ($notification->user_id !== auth()->id()) {
            abort(403);
        }

        $notification->update(['read_at' => now()]);

        if ($notification->link) {
            return redirect($notification->link);
        }

        return back();
    }

    public function markAllRead(): RedirectResponse
    {
        Notification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'تم تعليم كل الإشعارات كمقروءة');
    }
}
```

- [ ] **Step 2: تعديل `topbar.blade.php`** — استبدال كتلة البيل الفارغة (الأسطر 34-46) بهذا:

```blade
            {{-- Notifications --}}
            <div x-data="notificationsDropdown()" class="relative">
                <button
                    type="button"
                    class="relative p-2 rounded-full text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                    @click="open = !open"
                    aria-label="الإشعارات"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <template x-if="unreadCount > 0">
                        <span class="absolute -top-1 -left-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1.5 text-[10px] font-bold text-white" x-text="unreadCount"></span>
                    </template>
                </button>

                <div
                    x-show="open"
                    x-transition
                    @click.outside="open = false"
                    class="absolute left-0 mt-2 w-80 rounded-lg bg-white shadow-lg ring-1 ring-slate-200 z-50 dark:bg-slate-800 dark:ring-slate-700"
                    dir="rtl"
                    x-cloak
                >
                    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 dark:border-slate-700">
                        <div class="text-sm font-semibold text-slate-900 dark:text-white">الإشعارات</div>
                        <form method="POST" action="{{ route('notifications.read-all') }}">
                            @csrf
                            <button type="submit" class="text-xs text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">تعليم الكل كمقروء</button>
                        </form>
                    </div>
                    <template x-if="items.length === 0">
                        <div class="px-4 py-8 text-center text-sm text-slate-400">لا توجد إشعارات</div>
                    </template>
                    <div class="max-h-80 overflow-y-auto">
                        <template x-for="item in items" :key="item.id">
                            <form method="POST" :action="item.read_url">
                                @csrf
                                <button type="submit" class="w-full text-right px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/50" :class="{ 'bg-slate-50 dark:bg-slate-700/30': !item.read_at }">
                                    <div class="flex items-start gap-2">
                                        <span x-show="!item.read_at" class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-indigo-500"></span>
                                        <div class="flex-1">
                                            <div class="text-sm font-medium text-slate-900 dark:text-white" x-text="item.title"></div>
                                            <div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400" x-text="item.time"></div>
                                        </div>
                                    </div>
                                </button>
                            </form>
                        </template>
                    </div>
                    <div class="border-t border-slate-100 px-4 py-2 dark:border-slate-700">
                        <a href="{{ route('notifications.index') }}" class="block text-center text-xs font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">عرض كل الإشعارات</a>
                    </div>
                </div>
            </div>
```

- [ ] **Step 3: إنشاء `resources/views/notifications/index.blade.php`**

```blade
<x-app-layout>
    <x-slot name="title">{{ __('الإشعارات') }}</x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('الإشعارات') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">كل التنبيهات الداخلية الخاصة بك</p>
            </div>
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">تعليم الكل كمقروء</button>
            </form>
        </div>

        <div class="space-y-2">
            @forelse ($notifications as $notification)
                <form method="POST" action="{{ route('notifications.read', $notification) }}" class="block rounded-xl border p-4 shadow-sm {{ $notification->read_at ? 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900' : 'border-indigo-200 bg-indigo-50/60 dark:border-indigo-500/20 dark:bg-indigo-500/5' }}">
                    @csrf
                    <button type="submit" class="w-full text-right">
                        <div class="flex items-start gap-2">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $notification->read_at ? 'bg-transparent' : 'bg-indigo-500' }}"></span>
                            <div class="flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="text-sm font-semibold text-slate-900 dark:text-white">{{ $notification->title }}</div>
                                    <span class="shrink-0 text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</span>
                                </div>
                                @if ($notification->body)
                                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300 line-clamp-2">{{ $notification->body }}</p>
                                @endif
                                @if ($notification->link)
                                    <span class="mt-2 inline-block text-xs font-medium text-indigo-600 dark:text-indigo-400">فتح →</span>
                                @endif
                            </div>
                        </div>
                    </button>
                </form>
            @empty
                <div class="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-400 dark:border-slate-700 dark:bg-slate-900">لا توجد إشعارات</div>
            @endforelse
        </div>

        @if ($notifications->hasPages())
            <div>{{ $notifications->links() }}</div>
        @endif
    </div>
</x-app-layout>
```

- [ ] **Step 4: حقن بيانات البيل (إشعارات غير المقروءة) في Layout**
  - نمرّرها من أي مُعرَّض Layout مشترك. أسهل نمط موجود (بدون View Composer): ضع في `app/Http/Middleware/TrimStrings`؟ لا — الأفضل: استخدم `View::composer` أو حقن في `app_layout` عبر `share`. الأنسب لنمط المشروع: **أضف في `bootstrap/app.php` بعد `$app` تكوين** أو — أبسط بلا ملفات جديدة — حسِّن `x-app-layout` عبر `View::composer('layouts.app', ...)` في `AppServiceProvider`.
  - Modify: `app/Providers/AppServiceProvider.php` — أضف في `boot()`:
    ```php
    use Illuminate\Support\Facades\View;
    use App\Models\Notification;

    View::composer('layouts.app', function ($view) {
        $user = auth()->user();
        $notifications = $user
            ? Notification::where('user_id', $user->id)->latest()->take(5)->get()
            : collect();

        $view->with('topNotifications', $notifications);
        $view->with('topUnreadCount', $user ? Notification::where('user_id', $user->id)->whereNull('read_at')->count() : 0);
    });
    ```
  - ثم في `topbar.blade.php` داخل نطاق `x-data="notificationsDropdown()"` استخدم البيانات:
    ```blade
    <x-app-layout>
        ...
    </x-app-layout>
    ```
    وأخبر Alpine بالقيم عبر سمات على عنصر `x-data`:
    ```blade
    <div x-data="notificationsDropdown(@js($topNotifications->map(fn ($n) => [
        'id' => $n->id,
        'title' => $n->title,
        'time' => $n->created_at->diffForHumans(),
        'read_at' => $n->read_at,
        'read_url' => route('notifications.read', $n),
    ])->all()), {{ $topUnreadCount }})" class="relative">
    ```
  - أضف دالة Alpine في نفس الملف أسفلها:
    ```html
    <script>
        function notificationsDropdown(items = [], unreadCount = 0) {
            return {
                open: false,
                unreadCount,
                items,
            };
        }
    </script>
    ```
  - (تأكد أن `layouts/app.blade.php` يعرف `$topNotifications`/`$topUnreadCount` — يُمرَّر تلقائيًا لكل تضمين ومكوّن داخل `layouts.app`.)

- [ ] **Step 5: فحص**
  - `php -l app/Http/Controllers/NotificationController.php` → No syntax errors.

---

## Task 8: Seeder (بيانات دعم تجريبية)

**Files:**
- Modify: `database/seeders/CrmDemoDataSeeder.php` (إضافة دعم خفيف للتذاكر)

**Interfaces:**
- Consumes: `Ticket` / `TicketMessage` (Task 1) · مستخدمو الـ seeder الحاليون.

- [ ] **Step 1: إضافة دالة `tickets()` واستدعائها في `run()`**
  - داخل `run()` بعد بناء العملاء، أضف: `$this->tickets();`
  - أضف الدوال التالية قبل `salesUsers()`:

```php
    private function tickets(): void
    {
        if (\App\Models\CRM\Ticket::exists()) {
            return;
        }

        [$rep, $manager, $support] = $this->salesUsers();
        $customers = \App\Models\CRM\Customer::orderBy('id')->take(6)->get();
        $categories = ['فني', 'محاسبي', 'مبيعات', 'شحن', 'أخرى'];
        $statuses = ['open', 'in_progress', 'resolved', 'resolved', 'closed'];
        $priorities = ['low', 'medium', 'medium', 'high'];

        $sample = [
            'الموقع يظهر أخطاء عند فتح صفحة المنتجات',
            'تأخر في استلام الشحنة الأخيرة',
            'استفسار عن فاتورة شهر الماضي',
            'طلب تعديل بيانات العميل',
            'مشكلة في تسجيل الخروج من النظام',
            'طلب دعم فني لتركيب البرنامج',
            'ملاحظة على واجهة التقارير',
            'استفسار عن سياسة الاسترجاع',
        ];

        foreach ($sample as $i => $text) {
            $ticket = \App\Models\CRM\Ticket::create([
                'ticket_number' => 'TK-'.str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT),
                'customer_id' => $customers->get($i % 3)?->id,
                'subject' => $text,
                'message' => 'العميل أرسل هذه الشكوى ويطلب المتابعة السريعة.',
                'category' => $categories[$i % count($categories)],
                'priority' => $priorities[$i % count($priorities)],
                'status' => $statuses[$i % count($statuses)],
                'assigned_to' => $support?->id,
                'created_by' => $rep?->id,
                'resolved_at' => in_array($statuses[$i % count($statuses)], ['resolved', 'closed'], true) ? now()->subDays($i) : null,
                'created_at' => now()->subDays($i * 2 + 1),
                'updated_at' => now()->subDays($i * 2 + 1),
            ]);

            if ($ticket->status === 'in_progress') {
                \App\Models\CRM\TicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $support?->id,
                    'body' => 'تم استلام الشكوى وجارٍ التحقيق مع الفريق الفني.',
                    'is_internal' => false,
                    'created_at' => now()->subDays($i * 2),
                    'updated_at' => now()->subDays($i * 2),
                ]);
            }
        }
    }
```

- [ ] **Step 2: تشغيل للتأكد**
  - Run: `php artisan db:seed --class=CrmDemoDataSeeder`
  - Expected: لا أخطاء؛ إن وُجدت تذاكر فعلية فلن تُكرر.

---

## Task 9: Tests

**Files:**
- Create: `tests/Feature/TicketTest.php`
- Create: `tests/Feature/NotificationTest.php`

**Interfaces:**
- Consumes: كل ما سبق · `RbacSeeder` (يحوي الآن `assign_tickets` + `delete_tickets`).

- [ ] **Step 1: `tests/Feature/TicketTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\CRM\Customer;
use App\Models\CRM\Ticket;
use App\Models\CRM\TicketMessage;
use App\Models\Notification;
use App\Models\User;
use Database\Seeders\CrmDemoDataSeeder;
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
        return Customer::create(['name' => 'عميل دعم', 'phone' => '0599'.random_int(100000, 999999)]);
    }

    private function makeTicket(string $status = 'open'): Ticket
    {
        return \App\Services\CRM\TicketService::stub()->store?->invoke(
            new \App\Services\CRM\TicketService(),
            ['subject' => 'مشكلة في النظام', 'message' => 'تفاصيل'], $this->support()
        );
    }
}
```

- [ ] **Step 2: `tests/Feature/NotificationTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\CRM\Customer;
use App\Models\CRM\Ticket;
use App\Models\Notification;
use App\Models\User;
use App\Services\CRM\TicketService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
```

- [ ] **Step 3: توضيح/تنقيح**
  - مقتطفا الاختبارات أعلاه **مبدئيان ناقصان** (الـ helper `makeTicket` فيه استدعاء زائد). أكمل اختبارات `TicketTest` بالحالات الآتية (مضمون كامل يُكتب عند التنفيذ — القواعد في `TicketService`):
    1. `test_support_can_create_ticket` — POST `tickets.store` → `Ticket` برقم `TK-000001` وحالة open.
    2. `test_ticket_requires_subject_and_message` — أخطاء `subject`, `message`.
    3. `test_support_can_reply_and_status_becomes_in_progress` — رد على open → `in_progress` + رسالة.
    4. `test_reply_reopens_closed_ticket` — رد على closed → `in_progress` + `resolved_at` = null.
    5. `test_assign_ticket_sets_in_progress` — تعيين → `assigned_to` + `in_progress`.
    6. `test_support_can_resolve_ticket` — updateStatus resolved → `resolved_at` غير فارغ.
    7. `test_rep_cannot_resolve_ticket` — محاولة resolve من rep → `assertSessionHas('error')`.
    8. `test_delete_ticket_from_open` — حذف → soft delete.
    9. `test_delete_closed_ticket_denied` — حذف مغلقة → error.
    10. `test_support_cannot_access_tickets` → 403.
    11. `test_creating_ticket_notifies_viewers` — إنشاء تذكرة → `Notification` لمن لديهم `view_tickets` (admin + support) باستثناء المنشئ، ولها `read_at = null`.
    12. `test_reply_notifies_creator_and_assignee` — رد → إشعار لمنشئ التذكرة ولمن عُيّنت.
    13. `test_notification_mark_read` — `markRead` → `read_at` غير فارغ، و403 عند محاولة غيره.
    14. `test_notification_page_renders` — GET `notifications.index` → 200.
  - احذف helper `makeTicket` الزائد أعلاه واستخدم `TicketService::store()` مباشرة في كل اختبار.

- [ ] **Step 4: تشغيل**
  - Run: `php artisan test --filter=TicketTest` ثم `php artisan test --filter=NotificationTest`
  - Expected: كل الاختبارات تمر.

---

## Task 10: Docs

**Files:**
- Modify: `docs/DATABASE_SCHEMA.md` (tickets/ticket_messages/notifications)
- Modify: `docs/PLAN.md` (Phase 5 → ✅ + ملاحظة تنفيذ)
- Create: `phases/Phase-5-Support.md`

- [ ] **Step 1: `docs/DATABASE_SCHEMA.md`** — تحقق أن المخطط يعكس: `tickets (…, message, category, priority, status, assigned_to, created_by, resolved_at, deleted_at)` وقسم notifications/قل العلاقات، وأضفها إن لم تكن.
- [ ] **Step 2: `docs/PLAN.md`** — علّم Phase 5 `[x]` وحدّث شريط الحالة (المرحلة التالية Phase 6) وأضف ملاحظة بأسلوب المراحل السابقة.
- [ ] **Step 3: `phases/Phase-5-Support.md`** — ملف ملحق بنفس تنسيق `Phase-4-Procurement.md`.

---

## Task 11: Route/Test Verification + Smoke

- [ ] **Step 1:** `php artisan route:list --name=tickets` → 8 مسارات.
- [ ] **Step 2:** `php artisan route:list --name=notifications` → 3 مسارات.
- [ ] **Step 3:** `php artisan test` (كامل) → كل الاختبارات تمر (≥ 105 + الجديدة).
- [ ] **Step 4:** `php artisan db:seed --class=RbacSeeder` → لا أخطاء.
- [ ] **Step 5:** Smoke يدوي عبر `php artisan serve`:
  1. دخول supervisor `support@crm.test / password` → إنشاء تذكرة بعميل وموضوع.
  2. فتح التذكرة → رد خارجي → الحالة تصبح "قيد المعالجة".
  3. تعيين التذكرة لعضو آخر → يظهر الإشعار بعداد rose في البيل.
  4. حلّ التذكرة ثم غلقها، ثم "إعادة فتح" تعيدها وتفرّغ `resolved_at`.
  5. دخول `rep@crm.test` → محاولة حلّ تذكرة → error.
  6. محاولة حذف تذكرة مغلقة → error.

---

## Self-Review ملاحظات

- **تغطية المواصفة:** كل قرارات المستخدم في الـ spec مغطاة: 4 حالات (open/in_progress/resolved/closed) + إعادة فتح بالرد (Task 2 + views) · صلاحيتان جديدتان `assign_tickets`+`delete_tickets` (Task 5) · إشعارات عند الإنشاء وكل رد (Task 2 `NotificationService` + الـ controller) · Seeder دعم خفيف (Task 8).
- **الاتساق:** أعمدة الـ migration (message/category) مطابقة للموديل الجديد بعد إعادة البناء · جدول notifications مخصص بـ `user_id` — لا نستخدم Laravel DatabaseNotification · `can()` حارس قواعد التحولات داخل الخدمة + middleware permission على الـ routes.
- **نقطة حذف مغلقة:** `destroy` ممنوع للـ closed (قرار يمنع فقدان السجل) — مطابق للتجربة في الـ smoke.
- **الموديل القديم:** `CRM\Ticket` كان يشير لأعمدة غير موجودة — أُعيد بناؤه كليًا في Task 1؛ أي استدعاء سابق علنًا غير موجود (لم نجد Controllers تشير له).