<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Models\User;
use App\Modules\Subscription\Actions\ReviewManualPaymentAction;
use App\Modules\Subscription\Enums\PaymentStatus;
use App\Modules\Subscription\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminPaymentController
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_column(PaymentStatus::cases(), 'value'))],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim($filters['search'] ?? '');

        $payments = Payment::query()
            ->with(['subscription.business', 'targetPlan'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when(!array_key_exists('status', $filters), fn (Builder $query) => $query->where('status', PaymentStatus::PendingReview->value))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.mb_strtolower($search).'%';

                $query->where(function (Builder $nested) use ($term): void {
                    $nested->whereRaw('LOWER(bank_tracking_number) LIKE ?', [$term])
                        ->orWhereHas('subscription.business', fn (Builder $business) => $business
                            ->whereRaw('LOWER(name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(slug) LIKE ?', [$term]));
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $payments->through(fn (Payment $payment): array => [
                'id' => $payment->getKey(),
                'business_name' => $payment->subscription->business->name,
                'plan_name' => $payment->targetPlan->name,
                'amount' => $payment->amount,
                'status' => $payment->status->value,
                'bank_tracking_number' => $payment->bank_tracking_number,
                'submitted_at' => $payment->submitted_at?->toISOString(),
                'paid_at' => $payment->paid_at?->toISOString(),
                'receipt_url' => filled($payment->receipt_path)
                    ? route('admin.payments.receipt', $payment)
                    : null,
            ]),
            'filters' => [
                'status' => $filters['status'] ?? (array_key_exists('status', $filters) ? '' : PaymentStatus::PendingReview->value),
                'search' => $search,
            ],
            'statuses' => array_map(
                fn (PaymentStatus $status): array => [
                    'value' => $status->value,
                    'label' => match ($status) {
                        PaymentStatus::Pending => 'در انتظار',
                        PaymentStatus::Successful => 'موفق',
                        PaymentStatus::Failed => 'ناموفق',
                        PaymentStatus::PendingReview => 'در انتظار بررسی',
                        PaymentStatus::Approved => 'تأییدشده',
                        PaymentStatus::Rejected => 'ردشده',
                    },
                ],
                PaymentStatus::cases(),
            ),
        ]);
    }

    public function receipt(Payment $payment): StreamedResponse
    {
        abort_unless(is_string($payment->receipt_path) && $payment->receipt_path !== '', 404);

        return Storage::disk('local')->download(
            $payment->receipt_path,
            'payment-receipt-'.$payment->getKey().'.'.pathinfo($payment->receipt_path, PATHINFO_EXTENSION),
        );
    }

    public function approve(
        Payment $payment,
        Request $request,
        ReviewManualPaymentAction $reviewPayment,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isPlatformAdmin(), 403);

        $reviewPayment->approve($payment, $user);

        return back()->with('success', 'پرداخت تأیید و اشتراک تمدید شد.');
    }

    public function reject(
        Payment $payment,
        Request $request,
        ReviewManualPaymentAction $reviewPayment,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User && $user->is_platform_admin, 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $reviewPayment->reject($payment, $user, $validated['reason']);

        return back()->with('success', 'پرداخت رد شد.');
    }
}
