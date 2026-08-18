# المرحلة 0 — التأسيس (Phase 0) ✅ مكتملة

**المدة:** أسبوع واحد
**الهدف:** تجهيز بنية النظام الأساسية بالكامل قبل بناء أي وحدة وظيفية.

---

## ما تم تنفيذه في هذه المرحلة

### 1) تثبيت Laravel 11 + Breeze (Blade)
- تثبيت Laravel 11 مع PHP 8.2.12.
- تثبيت **Laravel Breeze** بواجهة Blade (وليس Livewire أو Inertia).
- تهيئة Tailwind CSS + Vite للبناء والتصحيح.
- الصفحات الافتراضية: تسجيل الدخول، التسجيل، تأكيد البريد، تغيير كلمة المرور، حذف الحساب.

**الميزة:** نظام مصادقة جاهز وآمن (CSRF + جلسات + تشفير كلمات المرور) دون إعادة اختراع العجلة.

---

### 2) ضبط العربية RTL + الخطوط
- تفعيل `dir="rtl"` في التخطيط العام.
- تحميل خط **Cairo** (عربي حديث) و **Figtree** من bunny.net.
- تخصيص Tailwind للاتجاه RTL.

**الميزة:** الواجهة كلها تُعرض من اليمين لليسار بطريقة سليمة، وهي اللغة الأساسية للنظام.

---

### 3) إنشاء كل الـ Migrations (37 ملف)
تم تصميم بنية قاعدة البيانات كاملةً مسبقًا، تغطي كل وحدات المشروع:

| المجموعة | الجداول |
|---|---|
| Auth + RBAC | users, roles, permissions, role_permission |
| CRM | leads, customers, opportunity_stages, opportunities, activities |
| الدعم | tickets, ticket_messages |
| المنتجات والمخزون | categories, units, products, warehouses, stock_items, stock_movements |
| الموردين والمشتريات | suppliers, purchase_orders, purchase_order_items |
| المبيعات والفواتير | sales_orders, sales_order_items, invoices, invoice_items, payments, expenses |
| الموارد البشرية | departments, employees, payrolls, payroll_items, attendance |
| إضافات | notifications, audit_logs |

**الميزة:** كل الجداول والعلاقات (Foreign Keys + فهارس فريدة مثل `unique(product_id, warehouse_id)`) جاهزة مسبقًا، ما يمنع إعادة هيكلة لاحقًا.

---

### 4) RBAC كامل — الأدوار والصلاحيات
- جدول `permissions` (74 صلاحية) + جدول `roles` + جدول وسيط `role_permission`.
- **سيدر RbacSeeder** يُنشئ:
  - **74 صلاحية** مقسمة على مجموعات (leads, customers, opportunities, activities, tickets, products, warehouses, stock, suppliers, purchase_orders, sales_orders, invoices, payments, expenses, departments, employees, payroll, attendance, reports, users, roles, audit_logs).
  - **8 أدوار**: admin، sales-manager، sales-rep، support-agent، accountant، warehouse-manager، purchasing-officer، hr-admin.
  - **8 مستخدمين تجريبيين** (واحد لكل دور) بكلمة مرور `password`.
- **Middleware `CheckPermission`**: يفحص صلاحية معينة قبل تنفيذ أي مسار، ويعيد `403` عند عدم وجودها.
- **دوال في الـ User**:
  - `hasRole(slug)` — هل المستخدم بهذا الدور.
  - `hasPermission(slug)` — هل يملك صلاحية محددة.
  - `isAdmin()` — سريعة للمسؤول.

**الميزة الأهم:** التحكم في الوصول *على مستوى المسار* وليس فقط إخفاء الأزرار، ما يعني أن أي محاولة مباشرة لفتح رابط غير مسموح ترفض فورًا.

---

### 5) التخطيط العام (Layout)
- **Sidebar** جانبي ذكي: يعرض الأقسام والأزرار حسب صلاحيات الدور (المستخدم لا يرى ما لا يملكه).
- **Topbar** علوي ثابت مع معلومات المستخدم.
- **وضع داكن (Dark Mode)** عبر مفتاح تخزين محلي + تفضيلات النظام.
- **صفحة Placeholder** "قيد التطوير" لكل وحدة لم تُبنَ بعد، محمية بنفس الصلاحيات.

**الميزة:** تجربة مستخدم متسقة ورسمية من أول لحظة، مع مرونة لإضافة الوحدات لاحقًا دون تغيير الهيكل.

---

### 6) سجل التدقيق Audit Log
- جدول `audit_logs` (المستخدم، الإجراء، النموذج، الـ ID، القيم قبل/بعد بصيغة JSON، IP، User-Agent).
- **خدمة `AuditLogger::log()`**: تُستخدم من أي مكان لتسجيل عملية.
- تُسجَّل الإجراءات: created / updated / deleted / status_changed / converted / stage_changed / completed.

**الميزة:** تتبع كامل "من فعل ماذا ومتى وقبل/بعد" — ضروري للمساءلة والمراجعة.

---

### 7) البيئة والإعداد
- ملف `.env` بقاعدة MySQL جديدة نظيفة `crm_erp_app`.
- ضبط collation `utf8mb4_unicode_ci` لدعم العربية بالكامل.
- تحقق فعلي: تسجيل الدخول يعيد `302` ثم لوحة التحكم `200`.

---

## خلاصة المزايا للمستخدم النهائي
- نظام آمن (مصادقة + صلاحيات + تدقيق).
- واجهة عربية احترافية RTL مع وضع داكن.
- البنية الكاملة للمشروع جاهزة مسبقًا — كل وحدة ستبنى داخل إطار ثابت.

---
*آخر تحديث: أغسطس 2026*

---

## ملخص التنفيذ (Phase 0 Summary)

Phase 0 complete — the foundation is fully in place.

### Implemented:
- **Laravel 11 + Breeze (Blade)** installed with PHP 8.2.12, Tailwind CSS + Vite wired up.
- **Arabic RTL** fully enabled with **Cairo** + Figtree fonts.
- **37 migrations** covering the entire project schema (Auth/RBAC, CRM, Support, Products/Inventory, Purchasing, Sales/Invoicing, HR, Notifications/Audit).
- **Full RBAC:** 74 permissions, 8 roles, 8 demo users (`password`), `CheckPermission` middleware (403), plus `hasRole/hasPermission/isAdmin` helpers.
- **Smart Layout:** role-aware sidebar, sticky topbar, dark mode (localStorage + system preference), protected placeholder pages.
- **Audit Log system:** `audit_logs` table + `AuditLogger::log()` service.
- **Environment:** fresh MySQL DB `crm_erp_app`, `utf8mb4_unicode_ci` for full Arabic support.

### Verification:
Login returns `302` → dashboard renders `200`. No feature tests in this phase (infrastructure only).

---

## ملخص التنفيذ بالعربية (ملحق)

اكتملت المرحلة الصفرية — التأسيس كامل.

### ما تم تنفيذه:
- **Laravel 11 + Breeze (Blade)** مُثبّت مع PHP 8.2.12، وربط Tailwind CSS + Vite.
- تفعيل **العربية RTL** بالكامل مع خط **Cairo** و Figtree.
- **37 ملف Migration** تغطي مخطط المشروع كاملًا (Auth/RBAC، CRM، الدعم، المنتجات/المخزون، المشتريات، المبيعات/الفواتير، الموارد البشرية، الإشعارات/التدقيق).
- **نظام RBAC كامل:** 74 صلاحية، 8 أدوار، 8 مستخدمين تجريبيين (`password`)، `CheckPermission` middleware (403)، ودوال `hasRole/hasPermission/isAdmin`.
- **تخطيط ذكي:** Sidebar حسب الدور، Topbar ثابت، وضع داكن (localStorage + تفضيل النظام)، وصفحات Placeholder محمية.
- **نظام سجل التدقيق:** جدول `audit_logs` + خدمة `AuditLogger::log()`.
- **البيئة:** قاعدة MySQL جديدة نظيفة `crm_erp_app` مع `utf8mb4_unicode_ci` لدعم العربية بالكامل.

### التحقق:
تسجيل الدخول يعيد `302` → لوحة التحكم تُعرض بـ `200`. لا توجد اختبارات Feature في هذه المرحلة (بنية تحضيرية فقط).

---

*آخر تحديث: أغسطس 2026*
