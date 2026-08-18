# المرحلة 5 — الدعم والإشعارات (Phase 5) ✅ مكتملة

**المدة:** أسبوع (8)
**الهدف:** بناء تذاكر الدعم الكاملة — حالات `open → in_progress → resolved → closed`، أولوية، تصنيف، تعيين، رسائل داخلية/خارجية، حذف ناعم، ترقيم تسلسلي `TK-000001` — مع إشعارات داخلية في جدول `notifications` المخصص مرتبطة بالبيل في الـ topbar.

---

## ما تم تنفيذه في هذه المرحلة

### 1) النماذج (Models) + الحالات المقبلة
**إعادة بناء `CRM\Ticket` كليًا** (كان يشير لأعمدة `description`/`department` غير الموجودة في الجدول):
| النموذج | ما يخزّنه |
|---|---|
| **Ticket** | تذكرة: رقم تسلسلي فريد، عميل (nullable)، موضوع، رسالة، تصنيف، أولوية، حالة، `assigned_to`/`created_by`، تاريخ الحل — **Soft Deletes** + `scopeSearch` + علاقات `customer/assignee/creator/messages` + `statusLabel()/priorityLabel()` |
| **TicketMessage** | سطر: التذكرة، المستخدم، النص، `is_internal` + `scopePublic` (إخفاء الداخلي عن غير المصرح لهم) |
| **Notification** (مخصص) | إشعار داخلي: `id` UUID، `user_id`، نوع، عنوان، نص، رابط، `read_at` + `scopeUnread` — **لا يستخدم** `DatabaseNotification` |

**الحالات (Enums):** `TicketStatus` (open/in_progress/resolved/closed) و `TicketPriority` (low/medium/high) مع `label()/color()/list()` عربية تُعرض في الـ views.

---

### 2) الخدمات (Services)
**TicketService** — سيل واحد لكل انتقال:
| العملية | الوصف |
|---|---|
| **`store()`** | ينشئ التذكرة برقم `TK-000001` تلقائيًا (عبر `withTrashed()->max('id')`) وحالة open + AuditLog |
| **`reply()`** | يكتب رسالة؛ **يعيد فتح** التذكرة المغلقة/المحلولة (in_progress + `resolved_at` فارغ) ويحوّل open→in_progress ثم AuditLog |
| **`assign()`** | تعيين مستخدم (أو إلغاءه)؛ التعيين يحوّل open→in_progress |
| **`updateStatus()`** | يوافق بين الحالات مع حُراس صلاحيات: close/resolve تحتاج `resolve_tickets`، open/in_progress تحتاج `reply_tickets`؛ ضبط `resolved_at` |
| **`destroy()`** | حذف ناعم؛ **ممنوع** للتذكرة المغلقة (حماية للسجل) |

**NotificationService:**
- `send()` — كتابة إشعارات UUID لكل المستخدمين.
- `notifyTicketCreated()` — لكل المستخدمين النشطين بصلاحية `view_tickets` باستثناء المنشئ.
- `notifyTicketReplied()` — لمنشئ التذكرة وللمُعیَّن إليها (باستثناء كاتب الرد).

---

### 3) الصفحات (Views) — عربية RTL
- **فهرس التذاكر:** بحث (رقم/موضوع/عميل) + تصفية بالحالة والأولوية + جدول بشارات ملونة + ترقيم صفحات.
- **إنشاء تذكرة:** العميل (اختياري) + التصنيف + الأولوية + الموضوع + التفاصيل.
- **عرض التذكرة:** سلسلة رسائل (خارجية بيضاء/داخلية كهرمانية برادج "ملاحظة داخلية") + بيانات جانبية + نموذج تعيين + أزرار حالة/حل/غلق/حذف **مشروطة بالحالة والصلاحية** + نموذج رد داخلي/خارجي.
- **صفحة الإشعارات:** قائمة غير المقروء مميزة + تعليم الكل كمقروء.
- **البيل في الـ topbar:** عداد غير المقروء (شارة حمراء) + قائمة منسدلة عبر Alpine مع "تعليم الكل كمقروء" و"عرض كل الإشعارات".

---

### 4) الصلاحيات (Permissions)
| مقطع | الصلاحيات | لمن |
|---|---|---|
| التذاكر | `view/create/reply/resolve_tickets` (موجودة) | المدير (كلها) + أخصائي الدعم |
| **جديدة** | `assign_tickets` + `delete_tickets` | المدير + أخصائي الدعم |

**قرار تفصيلي:** `updateStatus` تساوي بين open/in_progress بـ `reply_tickets` وبين resolved/closed بـ `resolve_tickets`؛ route عربة التذاكر تحمي بـ middleware بينما الخدمة تتحقق ثانية داخل `can()`.

---

### 5) Form Requests (جديدة — بنمط Gap 1)
- `StoreTicketRequest` (customer/subject/message/category/priority).
- `TicketReplyRequest` (body + is_internal).
- `AssignTicketRequest` (assigned_to nullable).

---

### 6) المسارات (Routes)
- **tickets**: 8 مسارات (index/create/store/show/destroy/reply/assign/status) — محل الـ Placeholder القديم.
- **notifications**: 3 مسارات (index/read/read-all) — قراءة إشعار تُحيل للرابط الخاص به مع التحقق من الملكية (403 عند الغير).

---

### 7) حقن بيانات البيل (Layout)
- `AppServiceProvider::boot()` → `View::composer('layouts.app', ...)` يشارك `topNotifications` (آخر 5) و `topUnreadCount` لكل صفحات الـ app layout.
- دالة Alpine `notificationsDropdown()` داخل `topbar` لتهيئة القائمة المنسدلة.

---

### 8) البيانات التجريبية (Seeder)
- `CrmDemoDataSeeder::tickets()` (8 تذاكر) بحالات/أولويات/تصنيفات متنوعة + رسالة واحدة لتذكرة قيد المعالجة — يُتجاوز إن وُجدت تذاكر سابقة.

---

### 9) الاختبارات (19 اختبار Feature) — إجمالي المشروع 109
**TicketTest (14):** إنشاء تذكرة برقم `TK-000001` وحالة open، إلزامية الموضوع/التفاصيل، الرد يحوّل إلى in_progress، إعادة فتح المغلقة تفرّغ `resolved_at`، التعيين يحوّل إلى in_progress، حل التذكرة يضبط `resolved_at`، منع من بلا صلاحية حل، حذف open (soft) ومنع حذف المغلقة (error)، منع مندوب المبيعات (403)، إشعار عند الإنشاء للمشاهدين باستثناء المنشئ، إشعار الرد للمنشئ والمسؤول، بيانات الـ seeder التجريبي، **إخفاء الملاحظات الداخلية عن غير المصرّح بالرد** (مع الاطلاع للأدمن).

**NotificationTest (5):** عرض صفحة الإشعارات، إشعار إنشاء تذكرة، تعليم مقروء يضبط `read_at`، منع قراءة إشعار الغير (403)، تعليم الكل كمقروء.

**ملاحظة تصحيح عن خطة التنفيذ:** اختبار "منع الحل من rep" أُجري عبر دور محدود (`view_tickets`+`reply_tickets` دون `resolve_tickets`) لأن `rep` ممنوع في الأصل من صفحة التذاكر بـ 403 (لا يملك `view_tickets`) — فالاختبار يفحص حارس الخدمة فعليًا.

> **ملاحظة على الأرقام:** الإجمالي الفعلي قبل المرحلة السادسة هو **109 اختبارًا** (وليس 124). أرقام المراحل السابقة (40/52/68/105) كانت تقديرية/تقريبية.

---

## كيف تتجرب بنفسك
```bash
php artisan serve
```
سجّل الدخول بـ `support@crm.test / password` (أخصائي الدعم) ثم جرّب:
1. **تذاكر الدعم** → أنشئ تذكرة بعميل وموضوع (الرقم `TK-000001` تلقائيًا، والحالة "مفتوحة").
2. افتح التذكرة → أرسل ردًا → الحالة تصبح "قيد المعالجة".
3. **عيّن** التذكرة لعضو آخر → تظهر الإشعارات (عداد أحمر في البيل) لمن لديهم `view_tickets` باستثناء نفسك.
4. **حلّ** التذكرة ثم **أغلقها**؛ ثم "إعادة فتح" تعيدها وتفرّغ تاريخ الحل.
5. أضف **ملاحظة داخلية** → تُرى بشارة كهرمانية (ولن يراها من لا يملك صلاحية الرد).
6. سجّل دخول بـ `rep@crm.test` (ممنوع من صفحة التذاكر بـ 403) أو `admin@crm.test` (يرى كل شيء ويبلغ عن كل التذاكر).

---

*آخر تحديث: أغسطس 2026*

---

## ملخص التنفيذ (Phase 5 Summary)

Phase 5 complete. All 11 tasks executed and verified.

### Implemented (Support Tickets + Notifications), deviating from the plan only where it was buggy/incomplete:

- **Models/Enums:** Rebuilt `CRM\Ticket` to match the table (message/category, SoftDeletes); added `CRM\TicketMessage`, `App\Models\Notification` (UUID), `TicketStatus`, `TicketPriority`.
- **Services:** `TicketService` (TK-000001 numbering, state transitions, reopen-on-reply, soft-delete blocked on closed, permission guards) + `NotificationService` (create/reply notifications to `view_tickets` holders).
- **Controller/Requests/Views:** `TicketController`, 3 Form Requests, index/create/show pages with status threads, assign form, internal-note badges.
- **Routes/RBAC:** 8 `tickets.*` + 3 `notifications.*` routes; new `assign_tickets`/`delete_tickets` permissions on support-agent + admin.
- **Notifications UI:** topbar bell with unread counter + Alpine dropdown (data via `View::composer`), notifications page with mark-read/mark-all.
- **Docs:** updated `PLAN.md`/`DATABASE_SCHEMA.md` + new `phases/Phase-5-Support.md`.

### Corrections to the plan (all three flagged at review):
1. Fixed `updateStatus` enum-vs-string comparison bug so close/resolve permission checks and `resolved_at` now actually work.
2. `scopePublic` is now consumed — internal notes hidden from users lacking `reply_tickets`.
3. Wrote the complete test suites (plan's scaffolding was broken).

### Verification:
`php artisan test` → **109 passed / 350 assertions** (105 → 109). Routes confirmed (8+3). All views render.

**Note:** this isn't a git repo, so no branch/worktree finishing step applies. Device smoke test (`php artisan serve`) remains for manual testing at `support@crm.test / password`. The DB already has CRM data, so `CrmDemoDataSeeder` skipped its ticket seeding — available on a fresh `migrate:fresh --seed`.

Ready for **Phase 6 (HR/Payroll)** when you are.

---

## ملخص التنفيذ بالعربية (ملحق)

اكتملت المرحلة الخامسة بالكامل — 11 مهمة نُفّذت وتم التحقق منها.

### ما تم تنفيذه (تذاكر الدعم + الإشعارات) — مع انحرافات عن الخطة فقط حيث كانت بها أخطاء/نواقص:

- **النماذج والحالات:** أُعيد بناء `CRM\Ticket` ليطابق الجدول (message/category + SoftDeletes)؛ وأُضيف `CRM\TicketMessage` و `App\Models\Notification` (مفتاح UUID) و `TicketStatus` و `TicketPriority`.
- **الخدمات:** `TicketService` (ترقيم TK-000001، انتقالات الحالات، إعادة الفتح بالرد، منع حذف المغلقة، حُرّاس الصلاحيات) + `NotificationService` (إشعارات الإنشاء والرد لمن لديهم `view_tickets`).
- **التحكم/الطلبات/الصفحات:** `TicketController` + 3 Form Requests + صفحات فهرس/إنشاء/عرض مع سلسلة الرسائل ونموذج التعيين وشارات الملاحظات الداخلية.
- **المسارات والصلاحيات:** 8 مسارات `tickets.*` + 3 مسارات `notifications.*`؛ صلاحيتان جديدتان `assign_tickets` و `delete_tickets` لأخصائي الدعم والمدير.
- **واجهة الإشعارات:** بيل بعدّاد غير المقروء + قائمة منسدلة بـ Alpine (بيبانات عبر `View::composer`) + صفحة إشعارات مع تعليم مقروء/الكل.
- **التوثيق:** تحديث `PLAN.md` و `DATABASE_SCHEMA.md` + إنشاء `phases/Phase-5-Support.md`.

### تصحيحات على الخطة (الثلاثة جميعًا رُصدت في المراجعة):
1. إصلاح خطأ المقارنة في `updateStatus` بين الـ enum والنص — فأصبح فحص صلاحيات الغلق/الحل وضبط `resolved_at` يشتغل فعلًا.
2. تفعيل `scopePublic` — إخفاء الملاحظات الداخلية عن من لا يملك `reply_tickets`.
3. كتابة اختبارات كاملة (كان نص الخطة ناقصًا).

### التحقق:
`php artisan test` → **109 اختبارًا ناجحًا / 350 تأكيدًا** (105 → 109). المسارات مؤكدة (8+3). كل الصفحات تُعرض بنجاح.

**ملاحظة:** المشروع ليس مستودع Git، لذلك لا تنطبق خطوة الفرع النهائية. اختبار الـ Smoke اليدوي (`php artisan serve`) متروك للتجربة بـ `support@crm.test / password`. قاعدة البيانات الحالية بها بيانات CRM، لذلك تخطّى `CrmDemoDataSeeder` تذاكره — متاح على قاعدة نظيفة عبر `migrate:fresh --seed`.

جاهزون للمرحلة السادسة (الموارد البشرية Payroll) متى أردت.