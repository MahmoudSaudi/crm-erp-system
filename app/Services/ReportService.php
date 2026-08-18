<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\SalesOrderStatus;
use App\Models\CRM\SalesOrder;
use App\Models\ERP\Expense;
use App\Models\ERP\Invoice;
use App\Models\ERP\Product;
use App\Models\ERP\StockItem;
use App\Models\ERP\StockMovement;
use App\Models\ERP\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function salesSummary(?string $month = null): array
    {
        $range = $this->dateRange($month);

        $orders = SalesOrder::with('customer:id,name')
            ->whereNot('status', SalesOrderStatus::Cancelled->value)
            ->whereBetween('order_date', $range)
            ->get();

        $invoices = Invoice::with('customer:id,name')
            ->whereNot('status', InvoiceStatus::Cancelled->value)
            ->whereBetween('issue_date', $range)
            ->get();

        $payments = DB::table('payments')
            ->whereBetween('paid_at', $range)
            ->get();

        $expenses = Expense::whereBetween('date', $range)->get();

        $monthly = $this->monthlyBreakdown($range);

        $revenue = round((float) $invoices->sum('total'), 2);
        $collected = round((float) $payments->sum('amount'), 2);

        return [
            'ordersCount' => $orders->count(),
            'revenue' => $revenue,
            'collected' => $collected,
            'due' => round(max(0, $revenue - $collected), 2),
            'expensesTotal' => round((float) $expenses->sum('amount'), 2),
            'chart' => $monthly,
            'topCustomers' => $invoices->groupBy('customer_id')
                ->map(fn ($group) => [
                    'name' => $group->first()->customer?->name ?? '—',
                    'total' => round((float) $group->sum('total'), 2),
                ])
                ->sortByDesc('total')
                ->take(5)
                ->values(),
            'recentOrders' => $orders->sortByDesc('order_date')->take(8),
        ];
    }

    public function inventorySummary(): array
    {
        $products = Product::with(['stockItems.warehouse:id,name,code'])->get();

        $lowStock = $products->filter(fn ($p) => $p->isLowStock())->take(10);

        $byWarehouse = Warehouse::with('stockItems')->get()->map(function ($warehouse) {
            return [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'code' => $warehouse->code,
                'totalQty' => round((float) $warehouse->stockItems->sum('quantity'), 2),
            ];
        });

        $latestMovements = StockMovement::with(['product:id,name,sku', 'warehouse:id,name,code'])
            ->latest()
            ->limit(10)
            ->get();

        return [
            'productCount' => $products->count(),
            'stockValue' => round((float) $products->sum(fn ($p) => $p->totalStockQuantity() * (float) $p->purchase_cost), 2),
            'lowStockCount' => $products->filter(fn ($p) => $p->isLowStock())->count(),
            'lowStockItems' => $lowStock,
            'byWarehouse' => $byWarehouse,
            'latestMovements' => $latestMovements,
        ];
    }

    public function profitSummary(?string $month = null): array
    {
        $range = $this->dateRange($month);

        $invoices = Invoice::whereNot('status', InvoiceStatus::Cancelled->value)
            ->whereBetween('issue_date', $range)
            ->get();

        $revenue = round((float) $invoices->sum('total'), 2);

        $cogs = (float) DB::table('sales_order_items')
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
            ->join('products', 'products.id', '=', 'sales_order_items.product_id')
            ->whereIn('sales_orders.status', [SalesOrderStatus::Confirmed->value, SalesOrderStatus::Fulfilled->value])
            ->whereBetween('sales_orders.order_date', $range)
            ->sum(DB::raw('sales_order_items.quantity * products.purchase_cost'));

        $expenses = round((float) Expense::whereBetween('date', $range)->sum('amount'), 2);

        return [
            'revenue' => $revenue,
            'cogs' => round($cogs, 2),
            'grossProfit' => round($revenue - $cogs, 2),
            'expenses' => $expenses,
            'netProfit' => round($revenue - $cogs - $expenses, 2),
        ];
    }

    public function expenseSummary(?string $month = null): array
    {
        $range = $this->dateRange($month);

        $expenses = Expense::whereBetween('date', $range)->get();

        return [
            'total' => round((float) $expenses->sum('amount'), 2),
            'byCategory' => $expenses->groupBy('category')
                ->map(fn ($g) => [
                    'category' => $g->first()->category,
                    'count' => $g->count(),
                    'total' => round((float) $g->sum('amount'), 2),
                ])
                ->sortByDesc('total')
                ->values(),
            'byMonth' => $expenses->groupBy(fn ($e) => $e->date->format('Y-m'))
                ->map(fn ($g) => [
                    'month' => $g->first()->date->translatedFormat('M Y'),
                    'total' => round((float) $g->sum('amount'), 2),
                ])
                ->sortByDesc(fn ($row) => $row['month'])
                ->values(),
        ];
    }

    public function overdueInvoices(): array
    {
        $invoices = Invoice::with('customer:id,name')
            ->whereNot('status', InvoiceStatus::Paid->value)
            ->whereNot('status', InvoiceStatus::Cancelled->value)
            ->where('due_date', '<', now()->toDateString())
            ->get()
            ->filter(fn ($i) => $i->dueAmount() > 0)
            ->map(function ($invoice) {
                $invoice->days_overdue = (int) now()->startOfDay()->diffInDays($invoice->due_date);

                return $invoice;
            })
            ->sortByDesc('days_overdue')
            ->values();

        return [
            'invoices' => $invoices,
            'totalDue' => round((float) $invoices->sum(fn ($i) => $i->dueAmount()), 2),
        ];
    }

    private function dateRange(?string $month): array
    {
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $start = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();

            return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];
        }

        return [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()];
    }

    private function monthlyBreakdown(array $range): array
    {
        $months = collect(range(0, 5))->map(fn ($i) => now()->subMonths($i)->startOfMonth());
        $start = $months->last()->toDateString();
        $end = $months->first()->copy()->endOfMonth()->toDateString();

        $sales = Invoice::whereNot('status', InvoiceStatus::Cancelled->value)
            ->whereBetween('issue_date', [$start, $end])
            ->get()
            ->groupBy(fn ($i) => $i->issue_date->format('Y-m'))
            ->map(fn ($g) => round((float) $g->sum('total'), 2));

        $payments = DB::table('payments')
            ->whereBetween('paid_at', [$start, $end])
            ->get()
            ->groupBy(fn ($p) => substr($p->paid_at, 0, 7))
            ->map(fn ($g) => round((float) $g->sum('amount'), 2));

        $expenses = Expense::whereBetween('date', [$start, $end])
            ->get()
            ->groupBy(fn ($e) => $e->date->format('Y-m'))
            ->map(fn ($g) => round((float) $g->sum('amount'), 2));

        return [
            'labels' => $months->map(fn ($m) => $m->translatedFormat('M Y'))->all(),
            'sales' => $months->map(fn ($m) => (float) ($sales[$m->format('Y-m')] ?? 0))->all(),
            'payments' => $months->map(fn ($m) => (float) ($payments[$m->format('Y-m')] ?? 0))->all(),
            'expenses' => $months->map(fn ($m) => (float) ($expenses[$m->format('Y-m')] ?? 0))->all(),
        ];
    }
}