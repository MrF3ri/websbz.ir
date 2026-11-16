<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Platform\Models\PlatformAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminAuditLogController
{
    public function index(Request $request): Response
    {
        $actions = PlatformAuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->all();

        $filters = $request->validate([
            'action' => ['nullable', Rule::in($actions)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim($filters['search'] ?? '');

        $logs = PlatformAuditLog::query()
            ->with('actor:id,name,email')
            ->when($filters['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.mb_strtolower($search).'%';

                $query->where(function (Builder $nested) use ($term): void {
                    $nested->whereRaw('LOWER(action) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(target_type) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(target_id) LIKE ?', [$term])
                        ->orWhereHas('actor', fn (Builder $actor) => $actor
                            ->whereRaw('LOWER(name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$term]));
                });
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs->through(fn (PlatformAuditLog $log): array => [
                'id' => $log->getKey(),
                'action' => $log->action,
                'target_type' => $log->target_type,
                'target_id' => $log->target_id,
                'actor_name' => $log->actor_id === null ? 'کاربر حذف‌شده' : $log->actor->name,
                'actor_email' => $log->actor?->email,
                'before' => $log->before_data,
                'after' => $log->after_data,
                'created_at' => $log->created_at?->toISOString(),
            ]),
            'filters' => [
                'action' => $filters['action'] ?? '',
                'search' => $search,
            ],
            'actions' => $actions,
        ]);
    }
}
