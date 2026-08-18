# المرحلة 3 — المبيعات والفواتير والمدفوعات (Phase 3) ✅ مكتملة

**المدة:** أسبوعان (5-6)
**الهدف:** بناء دورة المبيعات الكاملة — أوامر بيع مع خصم مخزون ذري، فواتير، دفعات، مصروفات، ولوحة تحكم مالية متقدمة برسم مبيعات شهري.

---

## ما تم تنفيذه في هذه المرحلة

### 1) نماذج (Models) كاملة
| النموذج | ما يخزّنه |
|---|---|
| **SalesOrder** | أمر بيع: رقم تسلسلي `SO-000001`، العميل، الحالة، تاريخ الأمر/التسليم، الخصم والشحن والضريبة، الإجماليات، منشئه |
| **SalesOrderItem** | سطر أمر: المنتج (مع لقطة اسم المنتج)، الكمية، سعر الوحدة، الإجمالي |
| **Invoice** | فاتورة: رقم تسلسلي `INV-000001`، مرتبطة بأمر مؤكد، الحالة، الإجماليات، `paid_amount` محسوب من الدفعات، `dueAmount()` |
| **InvoiceItem** | سطر فاتورة منسوخ من سطر الأمر (وصف + كمية + سعر + إجمالي) |
| **Payment** | دفعة: الفاتورة، العميل، المبلغ، الطريقة، المرجع، تاريخ الدفع، الحالة |
| **Expense** | مصروف: الفئة، المبلغ، الوصف، التاريخ، قابل للتعويض، **Soft Deletes** |

**الحالات (Enums):** `SalesOrderStatus` (draft→confirmed→fulfilled→cancelled) • `InvoiceStatus` (draft→sent→partial→paid→overdue→cancelled) • `PaymentMethod` (cash/credit_card/bank_transfer/cheque/online) • كلها بتسميات وألوان عربية تُعرض في الـ views.

---

### 2) خدمة أوامر البيع (SalesOrderService)
| العملية | الوصف |
|---|---|
| **`recalculateTotals()`** | إجمالي = (البنود − الخصم) + الشحن + ضريبة (معدل 0 حاليًا) |
| **`confirm()`** | فحص كفاية المخزون (يرفض الكمية غير المتاحة بـ `InsufficientStockException`) ثم يخصم المخزون **تلقائيًا** لكل سطر (حركة `sales_order` خروج) في معاملة ذرية |
| **`cancel()`** | يعيد المخزون للمستودع (حركة `sales_order_return` دخول) للمؤكد فقط، في معاملة ذرية |
| **`fulfill()`** | توثيق تنفيذ فقط (خالٍ من الخصم المزدوج — المخزون خُصم عند التأكيد) |

---

### 3) خدمة الفواتير (InvoiceService)
- **`createFromOrder()`**: يمنع الفاتورة من أمر **مسودة** أو أمر **له فاتورة مسبقًا** (رمي RuntimeException → رسالة error)، وينسخ الأسطر ويُرجع الفاتورة.
- **`recordPayment()`**: يمنع المبلغ ≤ 0 وتجاوز المستحق، يسجّل الدفعة ويعيد حساب الحالة.
- **`recomputeStatus()`**: `paid_amount` يُحسب من الدفعات فقط → الحالة تتحول تلقائيًا: جزئي → مدفوعة، أو متأخرة عند فوات `due_date`.
- **`nextNumber()`**: ترقيم تسلسلي `INV-000001` عبر max id.

---

### 4) الصفحات (Views)
**أوامر البيع:**
- فهرس: بحث + تصفية بالحالة + شارة حالة ملونة.
- إنشاء/تعديل: **صفوف أصناف ديناميكية بـ Alpine** — اختيار منتج (اسم · SKU)، إجمالي السطر حي، إضافة/حذف صفوف.
- عرض: ملخص بالحالة، أزرار تأكيد/تنفيذ/إلغاء/حذف **محمية بالصلاحيات**، الأصناف، رابط الفاتورة، ملاحظات.

**الفواتير:**
- فهرس: بطاقات إجمالي/محصّل/مستحق + فلاتر.
- إنشاء: قائمة أوامر **مؤكدة وغير مفوترة** فقط (مع حالة فارغة).
- عرض: تلخيص مالي، الأصناف، **نموذج تسجيل دفعة** مدمج، قائمة الدفعات.
- طباعة: صفحة **print-friendly** مستقلة بلا Layout.

**الدفعات:** فهرس بفلتر الطريقة • إنشاء باختيار فاتورة تُعرض بقيمة مستحقة (لا يُعرض إلا المتاح بمستحق).

**المصروفات:** فهرس بفلتر فئة/شهر + بطاقة إجمالي • إنشاء/تعديل • حذف ناعم.

**لوحة التحكم (Dashboard):**
- بطاقات KPI مالية: إجمالي مبيعات، محصّل، مستحق، مصروفات (روابط للصفحات الحيوية).
- **رسم مبيعات شهري** (آخر 6 أشهر) عبر Chart.js (CDN): إيرادات الفواتير + المدفوعات + المصروفات، بتسميات عربية RTL وتلميح `ar-EG`.

---

### 5) الصلاحيات (Permissions)
| مقطع | الصلاحيات | لمن |
|---|---|---|
| أوامر البيع | `view/create/confirm/fulfill/cancel_sales_orders` | المدير (كلها) + مندوب المبيعات (عرض/إنشاء) |
| الفواتير | `view/create_invoices` | المحاسب والمدير |
| الدفعات | `view/create_payments` | المحاسب والمدير |
| المصروفات | `view/create/edit/delete_expenses` | المحاسب والمدير |
| الحذف | `delete_sales_orders` (إلغاء فعلي) | المدير |

---

### 6) ربط الصفحات الحالية
- صفحة **العميل** تعرض تبويبات فواتير وأوامر بيع ودفعات مرتبطة بالنماذج الجديدة.
- **الـ Sidebar** فيه روابط sales-orders / invoices / payments / expenses.

---

### 7) بيانات تجريبية (SalesDemoDataSeeder)
يُشغَّل تلقائيًا مع `php artisan db:seed` (محمي عند وجود أوامر):
- أوامر بيع عبر 3 مستخدمين (مدير/مندوب/محاسب) في أيام متباعدة وحالات متنوعة (مؤكد/منفذ/ملغي/مسودة) مع خصم وشحن.
- تأكيدها يخصم المخزون تلقائيًا، ثم فواتير من المؤكد، ودفعات جزئية/كاملة، و8 مصروفات موزعة على الأشهر الماضية لعرض الرسم البياني.

---

### 8) الاختبارات (15 اختبار Feature)
- إنشاء أمر بيع بأصناف (إجمالي صحيح مع خصم/شحن).
- ترقيم متسلسل للأوامر.
- التأكيد **يخصم المخزون** مع الحركة المسجلة، ويرفض المخزون غير المتاح.
- الإلغاء **يعيد المخزون**.
- التنفيذ يحوّل الحالة دون خصم مزدوج.
- المؤكد لا يُعدَّل ولا يُحذف إلا بالصلاحية المناسبة.
- منع `support` من أوامر البيع.
- فاتورة من أمر مؤكد برقم `INV-000001`، ورفضها من أمر مسودة أو أمر مفوتر مسبقًا.
- الدفعات: جزئية تغيّر المستحق، كاملة تحوّل الحالة إلى مدفوعة، ورفض تجاوز المستحق.
- CRUD مصروفات مع حذف ناعم.
- تصفّح كل صفحات المرحلة كـ admin.

**إصلاح جوهري في هذه المرحلة:** implicit route model binding يقع إذا اختلف اسم معامل الـ controller عن مقطع الـ route — مسارات `{sales_order}` تتطلب معاملًا اسمه `$salesOrder` في `SalesOrderController` (`$invoice` و `$expense` مطابقة مسبقًا).

**النتيجة:** كل اختبارات المشروع (68) ناجحة.

---

## كيف تتجرب بنفسك
```bash
php artisan db:seed   # إن بُدئت قاعدة فارغة
php artisan serve
```
سجّل الدخول بـ `admin@crm.test / password` ثم جرّب:
1. **أوامر البيع** → أنشئ أمرًا بأصناف، ثم **أكّد** — ولاحظ انخفاض مخزون المنتجات تلقائيًا، و**ألغِ** أمرًا مؤكدًا ليعود المخزون.
2. **الفواتير** → أنشئ فاتورة من أمر مؤكد، وجرّب **طباعتها**.
3. **الدفعات** → سجّل دفعة جزئية على الفاتورة — لاحظ تغيّر الحالة إلى "جزئي" والمستحق.
4. **المصروفات** → أضف مصروفًا وفلتر بالأشهر.
5. **لوحة التحكم** → بطاقات KPI + الرسم البياني الشهري لمبيعات 6 أشهر.
6. سجّل دخول بـ `accountant@crm.test` (محاسب: فواتير/دفعات/مصروفات) و `rep@crm.test` (مندوب: أوامر بيع) وجرّب حدود الصلاحيات.

---

*آخر تحديث: أغسطس 2026*

---

## ملخص التنفيذ (Phase 3 Summary)

Phase 3 complete — Sales, Invoicing & Payments are built and tested.

### Implemented:
- **Models:** SalesOrder (`SO-000001`), SalesOrderItem (product snapshot), Invoice (`INV-000001`), InvoiceItem, Payment, Expense (soft deletes) + status enums (draft→confirmed→fulfilled→cancelled, invoice draft→…→paid→overdue, payment methods) with Arabic labels/colors.
- **SalesOrderService:** `recalculateTotals()`, `confirm()` (checks stock, deducts atomically with a `sales_order` movement), `cancel()` (returns stock via `sales_order_return`), `fulfill()`.
- **InvoiceService:** `createFromOrder()` (blocks draft/re-invoiced orders), `recordPayment()` (blocks overpayment), `recomputeStatus()` (paid from payments only → partial/paid/overdue), `nextNumber()`.
- **Pages:** sales orders with dynamic Alpine item rows; invoices with print-friendly standalone view + inline payment form; payments (by invoice with due); expenses (filter by category/month, soft delete); Dashboard with finance KPI cards + monthly sales Chart.js chart (last 6 months, RTL `ar-EG`).
- **Permissions:** rep (view/create orders), accountant (invoices/payments/expenses), admin (confirm/fulfill/cancel/delete + everything).
- **SalesDemoDataSeeder** — orders across users with varied statuses, invoices from confirmed, partial/full payments, 8 expenses over past months for the chart.

### Verification:
**Fixed root cause:** implicit route-model binding name mismatch (`{sales_order}` required `$salesOrder` param). All tests: previous 52 + 15 new = **68 passing**. Full page crawl as admin.

---

## ملخص التنفيذ بالعربية (Phase 3 ملحق)

اكتملت المرحلة 3 — المبيعات والفواتير والمدفوعات مبنية ومختبرة.

### ما تم تنفيذه:
- **النماذج:** أمر البيع (`SO-000001`)، سطر الأمر (لقطة اسم المنتج)، الفاتورة (`INV-000001`)، سطر الفاتورة، الدفعة، المصروف (حذف ناعم) + تعدادات الحالة (مسودة→مؤكد→منفذ→ملغي، الفاتورة مسودة→…→مدفوعة→متأخرة، طرق الدفع) بتسميات وألوان عربية.
- **SalesOrderService:** `recalculateTotals()`، و`confirm()` (يفحص المخزون ويخصمه ذريًا بحركة `sales_order`)، و`cancel()` (يعيد المخزون عبر `sales_order_return`)، و`fulfill()`.
- **InvoiceService:** `createFromOrder()` (يمنع أمر مسودة/المفوتر مسبقًا)، `recordPayment()` (يمنع تجاوز المستحق)، `recomputeStatus()` (المدفوع من الدفعات فقط → جزئي/مدفوعة/متأخرة)، `nextNumber()`.
- **الصفحات:** أوامر بيع بصفوف أصناف ديناميكية عبر Alpine؛ فواتير بعرض طباعة مستقل + نموذج دفعة مدمج؛ دفعات (بالفاتورة مع المستحق)؛ مصروفات (تصفية صنف/شهر وحذف ناعم)؛ لوحة تحكم ببطاقات KPI + رسم مبيعات شهري Chart.js (آخر 6 أشهر، RTL `ar-EG`).
- **الصلاحيات:** المندوب (عرض/إنشاء أوامر)، المحاسب (فواتير/دفعات/مصروفات)، المدير (تأكيد/تنفيذ/إلغاء/حذف + الكل).
- **SalesDemoDataSeeder** — أوامر عبر مستخدمين بحالات متنوعة، فواتير من المؤكد، دفعات جزئية/كاملة، و8 مصروفات موزعة على الأشهر للرسم.

### التحقق:
**إصلاح جوهري:** عدم تطابق اسم معامل الـ controller مع مقطع الرابط (`{sales_order}` يتطلب `$salesOrder`). كل الاختبارات: 52 سابقة + 15 جديدة = **68 ناجحة**. تصفح كامل للصفحات كمدير.

---

*آخر تحديث: أغسطس 2026*