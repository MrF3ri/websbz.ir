<?php

namespace App\\Modules\\Platform\\Http\\Controllers;

use App\\Modules\\Business\\Models\\Business;
use App\\Modules\\Subscription\\Models\\Payment;
use App\\Modules\\Subscription\\Models\\Subscription;
use App\\Modules\\Subscription\\Models\\SubscriptionEvent;
use Illuminate\\Database\\Eloquent\\Builder;
use Illuminate\\Database\\Eloquent\\Relations\\HasMany;
use Illuminate\\Http\\Request;
use Illuminate\\Validation\\Rule;
use Inertia\\Inertia;
use Inertia\\Response;

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

    public function show(string $businessPublicId): Response
    {
        $business = Business::query()
            ->where('public_id', $businessPublicId)
            ->firstOrFail();

        $members = $business->members()
            ->with('user:id,name,email')
            ->orderBy('id')
            ->get()
            ->map(fn ($member): array => [
                'name' => $member->user->name,
                'email' => $member->user->email,
                'role' => $member->role->value,
                'joined_at' => $member->created_at?->toISOString(),
            ]);

        $owner = $members->firstWhere('role', 'owner');

        $subscription = $business->subscriptions()
            ->with('plan')
            ->latest('id')
            ->first();

        $payments = Payment::query()
            ->with(['targetPlan', 'reviewer:id,name'])
            ->whereHas('subscription', fn (Builder $query) => $query->where('business_id', $business->getKey()))
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (Payment $payment): array => [
                'id' => $payment->getKey(),
                'plan' => $payment->targetPlan->name,
                'amount' => $payment->amount,
                'status' => $payment->status->value,
                'bank_tracking_number' => $payment->bank_tracking_number,
                'submitted_at' => $payment->submitted_at?->toISOString(),
                'reviewed_at' => $payment->reviewed_at?->toISOString(),
                'paid_at' => $payment->paid_at?->toISOString(),
                'reviewer_name' => $payment->reviewer?->name,
                'rejection_reason' => $payment->rejection_reason,
                'receipt_url' => $payment->receipt_path !== null
                    ? route('admin.payments.receipt', $payment)
                    : null,
            ]);

        $events = SubscriptionEvent::query()
            ->with('subscription.plan')
            ->whereHas('subscription', fn (Builder $query) => $query->where('business_id', $business->getKey()))
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (SubscriptionEvent $event): array => [
                'id' => $event->getKey(),
                'type' => $event->event_type->value,
                'plan' => $event->subscription->plan->name,
                'created_at' => $event->created_at?->toISOString(),
            ]);

        return Inertia::render('Admin/Businesses/Show', [
            'business' => [
                'public_id' => $business->public_id,
                'name' => $business->name,
                'slug' => $business->slug,
                'status' => $business->status->value,
                'phone' => $business->phone,
                'email' => $business->email,
                'address' => $business->address,
                'city' => $business->city,
                'postal_code' => $business->postal_code,
                'created_at' => $business->created_at?->toISOString(),
            ],
            'owner' => $owner,
            'members' => $members,
            'subscription' => $this->subscriptionSummary($subscription),
            'payments' => $payments,
            'events' => $events,
        ]);
    }

    /**
     * @return array{status: string, plan: string, ends_at: string|null, trial_ends_at: string|null}|null
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
            'trial_ends_at' => $subscription->trial_ends_at?->toISOString(),
        ];
    }
}
