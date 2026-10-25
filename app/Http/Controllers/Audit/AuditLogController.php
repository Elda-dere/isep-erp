<?php

namespace App\Http\Controllers\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'user' => ['nullable', 'integer'],
            'action' => ['nullable', 'string', 'max:100'],
            'entity' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'ip' => ['nullable', 'string', 'max:45'],
        ]);

        $zone = config('erp.timezone');

        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when($filters['user'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            // Prefix search, so "auth." finds every auth action. % and _ are escaped.
            ->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', 'like', addcslashes($v, '%_\\').'%'))
            ->when($filters['entity'] ?? null, fn ($q, $v) => $q->where('entity_type', $v))
            // The person picks days in business time; the database stores UTC.
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', Carbon::parse($v, $zone)->startOfDay()->utc()))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', Carbon::parse($v, $zone)->endOfDay()->utc()))
            ->when($filters['ip'] ?? null, fn ($q, $v) => $q->where('ip_address', $v))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'created_at' => $log->created_at->copy()->setTimezone($zone)->format('Y-m-d H:i:s'),
                'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'ip_address' => $log->ip_address,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
            ]);

        return Inertia::render('Audit/Index', [
            'logs' => $logs,
            'filters' => $request->only(['user', 'action', 'entity', 'from', 'to', 'ip']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'entities' => AuditLog::query()
                ->whereNotNull('entity_type')
                ->distinct()
                ->orderBy('entity_type')
                ->pluck('entity_type'),
        ]);
    }
}