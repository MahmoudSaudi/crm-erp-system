# قاعدة البيانات — المخطط الكامل (MySQL / MariaDB)

**قاعدة البيانات:** `crm_erp_app` · **Collation:** `utf8mb4_unicode_ci` · **المحرك:** InnoDB · **نظام التشغيل:** MySQL 8 / MariaDB 10.4

> تم تنفيذ هذا المخطط فعليًا في ملفات `database/migrations/`.

---

## 1) نظرة عامة على العلاقات

```
users ──role_id──▶ roles ◀──role_permission──▶ permissions

users ▶ leads / customers / opportunities / activities / tickets / expenses /
        payments / stock_movements / notifications / audit_logs

leads ──▶ customers        (created_from_lead_id)
customers ▶ opportunities / sales_orders / invoices / tickets / payments
opportunities ──stage_id──▶ opportunity_stages

categories ▶ products ◀──unit_id── units
products ◀──(stock_items)──▶ warehouses   (بفردية product+warehouse)
stock_items ▶ stock_movements  (بـ reference polymorphic → أمر/فاتورة/جرد)

suppliers ▶ purchase_orders ▶ purchase_order_items ▶ products
customers ▶ sales_orders ▶ sales_order_items ▶ products
sales_orders ──▶ invoices ──▶ invoice_items ▶ products
invoices ▶ payments

departments ▶ employees ▶ payrolls ▶ payroll_items + attendance
```

---

## 2) شجرة العلاقات التفصيلية

```
roles (id, name, slug, description, is_active)
  │
  ├── 1─n users  (role_id FK nullOnDelete)
  └── n─n permissions  (via role_permission: unique role+permission)

users (id, name, email, password, role_id, is_active, avatar, last_login_at, ...)
  ├── 1─n leads  (assigned_to / created_by)
  ├── 1─n customers (created_by)
  ├── 1─n opportunities (assigned_to / created_by)
  ├── 1─n activities (assigned_to / created_by)
  ├── 1─n tickets (assigned_to / created_by)
  ├── 1─n expenses (created_by)
  ├── 1─n payments (created_by)
  ├── 1─n stock_movements (created_by)
  ├── 1─n notifications
  ├── 1─n audit_logs
  └── 0..1─1 employees (user_id)

leads (id, first_name, last_name, phone, email, company, source, status,
       value, notes, next_follow_up_at, assigned_to, created_by, converted_at)
  └── 1─0..1 customers (created_from_lead_id)
  └── 1─n opportunities (lead_id)

customers (id, name, phone, email, type, company, tax_number, address, city,
           credit_limit, balance, status, created_from_lead_id, created_by)
  ├── 1─n opportunities
  ├── 1─n sales_orders
  ├── 1─n invoices
  ├── 1─n tickets
  └── 1─n payments

opportunity_stages (id, name, slug, color, position, is_won, is_lost)   ← بيانات أولية
  └── 1─n opportunities (stage_id)

opportunities (id, title, customer_id, lead_id, stage_id, amount, probability,
               expected_close_date, description, assigned_to, created_by, closed_at)

activities (id, type, subject, description, related_type, related_id, scheduled_at,
            completed_at, assigned_to, created_by)   ← polymorphic related

tickets (id, ticket_number, customer_id, subject, message, category, priority,
         status, assigned_to, created_by, resolved_at, deleted_at)
  └── 1─n ticket_messages (ticket_id, user_id, body, is_internal)

categories (id, name, slug, parent_id) ◀── parent_id Self-FK
units (id, name, code)

products (id, name, sku, barcode, category_id, unit_id, sale_price, purchase_cost,
          min_stock, image, description, is_active)
  └── n─n warehouses عبر stock_items (unique: product_id + warehouse_id)

warehouses (id, name, code, address, is_active)
stock_items (id, product_id, warehouse_id, quantity)  ← مصدر الحقيقة للرصيد
stock_movements (id, product_id, warehouse_id, type, reference_type, reference_id,
                 direction, quantity, before_qty, after_qty, note, created_by)
                 ← polymorphic reference (nullableMorphs)

suppliers (id, name, contact_name, phone, email, tax_number, address, is_active)
  └── 1─n purchase_orders (supplier_id, order_number, status, order_date, total)

purchase_orders (id, order_number, supplier_id, warehouse_id, status, order_date,
                 expected_date, received_at, total, notes, created_by)
  └── 1─n purchase_order_items (purchase_order_id, product_id, quantity, received_qty,
                                unit_cost, total)

sales_orders (id, order_number, customer_id, status, order_date, delivery_date,
              subtotal, discount_amount, tax_amount, shipping_amount, total,
              notes, created_by, confirmed_at, fulfilled_at)
  └── 1─n sales_order_items (sales_order_id, product_id, product_name, quantity,
                             unit_price, discount, total)

invoices (id, invoice_number, customer_id, sales_order_id, status, issue_date,
          due_date, subtotal, discount_amount, tax_amount, total, paid_amount,
          notes, created_by, sent_at)
  ├── 1─0..1 sales_order (originating order)
  └── 1─n invoice_items (invoice_id, product_id, description, quantity, unit_price, total)
  └── 1─n payments

payments (id, invoice_id, customer_id, amount, method, reference, status,
          paid_at, notes, created_by)   ← paid_amount في invoices يُحسب من هنا

expenses (id, category, amount, description, date, receipt, is_reimbursable, created_by)

departments (id, name)
  └── 1─n employees (department_id)

employees (id, user_id, department_id, first_name, last_name, email, phone,
           national_id, position, salary_type, base_salary, hire_date, is_active)
  ├── 1─n payrolls (employee_id, period, period_start, period_end, base_salary,
  │                allowances, deductions, bonus, net_total, status, paid_at)
  │     └── 1─n payroll_items (payroll_id, type, label, amount)
  └── 1─n attendance (employee_id, date, check_in, check_out, work_hours, status)
            ← unique (employee_id + date)

notifications (id UUID, user_id, type, title, body, link, read_at)
audit_logs (id, user_id, action, model_type, model_id, old_values JSON,
            new_values JSON, ip, user_agent)
```

---

## 3) نقاط تصميم مهمة (للعرض في المقابلات)

1. **مخزون غير مكرر:** `unique(product_id, warehouse_id)` في `stock_items` يمنع صفوفًا مكررة لنفس المنتج في نفس المخزن.
2. **سببية المخزون (Source of Truth):** `stock_movements` تسجّل الرصيد قبل وبعد (`before_qty`/`after_qty`) وترتبط بمستند السبب عبر `reference_type/reference_id` (نوع polymorphic: أمر بيع، أمر شراء، جرد). أي تغيير في الرصيد = حركة موثقة = تدقيق كامل.
3. **حساب الفواتير من المدفوعات:** `invoices.paid_amount` يُشتق دائمًا من جدول `payments` (وليس قيمة مخزنة يدويًا) → منع التناقض المحاسبي.
4. **التسلسل لا يترك ثغرة:** `order_number`/`invoice_number`/`ticket_number` أعمدة `unique` تُنشأ من Service عند الإنشاء.
5. **تحويل Lead→Customer:** حقل `created_from_lead_id` يمنع تكرار السجل ويربط تدفق التحويل.
6. **Audit Log مرن:** `old_values`/`new_values` بصيغة JSON + `model_type`/`model_id` عام → يخدم كل الكيانات دون تكرار.
7. **RBAC نظيف:** `roles` و `permissions` + pivot `role_permission` مع قيد فريد، وربط المستخدم بدور واحد (`users.role_id`) لتبسيط نمط الأمان في MVP.

---

## 4) قائمة الـ Migrations (بالترتيب)

| الملف | الجدول |
|---|---|
| `0001_01_01_000000_.._create_users_table` | users (الأساسي) |
| `0001_01_01_000001_.._create_cache_table` | cache, cache_locks |
| `0001_01_01_000002_.._create_jobs_table` | jobs, job_batches, failed_jobs |
| `2026_08_11_000001` | roles |
| `2026_08_11_000002` | permissions |
| `2026_08_11_000003` | role_permission |
| `2026_08_11_000004` | users: +role_id, is_active, avatar, last_login_at |
| `2026_08_11_000005` | leads |
| `2026_08_11_000006` | customers |
| `2026_08_11_000007` | opportunity_stages |
| `2026_08_11_000008` | opportunities |
| `2026_08_11_000009` | activities |
| `2026_08_11_000010` | tickets |
| `2026_08_11_000011` | ticket_messages |
| `2026_08_11_000012` | categories |
| `2026_08_11_000013` | units |
| `2026_08_11_000014` | products |
| `2026_08_11_000015` | warehouses |
| `2026_08_11_000016` | stock_items |
| `2026_08_11_000017` | stock_movements |
| `2026_08_11_000018` | suppliers |
| `2026_08_11_000019` | purchase_orders |
| `2026_08_11_000020` | purchase_order_items |
| `2026_08_11_000021` | sales_orders |
| `2026_08_11_000022` | sales_order_items |
| `2026_08_11_000023` | invoices |
| `2026_08_11_000024` | invoice_items |
| `2026_08_11_000025` | payments |
| `2026_08_11_000026` | expenses |
| `2026_08_11_000027` | departments |
| `2026_08_11_000028` | employees |
| `2026_08_11_000029` | payrolls |
| `2026_08_11_000030` | payroll_items |
| `2026_08_11_000031` | attendance |
| `2026_08_11_000032` | notifications |
| `2026_08_11_000033` | audit_logs |
| `2026_08_15_000100` | roles: +is_system |
| `2026_08_15_000001` | purchase_orders: +warehouse_id |

### ملاحظة بيئة محددة
- MariaDB/XAMPP لا يدعم collation `utf8mb4_0900_ai_ci` → عُدّل في `config/database.php` إلى `utf8mb4_unicode_ci`.
- اُستخدمت دالة `decimal()` بدلًا من `unsignedDecimal()` (غير موجودة في Laravel).
- قاعدة `crm_erp` القديمة تابعة لمشروع سابق — المشروع الجديد يستخدم `crm_erp_app`.