<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Business\Models\Business;
use App\Modules\Subscription\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminBusinessController
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'suspended'])],
        ]);

        $search = trim($filters['search'] ?? '');

        $businesses = Business::query()
            ->withCount('members')
            ->with(['subscriptions' => fn (HasMany $query) => $query
                ->with('plan')
                ->latest('id')
                ->limit(1)])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.mb_strtolower($search).'%';

                $query->where(function (Builder $nested) use ($term): void {
                    $nested->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(slug) LIKE ?', [$term]);
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Businesses/Index', [
            'businesses' => $businesses->through(fn (Business $business): array => [
                'public_id' => $business->public_id,
                'name' => $business->name,
                'slug' => $business->slug,
                'status' => $business->status->value,
                'members_count' => $business->members_count,
                'created_at' => $business->created_at?->toISOString(),
                'subscription' => $this->subscriptionSummary($business->subscriptions->first()),
            ]),
            'filters' => [
                'search' => $search,
                'status' => $filters['status'] ?? '',
            ],
        ]);
    }

    /**
     * @return array{status: string, plan: string, ends_at: string|null}|null
     */
    private function subscriptionSummary(?Subscription $subscription): ?array
    {
        if ($subscription === null) {
            return null;
        }

        return [
            'status' => $subscription->status->value,
            'plan' => $subscription->plan->name,
            'ends_at' => $subscription->current_period_ends_at?->toISOString(),
        ];
    }
}
