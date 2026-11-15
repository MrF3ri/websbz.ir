<?php

namespace App\Modules\Subscription\Enums;

enum GatewayVerificationResult: string
{
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case PENDING_OR_INVALID = 'pending_or_invalid';
}
