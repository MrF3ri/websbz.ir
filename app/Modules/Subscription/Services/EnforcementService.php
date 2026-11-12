<?php

namespace App\Modules\Subscription\Services;

use App\Modules\Business\Models\Business;
use LogicException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EnforcementService
{
    public function __construct(
        private readonly EntitlementService $entitlements,
    ) {}

    /**
     * Determine whether the business can create another resource under a numeric limit.
     */
    public function canCreate(Business $business, string $featureKey, int $currentCount): bool
    {
        $this->assertBusinessContext($business);

        $limit = $this->entitlements->getLimit($featureKey);

        if ($limit === -1) {
            return true;
        }

        if ($limit === null) {
            return false;
        }

        return $currentCount < $limit;
    }

    /**
     * Require a boolean feature to be enabled for the business.
     */
    public function requireFeature(Business $business, string $featureKey): void
    {
        $this->assertBusinessContext($business);

        if (! $this->entitlements->hasFeature($featureKey)) {
            throw new AccessDeniedHttpException(
                sprintf('The [%s] feature is not available for this plan.', $featureKey),
            );
        }
    }

    /**
     * Require the business to remain within a numeric feature limit.
     */
    public function requireLimit(Business $business, string $featureKey, int $currentCount): void
    {
        if (! $this->canCreate($business, $featureKey, $currentCount)) {
            throw new AccessDeniedHttpException(
                sprintf('The [%s] limit has been reached for this plan.', $featureKey),
            );
        }
    }

    /**
     * Prevent a business-bound entitlement service from being reused for another tenant.
     */
    private function assertBusinessContext(Business $business): void
    {
        if (! $this->entitlements->isForBusiness($business)) {
            throw new LogicException('The entitlement service is bound to a different business.');
        }
    }
}
