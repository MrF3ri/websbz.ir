<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminSubscriptionController
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_column(SubscriptionStatus::cases(), 'value'))],
            'plan' => ['nullable', 'string', 'max:40', Rule::exists('plans', 'code')],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim($filters['search'] ?? '');

        $subscriptions = Subscription::query()
            ->with(['business', 'plan'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['plan'] ?? null, fn (Builder $query, string $planCode) => $query->whereHas(
                'plan',
                fn (Builder $planQuery) => $planQuery->where('code', $planCode),
            ))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.mb_strtolower($search).'%';

                $query->whereHas('business', fn (Builder $businessQuery) => $businessQuery
                    ->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(slug) LIKE ?', [$term]));
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Subscriptions/Index', [
            'subscriptions' => $subscriptions->through(fn (Subscription $subscription): array => [
                'id' => $subscription->getKey(),
                'business_name' => $subscription->business->name,
                'business_slug' => $subscription->business->slug,
                'plan_name' => $subscription->plan->name,
                'plan_code' => $subscription->plan->code,
                'status' => $subscription->status->value,
                'trial_ends_at' => $subscription->trial_ends_at?->toISOString(),
                'period_starts_at' => $subscription->current_period_starts_at?->toISOString(),
                'period_ends_at' => $subscription->current_period_ends_at?->toISOString(),
            ]),
            'filters' => [
                'status' => $filters['status'] ?? '',
                'plan' => $filters['plan'] ?? '',
                'search' => $search,
            ],
            'plans' => Plan::query()->orderBy('id')->get(['code', 'name']),
            'statuses' => array_map(
                fn (SubscriptionStatus $status): array => [
                    'value' => $status->value,
                    'label' => match ($status) {
                        SubscriptionStatus::Trialing => 'آزمایشی',
                        SubscriptionStatus::Active => 'فعال',
                        SubscriptionStatus::PastDue => 'سررسیدگذشته',
                        SubscriptionStatus::Canceled => 'لغوشده',
                        SubscriptionStatus::Expired => 'منقضی',
                    },
                ],
                SubscriptionStatus::cases(),
            ),
        ]);
    }
}
