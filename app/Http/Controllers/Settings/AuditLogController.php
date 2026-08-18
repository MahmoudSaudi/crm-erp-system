<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->get('action')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->get('user_id')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('settings.audit-logs.index', [
            'logs' => $logs,
            'actions' => AuditLog::distinct()->orderBy('action')->pluck('action'),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'activeAction' => $request->get('action'),
            'activeUser' => $request->get('user_id'),
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        $auditLog->load('user:id,name');

        return view('settings.audit-logs.show', ['log' => $auditLog]);
    }
}