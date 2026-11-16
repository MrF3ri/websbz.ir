<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Subscription\Enums\SubscriptionEventType;
use App\Modules\Subscription\Models\SubscriptionEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminEventController
{
    public function index(Request $request): Response
    {
        $eventTypes = array_map(
            static fn (SubscriptionEventType $type): string => $type->value,
            SubscriptionEventType::cases(),
        );

        $filters = $request->validate([
            'event_type' => ['nullable', Rule::in($eventTypes)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim($filters['search'] ?? '');

        $events = SubscriptionEvent::query()
            ->with([
                'subscription.business:id,public_id,name,slug',
                'subscription.plan:id,name',
            ])
            ->when(
                $filters['event_type'] ?? null,
                fn (Builder $query, string $eventType) => $query->where('event_type', $eventType),
            )
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.mb_strtolower($search).'%';

                $query->whereHas('subscription', function (Builder $subscriptionQuery) use ($term): void {
                    $subscriptionQuery
                        ->whereHas('business', fn (Builder $businessQuery) => $businessQuery
                            ->whereRaw('LOWER(name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(slug) LIKE ?', [$term]))
                        ->orWhereHas('plan', fn (Builder $planQuery) => $planQuery
                            ->whereRaw('LOWER(name) LIKE ?', [$term]));
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Events/Index', [
            'events' => $events->through(fn (SubscriptionEvent $event): array => [
                'id' => $event->getKey(),
                'type' => $event->event_type->value,
                'business_public_id' => $event->subscription->business->public_id,
                'business_name' => $event->subscription->business->name,
                'business_slug' => $event->subscription->business->slug,
                'plan_name' => $event->subscription->plan->name,
                'subscription_id' => $event->subscription_id,
                'created_at' => $event->created_at->toISOString(),
            ]),
            'filters' => [
                'event_type' => $filters['event_type'] ?? '',
                'search' => $search,
            ],
            'eventTypes' => array_map(
                fn (SubscriptionEventType $type): array => [
                    'value' => $type->value,
                    'label' => $this->eventTypeLabel($type),
                ],
                SubscriptionEventType::cases(),
            ),
        ]);
    }

    private function eventTypeLabel(SubscriptionEventType $type): string
    {
        return match ($type) {
            SubscriptionEventType::Created => 'ایجاد اشتراک',
            SubscriptionEventType::Renewed => 'تمدید اشتراک',
            SubscriptionEventType::Upgraded => 'ارتقای طرح',
            SubscriptionEventType::Downgraded => 'کاهش طرح',
            SubscriptionEventType::Canceled => 'لغو اشتراک',
            SubscriptionEventType::Expired => 'انقضای اشتراک',
            SubscriptionEventType::TrialStarted => 'شروع دوره آزمایشی',
            SubscriptionEventType::TrialEnded => 'پایان دوره آزمایشی',
            SubscriptionEventType::PaymentSubmitted => 'ثبت پرداخت',
            SubscriptionEventType::PaymentApproved => 'تأیید پرداخت',
            SubscriptionEventType::PaymentRejected => 'رد پرداخت',
        };
    }
}
