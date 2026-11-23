<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    /**
     * S4-11: Filter and inspect immutable system audit logs.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $validated = $request->validate([
            'actor_user_id' => ['nullable', 'integer', 'min:1'],
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'auditable_type' => ['nullable', 'string', 'max:40'],
            'entity_type' => ['nullable', 'string', 'max:40'],
            'action' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
        ]);

        $actorId = $validated['actor_user_id'] ?? $validated['actor_id'] ?? null;
        $actorId = $actorId !== null ? (int) $actorId : null;
        $entityType = trim((string) ($validated['auditable_type'] ?? $validated['entity_type'] ?? ''));
        $action = trim((string) ($validated['action'] ?? ''));
        $from = (string) ($validated['from'] ?? $validated['from_date'] ?? '');
        $to = (string) ($validated['to'] ?? $validated['to_date'] ?? '');

        $logs = AuditLog::query()
            ->with('actor')
            ->when($actorId !== null, fn ($query) => $query->where('actor_id', $actorId))
            ->when($entityType !== '', fn ($query) => $query->where('entity_type', $entityType))
            ->when($action !== '', fn ($query) => $query->where('action', $action))
            ->when($from !== '', fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $actors = User::query()->orderBy('name')->get(['id', 'name', 'email', 'role']);
        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');
        $entityTypes = AuditLog::query()->select('entity_type')->distinct()->orderBy('entity_type')->pluck('entity_type');

        return view('admin.audit-logs.index', compact(
            'logs',
            'actors',
            'actions',
            'entityTypes',
            'actorId',
            'entityType',
            'action',
            'from',
            'to',
        ));
    }
}
