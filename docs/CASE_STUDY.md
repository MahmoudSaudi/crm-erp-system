# Case Study — نظام CRM + ERP عربي (Laravel 11)

> دراسة حالة لمشروع بورتفوليو: دمج إدارة علاقات العملاء وتخطيط موارد المؤسسة في نظام واحد بواجهة عربية RTL.

---

## 1) الملخص التنفيذي

نظام ويب متكامل **باللغة العربية (RTL)** يجمع CRM (العملاء المحتملون، العملاء، الفرص، الأنشطة، تذاكر الدعم) مع ERP (المبيعات والفواتير والمدفوعات، الموردون وأوامر الشراء، المنتجات والمخزون متعدد المستودعات، الموارد البشرية والرواتب) وصفحة تقارير مركزية. بني بأدوار وصلاحيات تفصيلية (8 أدوار / 50+ صلاحية)، وسجل تدقيق شامل، وأُنجز بـ **142 اختبارًا ناجحًا (414 assertion)**.

**النتيجة:** نظام صال للتشغيل وجاهز للعرض — من 0 إلى النهاية في 10 مراحل موثقة بـ `docs/PLAN.md` و `phases/`.

---

## 2) المشكلة والهدف

- الشركات الصغرى تخسر الوقت بتشتت البيانات بين Excel وبرامج منفصلة (CRM، محاسبة، مخزون، رواتب).
- الحلول الجاهزة غربية الواجهة أو معقدة/باهظة، ولا تتناسب مع بيئة العمل العربية وضرورة الصلاحيات الدقيقة والشفافية (تدقيق).

**الهدف:** نظام واحد مركزي: إدارة علاقات العملاء من **Lead → عميل → فرصة → أمر بيع → فاتورة → دفعة**، مع تغذية كل حلقة للمخزون والمحاسبة والتقارير، وواجهة عربية RTL نهائية.

---

## 3) التحديات والحلول

| التحدي | الحل |
|---|---|
| تنوع الوحدات وتداخلها | فصل المنطق في **Services** (Stock, SalesOrder, Invoice, PurchaseOrder, Payroll, Ticket, Report) بدل منطق متراكم في Controllers |
| صلاحيات دقيقة لكل قسم | نظام **Roles + Permissions (مسطحة بالـ slug)** مع Middleware `can:permission.*` والتحقق داخل الـ views بـ `@can` |
| اتساق المخزون عند التأكيد | التحقق من الكمية **قبل** الخصم داخل معاملة واحدة (Transaction) ورفض فوق المتاح، مع حركات مخزون قابلة للتتبع |
| دورة رواتب صحيحة | `generate → updateAmounts → approve → pay` مع منع تعديل/حذف المعتمد والمدفوع وسجل تدقيق لكل انتقال |
| فواتير «متأخرة» دقيقة | إعادة حساب حالة الفاتورة تلقائيًا من مدفوعاتها (paid_amount) بدل تحديثات يدوية |
| EVitch RTL + Dark | `dir="rtl"` في الـ layouts، خط Cairo، وتبديل الوضع الليلي عبر Alpine.js + localStorage |
| اختبار كل ذلك | 142 اختبارًا عبر RefreshDatabase يغطّي الصلاحيات (200/403)، دورات الأوامر والرواتب، الحسابات، الإشعارات، والتقارير |

---

## 4) التصميم والمخرجات التقنية

### البنية (Structure)
```
app/
├── Enums/       حالات الأعمال المركزية (string enum) مع label()/color() عربيتين
├── Models/
│   ├── CRM/     Lead · Customer · Opportunity · Activity · Ticket · SalesOrder
│   ├── ERP/     Product · Category · Unit · Warehouse · StockItem/Movement ·
│   │            Supplier · PurchaseOrder · Invoice · Payment · Expense
│   └── HR/      Department · Employee · Attendance · Payroll · PayrollItem
├── Services/    منطق الأعمال القابل لإعادة الاستخدام + AuditLogger
└── Http/Controllers/  وحدات رقيقة (Thin Controllers)
```

### الأرقام
- **8 أدوار** (admin, sales-manager, sales-rep, support-agent, accountant, warehouse-manager, purchasing-officer, hr-admin).
- **50+ صلاحية** موزعة على 11 مجموعة (dashboard, leads, customers, opportunities, activities, tickets, products, warehouses, suppliers, purchase_orders, sales_orders, invoices, payments, expenses, payroll, attendance, reports, users, roles, audit_logs).
- **142 اختبارًا ناجحًا** (Feature، MySQL عبر `crm_erp_testing`).

### سير تنفيذ الأعمال
1. **مبيعات**: إنشاء أمر → تأكيد (يخفض المخزون في مستودع الافتراضي ويسجل AuditLog) → إنشاء فاتورة من الأمر المؤكد → تسجيل دفعة (تعيد حساب حالة الفاتورة: partial/paid/overdue).
2. **مشتريات**: أمر شراء → تأكيد → استلام جزئي/كلي (يزيد المخزون عبر حركات `purchase_order`).
3. **رواتب**: توليد لكل الموظفين النشطين بلا راتب سابق → ضبط البنود وإعادة حساب الصافي → اعتماد → صرف.
4. **دعم**: تذكرة → ردود خارجية/داخلية (الداخلية ممنوعة عمن لا يملك `reply_tickets` حتى لو يملك عرض التذكرة) → إشعارات للمشرفين والمرتبطين.

### التقارير
`ReportService` قراءة فقط من النماذج: saleSummary (رسم Chart.js لآخر 6 أشهر)، inventorySummary، profitSummary، expenseSummary، overdueInvoices — مع تصفية شهر `?month=YYYY-MM` وعدم الحاجة لأي تغيير في schema.

---

## 5) هندسة البيانات (Data)

- **30+ Migration** حديدي المعاملة للترتيب (من users → roles/permissions → CRM → ERP → HR → audit_logs).
- **SoftDeletes** على الكيانات الحساسة (منتجات، موردين، أوامر، فواتير) مع الحفاظ على المرجعيات.
- **Seeders**: `RbacSeeder` (أدوار/مستخدمون)، ثم بيانات Demo لكل وحدة، ثم `FinalDemoDataSeeder` الذي يوزّع 6 أشهر من مبيعات ومشتريات واقعية لتملأ التقارير — كلها **idempotent** (تُتجاوز عند وجود بيانات).

---

## 6) البيانات التجريبية (Demo)

| القسم | الكمية |
|---|---|
| عملاء محتملون | 28 |
| عملاء | 15 (+ 3 من تحويلات) |
| فرص | 12 |
| أنشطة / تذاكر | 30 / 8 |
| موردون / أوامر شراء | 6 / 12 (9 مستلمة) |
| منتجات / تصنيفات / مستودعات | 14 / 8 / 3 |
| أوامر بيع / فواتير / مدفوعات | 24 / 19 / 11 |
| موظفون / أقسام / رواتب | 10 / 5 / 9 |
| مصروفات | 8 |

الرسم الشهري في تقرير المبيعات يعرض 6 أشهر فعلية (مثل: أغسطس 10,059 — يوليو 16,174 — يونيو 18,450 …) والتقارير كلها ذات أرقام حقيقية.

---

## 7) الاختبار (Testing)

- **142 اختبارًا / 414 assertion** تقريبًا، موزعة على: RbacTest, CrmLeadTest, SalesTest, InventoryTest, PurchaseTest, SupplierTest, TicketTest, HrTest, HrModelsTest, NotificationTest, SettingsTest, ReportTest.
- كل اختبار يشغّل `RbacSeeder` ثم يبني بياناته عبر `RefreshDatabase` فيsegment اختبار MySQL (`crm_erp_testing`).
- يغطي: نجاح/رفض الصلاحيات (200/403)، انتقالات حالة الأوامر/الرواتب، حسابات المخزون والمال، قيود الـ audit، إشعارات التذاكر، ومنطق التقارير.

```bash
php artisan test   # Tests: 142 passed (414 assertions)
```

---

## 8) التصميم المرئي (Visual)

- واجهة عربية بالكامل RTL بخط **Cairo**.
- **Dark Mode** كامل (تبديل فوري عبر Alpine + localStorage + `prefers-color-scheme`).
- لوحة تحكم (Dashboard) ببطاقات إحصائية، وإعداد `stat-card` component.
- **Empty States موحدة** (مكوّن `x-empty-state`) على كل الفهارس.
- ألوان متماسكة: Indigo للإجراءات الرئيسية، Emerald/Rose/Sky للإشارات الدلالية، وشريط جانبي داكن متناسق.

---

## 9) نتائج وتعريف بالمشروع

- **نظام واحد مغطّى بالكامل**: CRM + فرص + مبيعات + فواتير + مشتريات + مخزون + دعم + HR/رواتب + تقارير.
- **قابل للإعادة** عبر `php artisan migrate:fresh --seed` (بيئة demo واحدة للتشغيل والعرض).
- **جاهز للتوسع**: بنية تميcor enums/services، واختبارات، ووثائق لكل مرحلة.

---

## 10) خارطة المستقبل

- تقارير قابلة للتصدير (PDF/Excel) وتوسعة محرك الربط مع النظام المحاسبي.
- الوحدات المتبقية من Phase 7: تحسين RTL/empty states إضافي، سكرين شوتس البورتفوليو، README، وقد أُنجز القسم الأكبر.
- ترشيد نشر (Docker/Sail) وخط CI لـ `php artisan test` و Pint.

---

*تاريخ الإنجاز: أغسطس 2026 · Laravel 11 · PHP 8.2 · MySQL · Tailwind · Alpine.js · PHPUnit*