<?php

namespace App\Modules\Subscription\Enums;

enum SubscriptionEventType: string
{
    case Created = 'created';
    case Renewed = 'renewed';
    case Upgraded = 'upgraded';
    case Downgraded = 'downgraded';
    case Canceled = 'canceled';
    case Expired = 'expired';
    case TrialStarted = 'trial_started';
    case TrialEnded = 'trial_ended';
    case PaymentSubmitted = 'payment_submitted';
    case PaymentApproved = 'payment_approved';
    case PaymentRejected = 'payment_rejected';
}
