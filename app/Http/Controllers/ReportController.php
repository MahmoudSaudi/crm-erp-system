<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index()
    {
        return view('reports.index');
    }

    public function sales(Request $request)
    {
        $month = $this->validMonth($request->get('month'));
        $summary = $this->reports->salesSummary($month);

        return view('reports.sales', compact('month', 'summary'));
    }

    public function inventory()
    {
        $summary = $this->reports->inventorySummary();

        return view('reports.inventory', compact('summary'));
    }

    public function profit(Request $request)
    {
        $month = $this->validMonth($request->get('month'));
        $summary = $this->reports->profitSummary($month);

        return view('reports.profit', compact('month', 'summary'));
    }

    public function expenses(Request $request)
    {
        $month = $this->validMonth($request->get('month'));
        $summary = $this->reports->expenseSummary($month);

        return view('reports.expenses', compact('month', 'summary'));
    }

    public function overdue()
    {
        $summary = $this->reports->overdueInvoices();

        return view('reports.overdue', compact('summary'));
    }

    private function validMonth(?string $month): ?string
    {
        return $month && preg_match('/^\d{4}-\d{2}$/', $month) ? $month : null;
    }
}