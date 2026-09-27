# Nexus CRM & ERP System

**Customer Management | Sales & Invoicing | Procurement | Inventory | Support Tickets | Human Resources & Payroll | Analytics & Reporting**

A comprehensive, enterprise-grade web application built with **Laravel 11** and **MySQL**. This system seamlessly integrates Customer Relationship Management (CRM) with Enterprise Resource Planning (ERP). Designed as a production-ready application, it features an advanced Role-Based Access Control (RBAC) system with 8 distinct roles, comprehensive audit logging, internal notifications, full RTL support, a dynamic dark mode interface, and **142 passing automated tests**.

> **Demo Accounts**: `admin@crm.test` / `password` — Alternatively, use any of the pre-configured role-specific demo accounts (Sales Manager, Accountant, Warehouse Clerk, Purchasing Officer, Support Specialist, HR Specialist, etc.).

---



# CRM ERP System

نظام إدارة علاقات العملاء والموارد المؤسسية.

## Stack

- Laravel 11 (PHP 8.2)
- MySQL 8.0
- Nginx 1.27
- Docker + Docker Compose

## المتطلبات

- Docker Engine 24+
- Docker Compose v2+

## التشغيل السريع

```bash
# 1. Clone
git clone git@github.com:<username>/crm-erp-system.git
cd crm-erp-system

# 2. جهّز .env.docker
cp .env.docker.example .env.docker
# عدّل APP_KEY:
KEY="base64:$(openssl rand -base64 32)"
sed -i "s|^APP_KEY=.*|APP_KEY=$KEY|" .env.docker

# 3. شغّل الـ stack
docker compose up -d

# 4. اعمل migrations
docker compose exec app php artisan migrate --force

# 5. (لو محتاج) اعمل جدول sessions
docker compose exec db mysql -u crm_user -pcrm_secret_2026 crm_erp -e "
CREATE TABLE IF NOT EXISTS sessions (
  id VARCHAR(255) NOT NULL PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  user_agent TEXT NULL,
  payload LONGTEXT NOT NULL,
  last_activity INT NOT NULL,
  INDEX sessions_user_id_index (user_id),
  INDEX sessions_last_activity_index (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
"




## Features & Modules

### Customer Relationship Management (CRM)
- **Lead Management**: Track sources, statuses, and follow-ups. Convert qualified leads seamlessly into customers.
- **Customer Directory**: Manage corporate and individual clients, credit limits, account balances, and interaction history.
- **Opportunities**: Monitor the sales pipeline with customizable stages and closing probabilities.
- **Activities**: Schedule and log calls, emails, meetings, and tasks.

### Sales & Financials
- **Sales Orders**: Lifecycle management (Draft, Confirmed, Fulfilled, Canceled). Features automatic discount calculations, shipping fees, and automated inventory deduction upon confirmation.
- **Invoicing**: Generate invoices directly from confirmed orders. Track statuses (Draft, Sent, Partially Paid, Paid, Overdue) and print professional copies.
- **Payments**: Process multi-channel payments (Cash, Bank Transfer, Check, Card) with automatic invoice status reconciliation.
- **Expenses**: Categorize and track corporate expenses, including reimbursable claims.

### Procurement & Inventory Management
- **Supplier Management**: Maintain supplier profiles and purchase histories.
- **Purchase Orders**: Full lifecycle tracking with support for partial quantity receiving.
- **Product Catalog**: Manage SKUs, categories, units, cost structures, pricing, and minimum stock alerts.
- **Advanced Inventory**: Multi-warehouse support and precise stock movement tracking (Opening Balances, Sales, Purchases, Transfers) with strict validation against negative stock.

### Support Desk
- **Ticketing System**: Create, categorize, and prioritize support tickets. Assign tickets to specific agents, manage internal notes (e.g., Accounting/Support communication), and handle external replies with automated notifications.

### Human Resources & Payroll (HR)
- **Organization Structure**: Manage departments and employee profiles with independent system access roles.
- **Attendance Tracking**: Daily logging with automated calculation of working hours and status variances.
- **Payroll Processing**: A robust multi-step lifecycle (`Generate` -> `Update Amounts` -> `Approve` -> `Pay`). Includes detailed line items, strict validation against modifying approved/paid records, and comprehensive audit trails.

### Analytics & Reporting
- **Interactive Dashboard**: High-level metrics and visual charts.
- **Detailed Reports**: Five modular, filterable reports (Sales Performance with 6-month historical charts, Inventory Valuation, Profitability, Expenses, and Overdue Invoices). Access is securely gated by the `view_reports` permission.

### Security & Administration
- **Role-Based Access Control (RBAC)**: Over 50 granular permissions distributed across 8 predefined roles.
- **Audit Logging**: Immutable audit trails recording sensitive operations across all modules.
- **System Interface**: Built with Tailwind CSS and Alpine.js, featuring a robust Dark Mode toggle and complete RTL optimization utilizing the "Cairo" typography.

---

## Technical Stack

| Layer | Technology |
|---|---|
| **Backend** | PHP 8.2+ · Laravel 11 · Blade Components |
| **Database** | MySQL · Eloquent ORM (SoftDeletes) · Migrations |
| **Frontend** | Tailwind CSS · Alpine.js · Chart.js · Cairo Font |
| **Auth & Security**| Laravel Breeze · Custom RBAC Middleware |
| **Quality Assurance**| PHPUnit — **142 Tests / 414 Assertions** |

---

## Local Development Setup

```bash
# 1. Prerequisites: PHP 8.2+, MySQL, and Composer
composer install
npm install && npm run build

# 2. Environment Configuration
cp .env.example .env
# Update MySQL connection credentials in the .env file
php artisan key:generate

# 3. Database Migration & Comprehensive Seeding
php artisan migrate:fresh --seed

# 4. Serve the Application
php artisan serve
# The application will be available at http://127.0.0.1:8000
```

> **Note**: Executing `migrate:fresh --seed` provisions the database with a realistic 6-month historical dataset, including: 15 customers, 28 leads, 12 opportunities, 6 suppliers, 12 purchase orders, 24 sales orders, 19 invoices, 14 products, 9 payroll records, and various support tickets.

---

## Testing

Run the automated test suite to verify system integrity:

```bash
php artisan test
# Validates 142 passing tests ensuring robust business logic
```

---

## Architecture & Directory Structure

```text
app/
├── Enums/                 # Strongly typed business states (Orders, Invoices, Tickets, etc.)
├── Models/
│   ├── CRM/               # Lead, Customer, Opportunity, Activity, Ticket, SalesOrder
│   ├── ERP/               # Product, Category, Unit, Warehouse, StockMovement, Supplier, etc.
│   └── HR/                # Department, Employee, Attendance, Payroll
├── Services/              # Core business logic encapsulation (Stock, Invoicing, Payroll, Audit)
└── Http/Controllers/      # Modular controllers handling specific domains
database/
├── migrations/            # 30+ relational database migrations
├── seeders/               # RBAC setup and realistic demographic/transactional data seeders
resources/views/           # Component-based Blade templates with native Dark Mode support
docs/                      # Architecture plans and database schemas
phases/                    # Historical development phase documentation
```

---

## Documentation References

- [`docs/PLAN.md`](docs/PLAN.md) — Comprehensive master project plan.
- [`docs/DATABASE_SCHEMA.md`](docs/DATABASE_SCHEMA.md) — Relational database schema documentation.
- [`phases/`](phases/) — Detailed sprint and phase execution reports (Phase 0 through 7).
- [`docs/plans/`](docs/plans/) — Granular implementation plans for individual features.

---

## Demo Role Credentials

All demonstration accounts share the same password: `password`

| Role | Email | Primary Permissions |
|---|---|---|
| **System Administrator** | `admin@crm.test` | Full System Access |
| **Sales Manager** | `manager@crm.test` | CRM, Sales Orders, Reporting |
| **Sales Representative** | `rep@crm.test` | Leads, Customers, Opportunities, Orders |
| **Support Specialist** | `support@crm.test` | Support Tickets |
| **Accountant** | `accountant@crm.test` | Invoices, Payments, Expenses, Reporting |
| **Warehouse Clerk** | `warehouse@crm.test` | Products, Inventory, Goods Receipt |
| **Purchasing Officer** | `purchasing@crm.test` | Suppliers, Purchase Orders, Reporting |
| **HR Specialist** | `hr@crm.test` | Employees, Payroll, Attendance |

---

## Additional Notes

- The permissions architecture relies on scalable `Permission::slug` mapping, automatically provisioned via `RbacSeeder`.
- test for jenkins active or not
