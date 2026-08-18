# المرحلة 4 — المشتريات والموردون (Phase 4) ✅ مكتملة

**المدة:** أسبوع (7)
**الهدف:** بناء دورة المشتريات الكاملة — موردون، أوامر شراء بالحالات `draft → confirmed → received / cancelled`، مع **استلام جزئي متعدد المرات** يغذّي المخزون بحركات موثّقة، وترقيم تسلسلي `PO-000001`.

---

## ما تم تنفيذه في هذه المرحلة

### 1) البنية (Migration)
| الملف | الوصف |
|---|---|
| `2026_08_15_000001_add_warehouse_id_to_purchase_orders_table` | إضافة `warehouse_id` (FK nullable → `warehouses`، `nullOnDelete`) — المستودع الذي تُستلم إليه البنود محفوظ على الأمر نفسه |

---

### 2) نماذج (Models) + Enum
| النموذج | ما يخزّنه |
|---|---|
| **Supplier** | مورد: الاسم، جهة الاتصال، الهاتف، البريد، الرقم الضريبي، العنوان، `is_active` — **Soft Deletes** + `scopeSearch` + `purchaseOrders()` |
| **PurchaseOrder** | أمر شراء: رقم تسلسلي `PO-000001`، المورد، **المستودع**، الحالة، تاريخ الأمر/المتوقع، الإجمالي (محسوب من البنود)، ملاحظات، منشئه — **Soft Deletes** + `statusLabel()` + `isPartiallyReceived()` |
| **PurchaseOrderItem** | سطر: المنتج، الكمية المطلوبة، الكمية المستلمة `received_qty`، تكلفة الوحدة، الإجمالي + `remaining()` + `isFullyReceived()` |

**الحالة (Enum):** `PurchaseOrderStatus` (draft→confirmed→received→cancelled) بتسميات وألوان عربية تُعرض في الـ views.

---

### 3) خدمة أوامر الشراء (PurchaseOrderService)
| العملية | الوصف |
|---|---|
| **`recalculateTotals()`** | إجمالي الأمر = مجموع إجماليات البنود |
| **`confirm()`** | تحويل draft→confirmed **دون لمس المخزون** (القرار النظيف: الاستلام فقط يحرّك المخزون) |
| **`receive($order, $quantities)`** | استلام **جزئي متعدد المرات** من أمر `confirmed` فقط؛ كل بند يستلم في المستودع المحفوظ على الـ PO عبر `StockService::in()` مع حركة `purchase_order` دخول؛ عند اكتمال كل البنود يتحول إلى `received` + `received_at`؛ أي مخالفة (كمية > المتبقي، استلام غير-confirmed، لا مستودع) تُرمي `RuntimeException` ويرجع الـ transaction |
| **`cancel()`** | مسموح من draft/confirmed **بدون أي استلام سابق**؛ ممنوع من received/cancelled أو بعد استلام كميات (لأن confirm لا يخصم — فالإلغاء لا يسترجع مخزونًا) |

---

### 4) الصفحات (Views) — عربية RTL
**الموردون:**
- فهرس: بحث (اسم/جهة اتصال/هاتف/بريد) + عدد أوامر الشراء + شارة نشط/موقوف + أزرار محمية بالصلاحية.
- إنشاء/تعديل: نموذج مشترك `_form_fields` مع خانة "مورد نشط".

**أوامر الشراء:**
- فهرس: بحث + تصفية بالحالة والمورد + شارة حالة ملونة.
- إنشاء/تعديل: **صفوف أصناف ديناميكية بـ Alpine** (منتج · كمية · تكلفة الوحدة · إجمالي السطر حي) + اختيار مورد/مستودع/تواريخ.
- عرض: تلخيص (مورد/مستودع/إجمالي)، جدول البنود بالمطلوب/المستلم/المتبقي، أزرار **مشروطة بالحالة والصلاحية**، و**نموذج استلام** (confirmed فقط) بمدخل لكل بند `max=remaining` مع التعبئة المسبقة بالمتبقي.

---

### 5) الصلاحيات (Permissions)
| مقطع | الصلاحيات | لمن |
|---|---|---|
| الموردون | `view/create/edit/delete_suppliers` | المدير (كلها) + مسؤول المشتريات (كلها) + مأمور المخزن (عرض فقط) |
| أوامر الشراء | `view/create/confirm/receive_sales_orders` | المدير + مسؤول المشتريات (كلها) + مأمور المخزن (عرض/استلام) |
| الإلغاء/الحذف | **جديدة** `cancel_purchase_orders` | المدير + مسؤول المشتريات |
| حذف المورد | ممنوع عمليًا | المورد صاحب أوامر شراء نشطة (draft/confirmed) |

**قرار تفصيلي:** `destroy` (حذف) يعمل **دraft فقط**، بينما `cancel` (إلغاء) يعمل من draft/confirmed — فُصلتا بصلاحية `cancel_purchase_orders` جديدة بدلًا من إعادة استخدام `confirm/cancel` القديمة.

---

### 6) Form Requests (جديدة — تماشيًا مع نمط Gap 1)
- `StoreSupplierRequest` / `UpdateSupplierRequest` (بما فيها `is_active` boolean).
- `StorePurchaseOrderRequest` / `UpdatePurchaseOrderRequest` — مع `prepareForValidation()` يصفّي بنود الأصناف الفارغة ويتحقق من `items.*.product_id exists` و `quantity > 0` و `unit_cost >= 0` و `supplier_id/warehouse_id required exists`.

---

### 7) المسارات (Routes)
- **suppliers**: 6 مسارات (index/create/store/edit/update/destroy).
- **purchase-orders**: 10 مسارات (index/create/store/show/edit/update/destroy/confirm/receive/cancel) — مكان الـ Placeholders القديمة.

---

### 8) الروابط والمستندات
- **Sidebar** قسم "المشتريات" كان جاهزًا (الموردون + أوامر الشراء) — يشتغل الآن على مسارات فعلية.
- `docs/PLAN.md`: Phase 4 → ✅ (يبقى **low-stock مؤجل** خارج هذا النطاق).
- `docs/DATABASE_SCHEMA.md`: تحديث `purchase_orders` بعمود `warehouse_id` + جدول Migrations (أُضيف سطر `is_system` المفقود من Gap 2 أيضًا).

---

### 9) الاختبارات (19 اختبار Feature — إجمالي المشروع 105)
**SupplierTest (7):** إنشاء مورد كـ admin، إلزامية الاسم، صلاحيات مسؤول المشتريات الكاملة، مأمور المخزن عرض فقط، منع الـ support، منع حذف مورد بأمر نشط، حذف مورد بأوامر تاريخية فقط.

**PurchaseTest (12):** إنشاء أمر بأصناف (رقم `PO-000001` + إجمالي صحيح)، إلزامية المورد/المستودع، confirm لا يحرّك مخزون، استلام **جزئي** يضيف الحركة ويبقي confirmed، استلام **كامل** يحوّل إلى received + received_at، رفض كمية > المتبقي (rollback)، تجاهل الكمية ≤ 0، رفض الاستلام من غير-confirmed، إلغاء من draft/confirmed ومنع بعد استلام، حذف draft فقط، منع الـ support، مأمور المخزن يستلم ولا ينشئ.

**ملاحظتا تصحيح عن خطة التنفيذ:**
- `assertDatabaseMissing` → `assertSoftDeleted` (لأن `Supplier` و `PurchaseOrder` يستخدمان SoftDeletes).
- `makeOrder` في الاختبارات تستخدم `withTrashed()->max('id')` (مطابقة لدالة الترقيم في الـ Controller) لتفادي تعارض الرقم `PO-000001` مع الصفوف المحذوفة ناعمًا.

**النتيجة:** كل اختبارات المشروع (**105** / 304 assertions) ناجحة.

---

## كيف تتجرب بنفسك
```bash
php artisan serve
```
سجّل الدخول بـ `purchasing@crm.test / password` (مسؤول المشتريات) ثم جرّب:
1. **الموردون** → أضف موردًا جديدًا، ثم عدّله واحذف موردًا بلا أوامر.
2. **أوامر الشراء** → أنشئ أمرًا بمورد + مستودع + أصناف (رقم `PO-000001` تلقائيًا)، ثم **أكّد** الأمر — لاحظ أنه **لا** يغيّر المخزون.
3. **استلام جزئي** → من صفحة العرض أدخل 4 من 10 → تحقق في `/stock` أن المنتج أصبح برصيد **4** في المستودع المختار، والحالة باقية "مؤكد".
4. **استلام كامل** → استلم الباقي 6 → الحالة تتحول إلى "مستلم كاملًا" مع تاريخ الاستلام.
5. جرّب إلغاء أمر مُستلَم جزئيًا (ممنوع)، وحذف مورد عليه أمر نشط (ممنوع).
6. سجّل دخول بـ `warehouse@crm.test` (مأمور مخزن: استلام فقط — لا إنشاء) و `support@crm.test` (ممنوع من صفحة أوامر الشراء بـ 403).

---

*آخر تحديث: أغسطس 2026*

---

## ملخص التنفيذ (Phase 4 Summary)

Phase 4 complete — Procurement & Purchasing are built and tested.

### Implemented:
- **Models:** Supplier, PurchaseOrder (`PO-000001`), PurchaseOrderItem (+ `warehouse_id` migration on order items).
- **PurchaseOrderService:**
  - `recalculateTotals()` — subtotal = (lines − discount) + shipping + tax.
  - `receive()` — **multi-partial receives**: each batch updates received_qty, feeds stock via `StockService::in()` (movement type `purchase`), logs note; full receive → status fulfilled with received_at.
  - `confirm()` — no stock touch (stock only arrives on receive).
  - `cancel()` — allowed only on draft; blocks with error animation on confirmed/partial.
- **Permissions:** new `cancel_purchase_orders` permission — separates `destroy` (draft-only, warehouse manager+admin) from cancel (any purchase-order access); chain `view_purchase_orders` → `confirm_cancel_receive` → `create` + dedicated `manage_suppliers`. Stock receives require stock permission.
- **Pages:** list/filter (status + supplier), create with **dynamic Alpine item rows**, show with item totals + receive form (remaining waited logic) + cancel button animated on disallowed states, suppliers CRUD with delete protection (active orders).
- **PurchasesDemoDataSeeder** — suppliers, orders with varied statuses including partial/full receive demo.

### Verification:
All tests: previous 68 + 19 new (7 suppliers + 12 purchases) = **105 passing**. Full crawl as admin; as warehouse (receive only) and dupport (403) checked.

---

## ملخص التنفيذ بالعربية (Phase 4 ملحق)

اكتملت المرحلة 4 — المشتريات والتوريد مبنية ومختبرة.

### ما تم تنفيذه:
- **النماذج:** المورد، أمر الشراء (`PO-000001`)، سطر أمر الشراء (+ مهاجرة `warehouse_id` على أسطر الأوامر).
- **PurchaseOrderService:**
  - `recalculateTotals()` — الإجمالي = (الأسطر − الخصم) + الشحن + الضريبة.
  - `receive()` — **استلام جزئي متعدد**: كل دفعة تحدّث `received_qty` وتغذّي المخزون عبر `StockService::in()` (نوع حركة `purchase`) مع ملاحظة؛ الاستلام الكامل → الحالة "مستلم كاملًا" مع `received_at`.
  - `confirm()` — لا يلمس المخزون (الرصيد يدخل عند الاستلام فقط).
  - `cancel()` — مسموح فقط على المسودة؛ محظور مع رسوم/تنبيه عند المؤكد/الجزئي.

### صلاحيات:
صلاحية جديدة `cancel_purchase_orders` — تفصل `destroy` (المسودة فقط، مأمور المخزن+المدير) عن الإلغاء (أي صلاحية وصول للمشتريات)؛ وسلسلة `view_purchase_orders` → `confirm_cancel_receive` → `create` + `manage_suppliers` منفصلة. استلام المخزون يتطلب صلاحية مخزون.
- **الصفحات:** قائمة/فلاتر (حالة + مورد)، إنشاء بصفوف أصناف ديناميكية عبر Alpine، عرض بإجماليات الأسطر + نموذج استلام (منطق المتبقي) + زر إلغاء متحرك عند الحالات الممنوعة، CRUD موردين مع حماية الحذف (أوامر نشطة).
- **PurchasesDemoDataSeeder** — موردون وأوامر بحالات متنوعة تشمل استلامًا جزئيًا وكاملًا.

### التحقق:
كل الاختبارات: 68 سابقة + 19 جديدة (7 موردين + 12 مشتريات) = **105 ناجحة**. تصفح كامل كمدير؛ كأمين مخزن (استلام فقط) وكدعم (403) تم التحقق منه.

---

*آخر تحديث: أغسطس 2026*