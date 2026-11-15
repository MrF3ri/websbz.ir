<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Models\User;
use App\Modules\Subscription\Actions\ReviewManualPaymentAction;
use App\Modules\Subscription\Enums\PaymentStatus;
use App\Modules\Subscription\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminPaymentController
{
    public function index(): Response
    {
        $payments = Payment::query()
            ->where('status', PaymentStatus::PendingReview)
            ->with(['subscription.business', 'targetPlan'])
            ->orderBy('submitted_at')
            ->paginate(20);

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $payments->through(fn (Payment $payment): array => [
                'id' => $payment->getKey(),
                'business_name' => $payment->subscription->business->name,
                'plan_name' => $payment->targetPlan->name,
                'amount' => $payment->amount,
                'bank_tracking_number' => $payment->bank_tracking_number,
                'submitted_at' => $payment->submitted_at?->toISOString(),
                'receipt_url' => route('admin.payments.receipt', $payment),
            ]),
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
        abort_unless($user instanceof User && $user->is_platform_admin, 403);

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
