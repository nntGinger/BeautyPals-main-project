<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $actorId = $request->filled('actor_id') ? (int) $request->input('actor_id') : null;
        $entityType = trim($request->string('entity_type')->toString());
        $action = trim($request->string('action')->toString());
        $fromDate = $request->string('from_date')->toString();
        $toDate = $request->string('to_date')->toString();

        $logs = AuditLog::query()
            ->with('actor')
            ->when($actorId !== null && $actorId > 0, fn ($q) => $q->where('actor_id', $actorId))
            ->when($entityType !== '', fn ($q) => $q->where('entity_type', $entityType))
            ->when($action !== '', fn ($q) => $q->where('action', $action))
            ->when($fromDate !== '', fn ($q) => $q->whereDate('created_at', '>=', $fromDate))
            ->when($toDate !== '', fn ($q) => $q->whereDate('created_at', '<=', $toDate))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $actors = User::query()->orderBy('name')->get(['id', 'name', 'email', 'role']);

        return view('admin.audit-logs.index', compact('logs', 'actors', 'actorId', 'entityType', 'action', 'fromDate', 'toDate'));
    }
}
