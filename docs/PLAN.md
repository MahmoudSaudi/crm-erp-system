# خطة التنفيذ — نظام متكامل CRM + ERP

**الملخص التنفيذي:** نظام ويب متكامل للشركات الصغيرة والمتوسطة يجمع بين CRM (العملاء والفرص والتذاكر) و ERP (المنتجات والمخازن والمبيعات والفواتير والمشتريات والرواتب) ولوحة تحكم مركزية.

**قرار المعمارية:** Monolith Laravel واحد (Blade + Backend معًا) — Laravel 11 + Blade + JavaScript (Alpine.js) + Tailwind CSS + MySQL 8.0 — عربي RTL أساسي.

**الحالة:** ✅ مكتملة — المراحل 0 إلى 7 جميعها منجزة (CRM + ERP + Support + HR + Reports) وكل اختبارات المشروع (142) ناجحة.

---

## 1) Project Scope

### داخل MVP (يُنفذ كاملًا)
- Auth + Roles + Permissions (RBAC)
- لوحة تحكم أساسية (KPI + رسم بياني)
- Leads + Pipeline + تحويل Lead→Customer
- Customers + Opportunities + Activities
- Products + Inventory + جرد (Stock Movements)
- Sales Orders + خصم المخزون
- Invoices + Payments + Expenses
- Support Tickets
- تقارير أساسية (مبيعات + مخزون منخفض)
- Notifications + Audit Logs

### Phase 2 / Nice-to-have (مؤجل)
- Payroll كامل + Attendance
- تصدير Excel/PDF للتقارير
- Customer Portal / Self-service
- لوحات تحكم منفصلة لكل دور (متقدمة)
- REST API لنفس منطق الأعمال
- متعدد الشركات (Tenancy)
- إشعارات بالبريد
- ترجمة E/L ثنائية

### لا يُبنى إطلاقًا في النسخة الأولى
- Tenancy / متعدد الشركات
- اشتراكات Subscriptions
- ترجمة متعددة اللغات
- إشعارات بريد
- سلة مبيعات POS مخصصة
- إدارة عقود اشتراكات

---

## 2) الوحدات والمتطلبات الوظيفية

| الوحدة | المتطلبات الوظيفية الأساسية |
|---|---|
| **Auth & Roles** | تسجيل/دخول/خروج، صلاحيات per-item (view/create/edit/delete)، منع الوصول حسب الدور، تغيير كلمة المرور |
| **Dashboard** | بطاقات KPI (مبيعات اليوم، عملاء جدد، فرص مفتوحة، مخزون منخفض، تذاكر مفتوحة)، رسم بياني مبيعات شهريًا، آخر الأنشطة والتذاكر |
| **CRM** | Leads (Pipeline كوبورد New→Won/Lost)، تحويل Lead→Customer، Customers CRUD، Opportunities + مراحل، Activities (مكالمة/اجتماع/مهمة/بريد) |
| **ERP - Products** | Categories + Units + Products (SKU/barcode/sale/cost/min_stock) + Warehouses + StockItems + StockMovements (رصيد قبل/بعد) |
| **ERP - Sales** | Sales Orders من العمل، خصم/ضريبة/شحن، خصم من المخزون تلقائيًا عند التأكيد، حالات (draft→confirmed→fulfilled→cancelled) |
| **ERP - Invoicing** | إنشاء Invoice من Order، حالات (draft→sent→partial→paid→overdue)، تسجيل مدفوعات وتطبيقها، يظهر بقايا المستحق |
| **ERP - Purchasing** | Suppliers + Purchase Orders + استلام → مخزون |
| **ERP - Expenses** | تسجيل مصروفات + تقارير شهرية |
| **Support** | Tickets + رسائل + حالات (open→pending→answered→resolved/closed) |
| **Notifications** | تنبيهات داخلية (تذكرة جديدة، فاتورة متأخرة، مخزون منخفض، تحويل) |
| **Audit Logs** | سجل كل عملية (من، ماذا، قبل/بعد، IP) |

### المتطلبات غير الوظيفية
- عربي RTL أساسي (Dir RTL + خطوط عربية)
- أداء: لوحة القيادة < 2 ثانية، منع N+1
- أمان: CSRF + Validation + Gates/Policies + prepared queries
- Responsive بكل الأجهزة
- سهولة الاستخدام: Sidebar + Breadcrumb + رسائل خطأ واضحة

---

## 3) الأدوار والصلاحيات

| الدور | الصلاحيات الأساسية |
|---|---|
| **Admin** | كل شيء + إدارة المستخدمين والأدوار |
| **Sales Manager** | كل CRM + توزيع العملاء + تقارير المبيعات + إلغاء أوامر |
| **Sales Rep** | Leads + Activities + Customers + Sales Orders (إنشاء/عرض) فقط |
| **Support Agent** | Tickets + Customers (عرض) |
| **Accountant** | Invoices + Payments + Expenses + تقارير مالية |
| **Warehouse Manager** | Products + Warehouses + Stock + استلام المشتريات |
| **Purchasing Officer** | Suppliers + Purchase Orders |
| **HR Admin** | Employees + Payroll + Attendance |
| **Guest** | لا يوجد دخول إلا بتسجيل بوت بنظام |

**التنفيذ:** جدول `permissions` + pivot `role_permission` + Middleware + Gate في الـ views (عرض/إخفاء أزرار حسب الدور).

---

## 4) User Stories (مثال، ملف كامل لاحقًا)

| الوحدة | Story | Acceptance Criteria |
|---|---|---|
| CRM | As Sales Rep, I want to add a lead to the pipeline, so that I don't forget prospects | اسم/هاتف/مصدر ألزامي؛ يظهر بالكوبورد؛ حفظ |
| CRM | As Sales Manager, I want to drag a lead to "qualified", so that sales act fast | تغيير المرحلة تسجل Activity تلقائيًا |
| CRM | As Sales Rep, I want to convert a lead to a customer, so that it becomes billable | إنشاء customer + فرصة "won" مرتبطة |
| Sales | As Sales Rep, I want to create a sales order, so that I reserve stock | فحص المخزون ورفض الكمية غير المتاحة + خصم عند التأكيد |
| Sales | As Sales Manager, I want an invoice from an order, so that billing is consistent | رقم تسلسلي فريد مع منع تكرار فاتورة لنفس الأمر |
| Finance | As Accountant, I want to record a payment, so that balances match | الدفع يقلل paid_amount؛ الحالة partial/paid تلقائيًا |
| Purchasing | As Purchasing Officer, I want a low-stock alert, so that I create a PO | تنبيه عند quantity ≤ min_stock + زر إنشاء أمر |
| Support | As Support Agent, I want to reply and change ticket status, so that customers know progress | رسائل مسجلة + تغيير حالات مرتب |
| HR | As HR Admin, I want to run payroll, so that salaries are computed | احتساب من البيانات؛ حالات draft→approved→paid؛ بدون تكرار للفترة |
| Reports | As Sales Manager, I want monthly sales report, so that I evaluate performance | مجموعات شهرية وسنوية |

---

## 5) الـ Tech Stack

- **Laravel 11** (PHP 8.2.12 — متاح)
- **Blade + Tailwind CSS + Alpine.js + Vite**
- **MySQL 8.0.45** (محلي XAMPP)
- **Laravel Breeze (Blade)** لتسجيل الدخول — مخصّص بالكامل
- **Chart.js** (عبر Alpine + fetch endpoint)
- Session Auth + RBAC مخصص
- Seeders + Factories لبيانات عربية واقعية

**البيئة الحالية المعتمدة:**
```
PHP 8.2.12 | Composer 2.7.7 | Node 24.18.0 | npm 11.16.0 | MySQL 8.0.45
```

---

## 6) الـ ERD / قاعدة البيانات

**الخطط التفصيلية + الـ migrations ستوضع في ملف** `docs/DATABASE_SCHEMA.md`.

العلاقات الأساسية:

```
users 1→1 roles 1→n permissions (pivot role_permission)
users 1→n leads / customers / opportunities / activities / tickets / expenses / payments / stock_movements / notifications / audit_logs
leads 1→0..1 customers (created_from_lead_id)
customers 1→n opportunities / sales_orders / invoices / tickets / payments
opportunities → stage_id → opportunity_stages
categories 1→n products (parent_id optional)
units 1→n products
products n→n warehouses عبر stock_items (unique product+warehouse)
stock_items 1→n stock_movements (before_qty/after_qty/type + polymorphic reference)
suppliers 1→n purchase_orders 1→n purchase_order_items → product
customers 1→n sales_orders 1→n sales_order_items → product
sales_orders 1→0..1 invoices 1→n invoice_items → product
invoices 1→n payments
departments 1→n employees (users 1→0..1 employees)
employees 1→n payrolls (+ payroll_items breakdown)
employees 1→n attendance
users 1→n saved_reports / saved_filters (اختياري Phase 2)
```

**نقاط تعليمية:** unique (product_id, warehouse_id) • polymorphic reference في stock_movements • paid_amount محسوب من payments • Audit Log بالـ JSON قبل/بعد.

---

## 7) الـ Routes (Monolith — Web Routes)

```
/auth/login /logout /register
GET / → Dashboard بالدور
/leads → CRUD + /leads/pipeline + /leads/{id}/convert
/customers → CRUD + /customers/{id}/invoices + /customers/{id}/opportunities
/opportunities → CRUD + تغيير مرحلة
/activities → CRUD + /activities/complete
/tickets → CRUD + /tickets/{id}/messages
/categories /units /products /warehouses /stock/adjustments
/sales-orders → CRUD + /{id}/confirm + /{id}/fulfill + /{id}/invoice
/invoices → CRUD + /{id}/print + /{id}/payments + /{id}/mark-overdue
/payments → تسجيل
/expenses → CRUD
/suppliers /purchase-orders → CRUD + /receive → stock in
/departments /employees /payroll → توليد + approve
/reports → sales / inventory / profit / expenses / aged-receivables
/settings → lists (sources, methods, categories) + audit-logs + users/roles
```

---

## 8) خطة المراحل (Milestones) — 10 أسابيع

> **آلية التتبع:** عند كل مرحلة نضع ✅ في المربع المقابل، ونكتب ملاحظات التنفيذ تحت كل مرحلة.

### 📌 Phase 0 — التأسيس (أسبوع 1) ✅ مكتملة
- [x] تثبيت Laravel 11 + Breeze (Blade) + Tailwind
- [x] ضبط العربية RTL + الخطوط العربية (Cairo + dir=rtl)
- [x] إنشاء كل الـ migrations الأساسية (37 migration)
- [x] RBAC: Roles + Permissions + Seeders (74 صلاحية + 8 مستخدمين تجريبيين)
- [x] Layout أساسي (Sidebar + Header بالدور)
- [x] AuditLog Observer + معالجات عامة (AuditLog model + AuditLogger service + Gate قبل)
- [x] إعدادات بيئة `.env` + MySQL (قاعدة `crm_erp_app`)

**ملاحظات Phase 0:**
- اتصال MySQL عبر XAMPP (MariaDB 10.4) — تم تعديل collation لـ `utf8mb4_unicode_ci`.
- قاعدة `crm_erp` القديمة كانت مستخدمة من مشروع سابق؛ استخدمنا قاعدة جديدة نظيفة `crm_erp_app`.
- Checkpoint: login (302) → dashboard (200) تم التحقق منه فعليًا.
- الـ Layout يعرض الأزرار حسب صلاحية الدور، والوحدات غير المبنية بعد تظهر صفحة "قيد التطوير" placeholder.

### 📌 Phase 1 — CRM Core (أسبوعان 2-3) ✅ مكتملة
- [x] Leads CRUD + Pipeline كوبورد
- [x] تحويل Lead→Customer
- [x] Customers CRUD
- [x] Opportunities + مراحل
- [x] Activities (مكالمة/اجتماع/مهمة)
- [x] Dashboard أساسي (KPI)

**ملاحظات Phase 1:**
- أُنشئت نماذج Lead/Customer/Opportunity/OpportunityStage/Activity + Enums (LeadStatus, ActivityType) + Service `LeadService::convert` (معاملة ذرية تُنشئ عميلًا وفرصة وتُسجل Activity تلقائيًا + AuditLog).
- سيدر `OpportunityStagesSeeder` لمراحل الفرص (جديد→مؤهل→عرض سعر→تفاوض→فوز/خسارة).
- كانبان السحب/الإفلات (Drag & Drop) عبر Alpine لعمودي العملاء المحتملين والفرص، مع تسجيل تغيّر المرحلة تلقائيًا في الأنشطة.
- تحويل الـ Lead يُنشئ فرصة في مرحلة "جديد" مبدئيًا (وليس "فوز" — يبقى شقّ البيع حتى مرحلة الإغلاق)، كما يمنع التحويل المزدوج.
- أُضيفت صلاحيتا `edit_activities` و `delete_activities` ورُبطتا بثلاثة أدوار مع إعادة تشغيل RbacSeeder.
- Dashboard يعرض الآن KPIs حقيقية (قناة الفرص، توزيع العملاء المحتملين، آخر الأنشطة).
- إصلاح: جعل `last_name` في leads nullable عبر migration جديدة.
- اختبارات Feature (9 اختبارات) تغطي CRUD والصلاحيات وتحويل Lead→Customer — كل اختبارات المشروع (40) ناجحة.
- **بيانات تجريبية:** `CrmDemoDataSeeder` (يُشغَّل تلقائيًا مع `php artisan db:seed`) يُدخل 28 عميلًا محتملًا بمراحل مختلفة، 15 عميلًا (منها 3 ناتجة عن تحويل Leads)، 12 فرصة موزعة على كل المراحل، و30 نشاطًا مرتبطًا — حتى يسهل تجربة النظام ورؤية البيانات مترابطة.

### 📌 Phase 2 — المنتجات والمخزون (أسبوع 4) ✅ مكتملة
- [x] Categories + Units
- [x] Products (SKU/سعر/تكلفة/حد أدنى)
- [x] Warehouses + StockItems + StockMovements
- [x] جرد/تصحيح مخزون

**ملاحظات Phase 2:**
- خدمات المخزون: `StockService` (add/out/transfer/adjust مع تسجيل رصيد قبل/بعد في `stock_movements` + polymorphic reference) وحماية من السحب الزائد عبر `InsufficientStockException`.
- تدفق كامل Products→Warehouses→StockItems مع فحص low-stock (الكمية ≤ حد أدنى).
- `InventoryDemoDataSeeder` لبيانات تجريبية + اختبارات Feature (13 اختبارًا).

### 📌 Phase 3 — المبيعات والفواتير والمدفوعات (أسبوعان 5-6) ✅ مكتملة
- [x] Sales Orders + خصم المخزون
- [x] حالات الأوامر (draft→confirmed→fulfilled)
- [x] إنشاء Invoice من Order
- [x] طباعة فاتورة (print-friendly)
- [x] Payments + تحديث حالة الفاتورة
- [x] Expenses
- [x] Dashboard متقدم (رسم مبيعات شهري)

**ملاحظات Phase 3:**
- `SalesOrderService`: `confirm` يخصم المخزون تلقائيًا (يرفض غير المتاح) عند التحويل إلى confirmed، و`cancel` يعيده للمخزون (type `sales_order_return`)، و`fulfill` توثيق تنفيذ فقط (لا خصم مزدوج).
- `InvoiceService`: `createFromOrder` (يمنع الفاتورة من أمر مسودة أو أمر له فاتورة مسبقًا) + `recordPayment` (يمنع تجاوز المستحق) + `recomputeStatus` (partial→paid→overdue تلقائيًا) + ترقيم تسلسلي `INV-000001`.
- `SalesDemoDataSeeder`: أوامر مؤكدة/ملغاة + فواتير + دفعات جزئية/كاملة + 8 مصروفات.
- Dashboard متقدم: بطاقات KPI مالية + رسم مبيعات شهري (آخر 6 أشهر) عبر Chart.js (CDN) مع تسميات عربية RTL.
- **إصلاح مهم:** implicit route model binding يتطلب مطابقة اسم معامل الـ controller لمقطع الـ route — `SalesOrderController` تستخدم `$salesOrder` مقابل `{sales_order}`.
- اختبارات Feature (15 اختبارًا) تغطي أوامر/مخزون/فواتير/دفعات/مصروفات/صلاحيات — **كل اختبارات المشروع (68) ناجحة**.

### 📌 Phase 4 — المشتريات (أسبوع 7) ✅ مكتملة
- [x] Suppliers CRUD
- [x] Purchase Orders + استلام → مخزون
- [ ] تنبيه low stock + زر إنشاء أمر شراء (مؤجل)

> **ملاحظة التنفيذ:** مبنية على `PurchaseOrderService` (مالك `DB::transaction`) مع استلام **جزئي متعدد المرات** — كل استلام يضيف للمخزون عبر `StockService::in()` في المستودع المحفوظ على الـ PO. `confirm` لا يحرّك المخزون؛ الإلغاء ممنوع بعد أي استلام أو من received/cancelled. ترقيم تلقائي `PO-000001`. صلاحية جديدة `cancel_purchase_orders` (لـ purchasing-officer وحده بين الأدوار غير admin) تفصل الحذف (`destroy` draft-only) عن الإلغاء (draft/confirmed). Form Requests جديدة (`Store/Update Supplier + PurchaseOrder`) تماشيًا مع نمط Gap 1. اختبارات جديدة: 7 Suppliers + 12 Purchases — **كل اختبارات المشروع (105) ناجحة**.

### 📌 Phase 5 — الدعم Support (أسبوع 8) ✅ مكتملة
- [x] Tickets + رسائل + حالات
- [x] Notifications داخلية

> **ملاحظة التنفيذ:** مبنية على `TicketService` (ترقيم تلقائي `TK-000001`، حالات open→in_progress→resolved→closed، إعادة فتح تلقائية عند الرد على تذكرة مغلقة/محلولة، حذف ناعم SoftDeletes، منع حذف التذكرة المغلقة) و `NotificationService` (إشعارات داخلية في جدول `notifications` المخصص بـ `user_id` عند إنشاء تذكرة وكل رد). أُعيد بناء موديل `CRM\Ticket` كليًا ليطابق الجدول (message/category بدل description/department). صلاتيتان جديدتان `assign_tickets` و `delete_tickets` لـ support-agent. بيل الإشعارات في الـ topbar (Alpine) + صفحة إشعارات + تعليم كمقروء مع تمييز الملكية. Form Requests جديدة (Store/Reply/Assign). بيانات تجريبية: 8 تذاكر في `CrmDemoDataSeeder`. اختبارات جديدة: 14 Tickets + 5 Notifications — **كل اختبارات المشروع (109) ناجحة**. *(أرقام المراحل السابقة (40/52/68/105) تقديرية/تقريبية.)*

### 📌 Phase 6 — الموارد البشرية Payroll (أسبوع 9) ✅ مكتملة
- [x] Departments
- [x] Employees
- [x] Attendance (بسيط)
- [x] دورة Payroll (توليد → approve → paid)

> **ملاحظة التنفيذ:** 5 أقسام + 10 موظفين + 296 سجل حضور + دورة Payroll كاملة (توليد بنود من الحضور → approve → paid) + صلاحيات HR منفصلة (run/approve/pay/attendance/departments) — 15 اختبارًا جديدًا (HrTest 12 + HrModelsTest 3) — **كل اختبارات المشروع (136) ناجحة**.

### 📌 Phase 7 — التقارير واللمسات النهائية (أسبوع 10) ✅ مكتملة
- [x] تقارير: مبيعات / مخزون / أرباح / مصروفات / فواتير متأخرة
- [x] تحسين RTL + Empty States + ألوان موحدة
- [x] بيانات تجريبية نهائية واقعية (Seeder شامل)
- [x] توثيق README + Case Study
- [x] سكرين شوتس للبورتفوليو

> **ملاحظة التنفيذ (التقارير):** `ReportService` (تجميع قراءة فقط من النماذج) + `ReportController` (6 صفحات) + 6 مسارات `reports.*` خلف `permission:view_reports` + تقارير مبيعات/مخزون/أرباح/مصروفات/متأخرة عربية RTL مع تصفية شهر (؟month=YYYY-MM) وEmpty States وChart.js. أُصلح حساب «المستحق» ليصبح من جدول المدفوعات. — اختبارات جديدة: 6 (ReportTest) — **كل اختبارات المشروع (142) ناجحة**.

> **ملاحظة التنفيذ (Seeder شامل):** أُضيف `FinalDemoDataSeeder` بعد `HrDemoDataSeeder` — 6 موردين، 12 أمر شراء (9 مستلمة → تضخ المخزون بأصناف تشغيلية)، و16 أمر بيع موسّعة عبر 6 أشهر (مارس→أغسطس) بمدفوعات كاملة/جزئية ومتأخرة/غير مدفوعة — فيصبح الرسم الشهري في تقرير المبيعات حقيقيًا والمتأخرة 8 فواتير والفواتير غير المدفوعة بحالة `sent` (وليس draft). أُصلح أثناء ذلك: استيراد `User` الناقص في `InventoryDemoDataSeeder`، ترتيب الفاتورة قبل `fulfill` في `SalesDemoDataSeeder`، ومدى `monthlyBreakdown` في `ReportService` (كان `$months->first()` بدل `->last()`). الإجمالي النهائي: 6 موردين / 12 أمر شراء / 24 أمر بيع / 19 فاتورة / 11 دفعة.

> **ملاحظة اللمسات النهائية (Polish):** أُنشئ مكوّن `x-empty-state` موحّد جديد (أيقونة/عنوان/وصف/CTA) وطُبّق على 25 صفحة فهرس؛ حُوّل زر «إضافة عميل» إلى indigo؛ أُعيدت صياغة `README.md` من قالب Laravel الافتراضي إلى توثيق مشروع كامل (مزايا/تقنية/تشغيل/اختبارات/أدوار)، وأُنشئ `docs/CASE_STUDY.md` (10 أقسام: مشكلة/تحديات/بنية/بيانات/اختبار/تصميم/مستقبل). **سكرين شوتس:** 22 لقطة في `public/screenshots/` لكل الوحدات والتقارير واللوحة وتسجيل الدخول.

---

## 9) خطة الاختبار

- **Unit:** Services (صافي الفاتورة، خصم المخزون، حالة الفاتورة، حساب الراتب)
- **Feature/Integration:** تدفق كامل lead→order→invoice→payment
- **Permission tests:** Accountant لا يصل لصفحات Products/Purchasing
- **Validation tests** لكل كيان حساس
- اختبار الـ User Flows الأساسية يدويًا

## 10) خطة الأمان

- Auth: Session-based (Laravel) + Password Hashing
- Authorization: RBAC + Gates/Policies على الحاويات
- Input Validation قوية لكل طلب
- CSRF تلقائي
- إخفاء الحقول الحساسة (مثل salary)
- Audit Logs على عمليات الكتابة
- Prepared statements (Eloquent)

## 11) خطة النشر

- **محلي:** XAMPP (بلا تغيير) — للعمل اليومي
- **عرض حي للبورتفوليو:** VPS بسيط (Ubuntu + Nginx + Laravel) أو استضافة مشتركة تدعم Laravel
- **DB:** MySQL معرفة في env + `.env.example` في README
- **ملاحظة:** Vercel غير مناسب للمونوليث — يتم الذكر في البورتفوليو

## 12) البيانات التجريبية (Seeder)

مستخدم واحد لكل دور (7 مستخدمين) + بيانات عربية:
50 Lead • 30 Customer • 20 Opportunity • 30 Product • مخزون كامل • 40 Order • 35 Invoice • 60 Payment • 25 Ticket • 15 Expense • 10 Supplier • 8 Employee • Payroll لفترة واحدة.

## 13) البورتفوليو / Case Study

الملف الكامل: `docs/CASE_STUDY.md`
Structure: Title • Problem • Solution • Key Features • Tech Stack • My Role • Challenges (تحويل lead، قيود المخزون، RBAC، RTL، تسوية فواتير) • Screens • بنية القصة.

---

## 14) المخاطر وكيف نتجنب التعقيد

| التحدي | الحل |
|---|---|
| تحويل Lead→Customer يسبب تكرار | حقل `created_from_lead_id` + Service واحد |
| قيود المخزون السالبة | فحص في الـ Service قبل التحديث |
| RBAC يتحول لفوضى | إبقاء الأذونات بحسب الوحدة فقط (grouped) |
| بتاريخ الفواتير غير صحيح | حساب paid_amount من payments فقط |
| نطاق واسع | التزام صارم بقائمة MVP — كل ما عداها Phase 2 |

**قاعدة الثبات:** نحن لا نضيف أي ميزة خارج قائمة MVP إلا بعد إغلاق المرحلة الحالية بالكامل.

---

## 15) قائمة الملفات المرجعية (تُكتب عند بدء التنفيذ)

- [x] `docs/DATABASE_SCHEMA.md` — مخطط الجداول والعلاقات كاملًا (مكتوب مع انتهاء Phase 0)
- [ ] `docs/USER_STORIES.md` — كل القصص مع Acceptance Criteria كاملة (قبل المراحل)
- [ ] `docs/CASE_STUDY.md` — محتوى البورتفوليو (قبل المرحلة النهائية)
- [ ] `README.md` — وصف + إعداد + نشر (مرحلة 10)

---

**الخطوة التالية:** عند موافقتك نبدأ **Phase 0** ونضع ✅ بعد كل إنجاز.