<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\LeadStatus;
use App\Models\CRM\Activity;
use App\Models\CRM\Customer;
use App\Models\CRM\Lead;
use App\Models\CRM\Opportunity;
use App\Models\CRM\SalesOrder;
use App\Models\ERP\Expense;
use App\Models\ERP\Invoice;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'leads' => Lead::count(),
            'customers' => Customer::count(),
            'tickets' => DB::table('tickets')->count(),
            'products' => DB::table('products')->count(),
            'sales_orders' => SalesOrder::where('status', '!=', 'cancelled')->count(),
            'expenses' => Expense::count(),
        ];

        $leadStats = collect(LeadStatus::cases())->map(function (LeadStatus $status) {
            return [
                'status' => $status,
                'count' => Lead::where('status', $status->value)->count(),
            ];
        });

        $openPipelineValue = Opportunity::whereHas('stage', function ($q) {
            $q->where('is_won', false)->where('is_lost', false);
        })->sum('amount');

        $wonPipelineValue = Opportunity::whereHas('stage', fn ($q) => $q->where('is_won', true))->sum('amount');

        $financial = [
            'sales' => Invoice::whereNot('status', InvoiceStatus::Cancelled->value)->sum('total'),
            'collected' => Invoice::whereNot('status', InvoiceStatus::Cancelled->value)->sum('paid_amount'),
            'due' => Invoice::whereNot('status', InvoiceStatus::Cancelled->value)->get()->sum(fn ($i) => $i->dueAmount()),
            'expenses' => Expense::sum('amount'),
        ];

        $chart = $this->monthlyChart();

        $recentActivities = Activity::with(['creator:id,name'])
            ->latest()
            ->limit(8)
            ->get();

        return view('dashboard', compact('stats', 'leadStats', 'openPipelineValue', 'wonPipelineValue', 'financial', 'chart', 'recentActivities'));
    }

    private function monthlyChart(): array
    {
        $months = collect(range(5, 0))->map(function (int $i) {
            return now()->subMonths($i);
        });

        $sales = Invoice::whereNot('status', InvoiceStatus::Cancelled->value)
            ->where('issue_date', '>=', $months->first()->startOfMonth()->toDateString())
            ->get()
            ->groupBy(fn ($i) => $i->issue_date->format('Y-m'))
            ->map(fn ($group) => round($group->sum(fn ($i) => (float) $i->total), 2));

        $payments = DB::table('payments')
            ->where('paid_at', '>=', $months->first()->startOfMonth()->toDateString())
            ->get()
            ->groupBy(fn ($p) => substr($p->paid_at, 0, 7))
            ->map(fn ($group) => round($group->sum(fn ($p) => (float) $p->amount), 2));

        $expenses = Expense::where('date', '>=', $months->first()->startOfMonth()->toDateString())
            ->get()
            ->groupBy(fn ($e) => $e->date->format('Y-m'))
            ->map(fn ($group) => round($group->sum(fn ($e) => (float) $e->amount), 2));

        return [
            'labels' => $months->map(fn ($m) => $m->translatedFormat('M Y'))->all(),
            'sales' => $months->map(fn ($m) => (float) ($sales[$m->format('Y-m')] ?? 0))->all(),
            'payments' => $months->map(fn ($m) => (float) ($payments[$m->format('Y-m')] ?? 0))->all(),
            'expenses' => $months->map(fn ($m) => (float) ($expenses[$m->format('Y-m')] ?? 0))->all(),
        ];
    }
}
