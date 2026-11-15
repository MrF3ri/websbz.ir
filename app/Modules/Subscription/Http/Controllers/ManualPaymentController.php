<?php

namespace App\Modules\Subscription\Http\Controllers;

use App\Modules\Business\Models\Business;
use App\Modules\Platform\Services\PlatformSettingsService;
use App\Modules\Subscription\Actions\SubmitManualPaymentAction;
use App\Modules\Subscription\Models\Payment;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ManualPaymentController
{
    public function store(
        Request $request,
        CurrentBusiness $currentBusiness,
        PlatformSettingsService $platformSettings,
        SubmitManualPaymentAction $submitPayment,
    ): RedirectResponse {
        if (! $platformSettings->hasCardDetails()) {
            throw ValidationException::withMessages([
                'payment' => 'اطلاعات کارت بانکی پلتفرم هنوز ثبت نشده است. لطفاً بعداً تلاش کنید.',
            ]);
        }

        $request->merge([
            'bank_tracking_number' => trim((string) $request->input('bank_tracking_number')),
        ]);

        $validated = $request->validate([
            'plan_id' => [
                'required',
                'integer',
                Rule::exists('plans', 'id')->where('is_active', true),
            ],
            'bank_tracking_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('payments', 'bank_tracking_number'),
            ],
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $business = $currentBusiness->get();
        abort_unless($business instanceof Business, 404);
        Gate::authorize('update', $business);

        $subscription = $business->subscriptions()
            ->latest('id')
            ->first();

        abort_unless($subscription instanceof Subscription, 404);

        $plan = Plan::query()
            ->where('is_active', true)
            ->whereKey($validated['plan_id'])
            ->firstOrFail();

        $receiptPath = $request->file('receipt')?->store(
            'payment-receipts/'.$business->getKey(),
            'local',
        );

        abort_unless(is_string($receiptPath), 500);

        try {
            $submitPayment->execute(
                $subscription,
                $plan,
                $validated['bank_tracking_number'],
                $receiptPath,
            );
        } catch (QueryException $exception) {
            Storage::disk('local')->delete($receiptPath);

            if (in_array($exception->getCode(), ['23000', '23505'], true)) {
                throw ValidationException::withMessages([
                    'bank_tracking_number' => 'این شماره پیگیری قبلاً ثبت شده است.',
                ]);
            }

            throw $exception;
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($receiptPath);

            throw $exception;
        }

        return back()->with('success', 'رسید پرداخت ثبت شد و پس از بررسی مدیر نتیجه اعلام می‌شود.');
    }

    public function receipt(
        Payment $payment,
        CurrentBusiness $currentBusiness,
    ): StreamedResponse {
        $business = $currentBusiness->get();
        abort_unless($business instanceof Business, 404);
        Gate::authorize('update', $business);

        abort_unless(
            $payment->subscription()
                ->where('business_id', $business->getKey())
                ->exists()
                && is_string($payment->receipt_path)
                && $payment->receipt_path !== '',
            404,
        );

        return Storage::disk('local')->download(
            $payment->receipt_path,
            'payment-receipt-'.$payment->getKey().'.'.pathinfo($payment->receipt_path, PATHINFO_EXTENSION),
        );
    }
}
