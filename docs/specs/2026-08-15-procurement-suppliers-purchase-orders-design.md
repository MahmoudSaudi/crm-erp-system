# مواصفة التصميم — Phase 4: المشتريات (Suppliers + Purchase Orders)

**التاريخ:** 2026-08-15
**النطاق:** استبدال `PlaceholderController` لوحدتي (suppliers · purchase-orders) بنظام كامل: موديلات + Enum + Service + Controllers + Routes + Views + Validation + Tests.
**الحالة:** معتمدة للمراجعة قبل التنفيذ. لا يُكتب أي كود حتى اعتماد هذه الوثيقة.

---

## 1) الهدف والنطاق

- بناء إدارة **الموردين** (CRUD + soft delete).
- بناء **أوامر الشراء** بالأحوال `draft → confirmed → received / cancelled` مع **استلام جزئي متعدد المرات** للمخزون.
- كل استلام ينشئ حركة مخزون موثّقة (`stock_movements`) مرتبطة بالـ PO عبر `reference` polymorphic (نفس نمط `SalesOrderService`).
- لا نضيف زر low-stock alert في هذا النطاق (مؤجل — خارج هذه المواصفة).

**خارج النطاق:** Payroll/Attendance/Tickets/Reports المحيطة — تبقى Placeholder حتى مراحلها.

---

## 2) الموديلات (جديدة) + Migration

### `App\Models\ERP\Supplier`
| حقل | قواعد |
|---|---|
| name | required |
| contact_name / phone / email / tax_number / address | nullable |
| is_active | bool default true |
| (+ timestamps + softDeletes) | migration موجودة فعلًا |

علاقات: `purchaseOrders()` hasMany. `scopeSearch($query, ?string)` على name/phone/email/contact_name.

### `App\Models\ERP\PurchaseOrder`
| حقل | قواعد |
|---|---|
| order_number | unique — يُنشأ من Service (`PO-000001`) |
| supplier_id | FK nullable nullOnDelete (DB) — **required في الـ Validation** |
| status | string default 'draft' |
| order_date / expected_date / received_at | date/datetime |
| total | decimal 15,2 default 0 |
| notes | nullable |
| created_by | FK nullable nullOnDelete |
| **warehouse_id** | **يُضاف عبر migration جديدة** (FK nullable → warehouses, nullOnDelete في DB) — **required في الـ Validation** |

علاقات: `supplier()` belongsTo · `items()` hasMany · `warehouse()` belongsTo · `creator()` belongsTo(User, created_by). `statusLabel()` + `scopeSearch` (رقم الأمر/المورد) + `isPartiallyReceived()` helper.

### `App\Models\ERP\PurchaseOrderItem`
| حقل | قواعد |
|---|---|
| purchase_order_id | FK cascadeOnDelete |
| product_id | FK restrictOnDelete |
| quantity / received_qty (default 0) | decimal |
| unit_cost / total | decimal |

علاقات: `purchaseOrder()` belongsTo · `product()` belongsTo. Helper: `remaining()` = quantity − received_qty · `isFullyReceived()`.

### Migration جديدة
`2026_08_15_000001_add_warehouse_id_to_purchase_orders_table` — إضافة `warehouse_id` (nullable FK → warehouses, nullOnDelete).

---

## 3) Enum + Service (منطق الأعمال)

### `App\Enums\PurchaseOrderStatus: string`
- `Draft = 'draft'` · `Confirmed = 'confirmed'` · `Received = 'received'` · `Cancelled = 'cancelled'`
- `label()` · `color()` (نفس أنماط `SalesOrderStatus`) · `list()`

### `App\Services\ERP\PurchaseOrderService`
الخدمة هي **صاحب الـ `DB::transaction()`** (وليس الـ controller).

| Method | القواعد |
|---|---|
| `recalculateTotals(PurchaseOrder $order)` | total = sum(items.total) |
| `confirm(PurchaseOrder $order)` | draft فقط (§4 جدول validation) · يغيّر الحالة فقط — **لا يلمس المخزون** |
| `receive(PurchaseOrder $order, array $quantities)` | انظر §3 أدناه — **يستخدم `$order->warehouse`** (لا يُمرَّر مستودع خارجي) |
| `cancel(PurchaseOrder $order)` | ممنوع إن وُجد أي `received_qty > 0` · ممنوع من `received/cancelled` |

### `receive()` — الـ workflow والـ invariant
1. **تبدأ بـ `DB::transaction()`.**
2. تتحقق أن `status === Confirmed` (وإلا `RuntimeException`). تتحقق أن `$order->warehouse` موجود — **لا يُقبل أي مستودع غير محفوظ على الـ PO** (الاستلام يستخدم المستودع المختار عند الإنشاء).
3. لكل بند في الطلب:
   - تتحقق من الكمية المطلوبة: `qty >= 1` و `qty <= remaining` (وإلا `RuntimeException` برسالة عربية).
   - تحدّث `received_qty = received_qty + qty` فقط. **لا تتغير** قيمتا `unit_cost` و`total` للبند: تُحسب `total = quantity × unit_cost` عند إنشاء الـ PO (من الكمية المطلوبة أصلًا)، وتبقى ثابتة مهما استلمنا أجزاءً.
   - تنشئ **Stock Movement مستقل** عبر `StockService::in($product, $order->warehouse, $qty, 'purchase_order', $order, "أمر شراء {order_number}")`.
4. **حالتان للـ PO بعد الحلقة:**
   - إذا `received_qty == quantity` لكل البنود → `status = received` + `received_at = now()`.
   - وإلا → تبقى `status = confirmed` (استلام جزئي).
5. `AuditLogger::log('received', $order, old, new)` (والـ stock movements مسجلة تلقائيًا داخل `StockService`).
6. تُعيد الـ PO المُحدّث (fresh).

### `confirm()` — لا يحرّك مخزون
- يغيّر `status → confirmed` فقط.
- يرفض إن كانت الحالة ليست draft.
- `AuditLogger::log('confirmed', ...)`.

### `cancel()`
- يرفض من `received/cancelled`.
- يرفض إذا أي بند `received_qty > 0`.
- يغيّر `status → cancelled`.
- لا يُرجع أي مخزون (لأن confirm لا يخصم والاستلام محظور قبل الإلغاء).

---

## 4) القواعد وتفاصيلها المرجعية (دائمًا Backend-first)

| العملية | الشرط الصارم (خادم) |
|---|---|
| `receive()` | `status === confirmed` · `$order->warehouse` موجود · كل كمية `>= 1` و `<= remaining` · داخل transaction |
| `cancel()` | ممنوع من `received/cancelled` · ممنوع إن وُجد أي `received_qty > 0` · يعمل من `draft` أو `confirmed` |
| `confirm()` | الحالة `draft` فقط |
| **`destroy()` vs `cancel()`** | **حذف (DELETE)** = إزالة الـ PO وهو ما زال `draft` فقط · **إلغاء (POST cancel)** = تغيير الحالة إلى `cancelled` من `draft` أو `confirmed` · بعد `confirmed` **لا حذف** · بعد `received/cancelled` **لا حذف ولا إلغاء** |
| `create/update PO` (validated) | `supplier_id` **required** + `exists:suppliers,id` · **`warehouse_id` required** + `exists:warehouses,id` · بنود غير فارغة · كل بند: `product_id exists` · `quantity >= 1` · `unit_cost >= 0` |
| `edit/update PO` | الحالة `draft` فقط |
| `destroy Supplier` | ممنوع فقط إذا وُجدت أوامر شراء بحالة `draft/confirmed` (المرتبطة بهذا المورد) |

> الـ UI/الـ Frontend يعرض الأخطاء ويقيّد المدخلات لكنه **ليس خط الدفاع**؛ كل القواعد أعلاه تُنفَّذ في الـ Service/الـ Controller.

---

## 5) Controllers + Routes + RBAC

### `App\Http\Controllers\ERP\SupplierController`
- `index` (بحث + فلتر حالة is_active) · `create/store` · `edit/update` · `destroy`
- `destroy`: `PurchaseOrder::where('supplier_id', $id)->whereIn('status', ['draft','confirmed'])->exists()` → ارفض (message عربي).
- كل عملية `AuditLogger::log(...)` + redirect مع `with('success'/'error')`.

### `App\Http\Controllers\ERP\PurchaseOrderController`
- `index` (بحث + فلتر status/supplier) · `create/store` (بنود ديناميكية) · `show` · `edit/update` (draft) · `destroy` (draft فقط)
- **الفصل بين `destroy()` و `cancel()`:**
  - `destroy(PurchaseOrder)` — **حذف فعلي** للـ PO، يُسمح فقط إذا كانت الحالة `draft` (ولا يمكن بعد confirm أبدًا).
  - `cancel(PurchaseOrder)` — **تغيير حالة** إلى `cancelled` من `draft` أو `confirmed` (لو لم يُستلم شيء).
  - كلاهما محمي بصلاحية `cancel_purchase_orders`، ويستدعيان Service مع catch لـ `RuntimeException` → `back()->with('error')`.
- `confirm(PurchaseOrder)` / `receive(Request, PurchaseOrder)` — استدعاء Service فقط (receive لا يستقبل warehouse؛ يقرأه من الـ PO).
- `nextNumber()` → `PO-` + str_pad 6.
- **Route model binding:** معامل الطريقة `PurchaseOrder $purchaseOrder` مقابل `{purchase_order}` في الـ URIs (نفس ملاحظة أورام البيع).

### Routes (`routes/web.php`) — استبدال Placeholder
```php
// Suppliers
GET  /suppliers                 → suppliers.index      (view_suppliers)
GET  /suppliers/create          → suppliers.create     (create_suppliers)
POST /suppliers                 → suppliers.store      (create_suppliers)
GET  /suppliers/{supplier}/edit → suppliers.edit       (edit_suppliers)
PUT/PATCH /suppliers/{supplier} → suppliers.update     (edit_suppliers)
DELETE /suppliers/{supplier}    → suppliers.destroy    (delete_suppliers)

// Purchase Orders
GET  /purchase-orders                     → purchase-orders.index  (view_purchase_orders)
GET  /purchase-orders/create              → purchase-orders.create (create_purchase_orders)
POST /purchase-orders                     → purchase-orders.store  (create_purchase_orders)
GET  /purchase-orders/{purchase_order}    → purchase-orders.show   (view_purchase_orders)
GET  /purchase-orders/{purchase_order}/edit → purchase-orders.edit   (create_purchase_orders)
PUT/PATCH /purchase-orders/{purchase_order} → purchase-orders.update (create_purchase_orders)
DELETE /purchase-orders/{purchase_order}  → purchase-orders.destroy (cancel_purchase_orders)
POST /purchase-orders/{purchase_order}/confirm → purchase-orders.confirm (confirm_purchase_orders)
POST /purchase-orders/{purchase_order}/receive → purchase-orders.receive (receive_purchase_orders)
POST /purchase-orders/{purchase_order}/cancel → purchase-orders.cancel (cancel_purchase_orders)
```

### RBAC (بإضافة صلاحية واحدة للـ `RbacSeeder` ثم إعادة تشغيله)
- **صلاحية جديدة:** `cancel_purchase_orders` (group `purchase_orders`) — تُضاف إلى `permissions()` في `RbacSeeder` وتُرجع له:
  - **purchasing-officer:** suppliers (view/create/edit/delete) + purchase_orders (view/create/confirm/**cancel**/receive)
  - **warehouse-manager:** view_suppliers + view/receive_purchase_orders
  - **admin:** كل شيء تلقائيًا (Permission::all)
- بعد تعديل الـ seeder: `php artisan db:seed --class=RbacSeeder` (أو عبر `RefreshDatabase` في الاختبارات، التي تستدعي الـ seeder تلقائيًا).

---

## 6) Views / UI (عربية RTL — أنماط النظام الحالية)

```
resources/views/erp/suppliers/{index, create, edit}.blade.php
resources/views/erp/purchase-orders/{index, create, show, edit}.blade.php
```

- **suppliers/index:** جدول (اسم، جهة اتصال، هاتف، بريد، حالة is_active badge) + بحث + زر/إنشاء/تعديل/حذف.
- **purchase-orders/index:** جدول (رقم، مورد، تاريخ، إجمالي، حالة badge ملوّن) + فلتر status/supplier.
- **purchase-orders/create:** نموذج بنود ديناميكية + `supplier select` + `warehouse select` + تاريخ/متوقع + ملاحظات.
- **purchase-orders/show:** بطاقات البيانات + جدول البنود (المطلوب/المستلم/المتبقي) + إجراءات حسب الحالة:
  - `draft` → تعديل/تأكيد/إلغاء.
  - `confirmed` → جدول استلام (input لكل بند `min=1 max=remaining`) + عرض المستودع (من الـ PO) + زر استلام.
  - `received` → شارة "استلام كامل" + تاريخ الاستلام.
  - `cancelled` → شارة إلغاء فقط.
- **purchase-orders/edit:** تعديل بنود (draft فقط).

> نفس الـ components/tables/badges/buttons المستخدمة في `crm.sales-orders.*` و `erp.warehouses.*` — بلا مكتبات جديدة، وبلا routes مفقودة في الـ sidebar (الروابط موجودة مسبقًا).

---

## 7) الاختبارات

### `tests/Feature/SupplierTest.php`
- admin يمكنه إنشاء/تعديل/حذف مورد · مورد يتطلب name · search · RBAC (support لا يرى، purchasing-officer كامل، warehouse-manager view فقط) · **منع حذف مورد عليه PO في draft/confirmed** · السماح بحذف مورد تاريخي فقط (لا PO نشط).

### `tests/Feature/PurchaseTest.php`
- إنشاء PO بمورد + بنود + warehouse · ترقيم تسلسلي `PO-000001` · search/فلتر · confirm يُغيّر الحالة ولا يحرّك مخزون · استلام جزئي يضيف المخزون (للمستودع المحفوظ على الـ PO) ويحدّث received_qty ويبقي confirmed · استلام كامل يغيّر إلى received + received_at · منع استلام `qty > remaining` · منع `qty <= 0` · منع استلام في draft/cancelled/received · **منع استلام بكمية > المتبقي لا يغادر transaction** · **destroy يعمل فقط في draft ويُرفض بعد confirm** · cancel ناجح من draft و من confirmed بلا استلام (لا استرجاع مخزون لأن لا خصم) · **cancel ممنوع بعد any استلام ومن received/cancelled** · RBAC (support ممنوع، warehouse-manager يُستلم فقط، purchasing-officer كامل، وكلاهما بمبدأ cancel_purchase_orders).

---

## 8) Cleanup / Smoke Test

- تحديث `docs/DATABASE_SCHEMA.md` (قسم purchase_orders + warehouse_id).
- تحديث `docs/PLAN.md` (Phase 4 المشتريات → ✅ جزئي للموردين الأوامر/الاستلام).
- `php artisan route:list` للتحقق من routes الجديدة.
- `php artisan test` كامل (الكل أخضر).
- Smoke يدوي خارجي: إنشاء مورد → PO → تأكيد → استلام جزئي → متابعة رصيد المنتج في `/stock`.

---

## 9) ملاحظات التصميم المتفق عليها (record of decisions)

1. استلام **جزئي** متعدد المرات (ليس كاملًا فقط).
2. أضفنا `warehouse_id` على الـ PO عبر **migration واحدة** (مختار وقت الإنشاء).
3. `confirm` **لا يحرّك المخزون** (قرار نظيف — الاستلام فقط يحرّك).
4. `DB::transaction()` يملكه **Service** وليس الـ Controller.
5. `receive` يتحقق من الحالة والفروقات داخل transaction (أي فشل = rollback كامل).
6. عدد حركات المخزون = عدد البنود المستقبَلة (حركة مستقلة لكل بند).
7. الحالة `received` تتطلب `received_qty == quantity` لكل البنود.
8. `cancel` ممنوع بعد أي استلام؛ ومنع حذف مورد مع PO نشط فقط (draft/confirmed).
9. الترقيم `PO-000001` (نفس نمط `SO-000001`).
10. Backend دائمًا هو خط الدفاع عن validation و RBAC — الـ UI عرض وتجربة فقط.
11. `supplier_id` و `warehouse_id` **required** في الـ Validation (لا يُنشأ PO بلا مورد أو بلا مستودع) — بينما تبقى الأعمدة في DB nullable (نمط `nullOnDelete`).
12. `receive()` يقرأ **المستودع من الـ PO** (`$order->warehouse`) — لا يُمرَّر مستودع مختلف.
13. الفصل الواضح: **`destroy()` = حذف فعلي draft فقط** · **`cancel()` = تغيير حالة إلى cancelled من draft/confirmed** · الاثنان بصلاحية `cancel_purchase_orders`.