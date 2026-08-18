# المرحلة 2 — المنتجات والمخزون (Phase 2) ✅ مكتملة

**المدة:** أسبوعان (2-3)
**الهدف:** بناء نظام المنتجات والمخزون — تصنيفات، وحدات قياس، منتجات، مستودعات، رصيد مخزون مع سجل حركات ذري.

---

## ما تم تنفيذه في هذه المرحلة

### 1) نماذج (Models) كاملة
| النموذج | ما يخزّنه |
|---|---|
| **Category** | تصنيف المنتجات: الاسم، الرابط `slug`، وتصنيف أب اختياري (شجرة) |
| **Unit** | وحدة القياس: الاسم، الرمز `code` (قطعة، كرتونة، متر...) |
| **Product** | منتج: الاسم، `sku` فريد، الباركود، التصنيف، الوحدة، سعر البيع، تكلفة الشراء، الحد الأدنى للمخزون، وصف، حالة نشاط — مع **Soft Deletes** |
| **Warehouse** | مستودع: الاسم، الرمز `code` فريد، العنوان، حالة النشاط |
| **StockItem** | رصيد منتج في مستودع: فريد `(product_id, warehouse_id)` مع الكمية |
| **StockMovement** | حركة مخزون: النوع، الاتجاه، الكمية، الكمية قبل وبعد، مصدرها (morph)، المنشئ |

**الميزة:** الموديلات تعكس مخطط قاعدة البيانات بدقة، مع علاقات (belongsTo/hasMany/morphTo) وتحقيل الأرقام (decimal casts).

---

### 2) خدمة المخزون (StockService) — قلب المرحلة
كل عملية مخزون **ذرية (DB transaction)** تسجّل حركة `StockMovement` تلقائيًا:

| العملية | الوصف |
|---|---|
| **`in()`** | إضافة كمية لمستودع (استلام/رصيد افتتاحي/إدخال يدوي) |
| **`out()`** | خصم كمية مع **منع السحب الزائد** (يُرمي `InsufficientStockException`) |
| **`adjust()`** | جرد/تصحيح: ضبط الرصيد للكمية الفعلية المسجّلة في الجرد |
| **`transfer()`** | نقل كمية بين مستودعين (حركة خروج + حركة دخول في نفس المعاملة) |

**كل حركة تسجّل:** `before_qty` و `after_qty` ونوعها ومصدرها (مثل أمر بيع/شراء لاحقًا) ومنفّذها.

**الميزة الأكبر:** سجل حركات كامل غير قابل للتلاعب، ويمنع النظام المخزون السالب.

---

### 3) التصنيفات (Categories)
- **جدول** مع عرض عدد المنتجات والتصنيف الأب.
- **إضافة / تعديل / حذف** — لا يمكن حذف تصنيف يحوي منتجات.
- عند حذف تصنيف له تصنيفات فرعية، تُرفع الفرعية لتكون تصنيفًا رئيسيًا.

### 4) وحدات القياس (Units)
- **جدول** مع عرض عدد المنتجات المرتبطة والرمز.
- **إضافة / تعديل / حذف** — لا يمكن حذف وحدة مستخدمة في منتجات.

### 5) المنتجات (Products)
- **جدول** مع: بحث (اسم/SKU/باركود)، تصفية بالتصنيف، وتصفية **حالة المخزون** (منخفض/نفد).
- **شارة الحالة** لكل منتج: متوفر / منخفض / نفد / معطل حسب الرصيد مقابل `min_stock`.
- **إضافة / تعديل / حذف** مع **رصيد افتتاحي** لكل مستودع عند الإنشاء.
- **لوحة تحديث مخزون** داخل صفحة التعديل: إضافة / خصم / جرد بنقرة واحدة.

### 6) المستودعات (Warehouses)
- **بطاقات** تعرض الرمز والعنوان والحالة وإجمالي الرصيد وعدد الأصناف.
- **إضافة / تعديل / حذف** — لا يمكن حذف مستودع يحوي رصيدًا.

### 7) المخزون (Stock)
- **صفحة المخزون**: رصيد كل منتج في كل مستودع، مع تصفية بالمستودع والنطاق (منخفض) والبحث.
- **تنبيه بارز** أعلى الصفحة بعدد الأصناف التي وصلت حد الطلب الأدنى.
- **نموذج جرد/تصحيح**: اختيار منتج + مستودع + الكمية الفعلية → يسوّي الرصيد ويسجّل حركة "adjustment" موثقة بجملة "جرد: من X إلى Y".
- **سجل الحركات**: كل حركات الدخول/الخروج/التسوية مع الكمية قبل وبعد والمنفذ، وتصفية بالمنتج والمستودع والاتجاه.

---

### 8) الصلاحيات (Permissions)
مسارات المخزون محمية كليًا عبر middleware `permission:`:

| الصلاحية | لمن |
|---|---|
| `view_products` / `create_products` / `edit_products` / `delete_products` | مدير المستودع (والعرض للبيع/المشتريات) |
| `manage_categories` / `manage_units` | مدير المستودع |
| `view_warehouses` / `manage_warehouses` | مدير المستودع |
| `view_stock` / `adjust_stock` | مدير المستودع |

---

### 9) بيانات تجريبية (InventoryDemoDataSeeder)
يُشغَّل تلقائيًا مع `php artisan db:seed` (محمي من التكرار):

| الكيان | العدد |
|---|---|
| Categories | 8 (4 رئيسية + 4 فرعية) |
| Units | 5 |
| Warehouses | 3 |
| Products | 14 |
| Stock Movements | 18 (رصيد افتتاحي + عمليات نقل فعلية بين المستودعات) |

بما فيها منتجات **منخفضة المخزون** و**نفدت** لترى التنبيهات تعمل مباشرة.

---

### 10) الاختبارات (12 اختبار Feature)
- إنشاء منتج مع رصيد افتتاحي + الحركة المسجلة.
- فريدة SKU.
- فصل الصلاحيات (مدير مستودع يصل / أخصائي دعم ممنوع).
- خدمة المخزون: إضافة + خصم مع قبل/بعد، **منع السحب الزائد**، جرد، نقل بين مستودعين.
- تصحيح مخزون عبر الـ Controller.
- فلتر المنتجات المنخفضة.
- حماية حذف تصنيف/مستودع برصيد.

**النتيجة:** كل اختبارات المشروع (52) ناجحة.

---

## كيف تتجرب بنفسك
```bash
php artisan serve
```
سجّل الدخول بـ `admin@crm.test / password` ثم جرّب:
1. **المنتجات** → جدول بالحالات والفلاتر، ثم أنشئ منتجًا برصيد افتتاحي.
2. **المخزون** → لاحظ التنبيه للأصناف المنخفضة، ثم سجّل **جردًا** وغيّر رصيدًا فعليًا.
3. **سجل الحركات** → شاهد كل حركة بالكمية قبل وبعد ومنفذها.
4. سجّل دخول بـ `warehouse@crm.test / password` (مدير مستودع) لتجربة صلاحيات كاملة، وجرّب `support@crm.test` لترى المنع.

---

*آخر تحديث: أغسطس 2026*

---

## ملخص التنفيذ (Phase 2 Summary)

Phase 2 complete — Products & Inventory are built and tested.

### Implemented:
- **Models:** Category (tree), Unit, Product (`sku` unique, `min_stock`, soft deletes), Warehouse, StockItem (unique `product_id+warehouse_id`), StockMovement (morph source, before/after quantities).
- **StockService** — atomic `in()`, `out()` (rejects overdraw with `InsufficientStockException`), `adjust()` (count), `transfer()` between warehouses; every movement logs before/after + source + actor.
- **Categories & Units** management with delete protection (in-use / has products) and parent re-linking.
- **Products** table with search, category & stock-status filters, status badges, opening balance per warehouse, inline stock panel.
- **Warehouses** cards + delete protection when has stock.
- **Stock page** with low-stock alert banner, physical-count form, full movement log (filterable).
- **Permissions** for warehouse manager role (`manage_categories/units/warehouses`, `view/adjust_stock`, `create/edit/delete_products`, `view_products`).
- **InventoryDemoDataSeeder** — 8 categories, 5 units, 3 warehouses, 14 products, 18 movements including low/out-of-stock items.

### Verification:
All feature tests: previous 40 + 12 new = **52 passing**. Full crawl of inventory pages as admin + permission separation (warehouse vs support).

---

## ملخص التنفيذ بالعربية (Phase 2 ملحق)

اكتملت المرحلة 2 — المنتجات والمخزون مبنيان ومختبران.

### ما تم تنفيذه:
- **النماذج:** التصنيف (شجرة)، وحدة القياس، المنتج (`sku` فريد، `min_stock`، حذف ناعم)، المستودع، رصيد المخزون (فريد `product_id+warehouse_id`)، حركة المخزون (مصدر morph، كميات قبل/بعد).
- **StockService** — ذرية `in()` و`out()` (يرفض السحب الزائد بـ `InsufficientStockException`) و`adjust()` (جرد) و`transfer()` بين المستودعات؛ كل حركة تسجّل قبل/بعد + المصدر + المنفذ.
- **إدارة التصنيفات ووحدات القياس** مع حماية الحذف (المستخدم/الذي يحوي منتجات) ورفع التصنيفات الفرعية.
- **المنتجات:** جدول ببحث وتصفيات (تصنيف + حالة مخزون)، شارات حالة، رصيد افتتاحي لكل مستودع، ولوحة تحديث مخزون داخل التعديل.
- **المستودعات:** بطاقات + حماية الحذف عند وجود رصيد.
- **صفحة المخزون:** تنبيه الأصناف المنخفضة، نموذج الجرد/التصحيح، وسجل حركات كامل (قابل للتصفية).
- **الصلاحيات:** لدور مدير المستودع (`manage_categories/units/warehouses`، `view/adjust_stock`، `create/edit/delete_products`، `view_products`).
- **InventoryDemoDataSeeder** — 8 تصنيفات، 5 وحدات، 3 مستودعات، 14 منتجًا، 18 حركة تشمل منخفض/نفد.

### التحقق:
كل اختبارات الميزات: 40 سابقة + 12 جديدة = **52 ناجحة**. تصفح كامل لصفحات المخزون + فصل الصلاحيات (مدير مستودع مقابل دعم).

---

*آخر تحديث: أغسطس 2026*
