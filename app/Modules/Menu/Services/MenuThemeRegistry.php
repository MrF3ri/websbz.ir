<?php

namespace App\Modules\Menu\Services;

use App\Modules\Business\Models\Business;
use App\Modules\Subscription\Services\EntitlementService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MenuThemeRegistry
{
    /**
     * The first three templates are available on Economic; Professional and Top
     * receive the complete predefined registry. Availability comes from the
     * subscription snapshot, never from plan-name checks.
     *
     * @var array<string, array{name: string, description: string, layout: string}>
     */
    private const TEMPLATES = [
        'classic' => ['name' => 'کلاسیک', 'description' => 'چیدمان آشنا با تمرکز بر خوانایی', 'layout' => 'classic'],
        'minimal' => ['name' => 'مینیمال', 'description' => 'فضای خلوت و تأکید بر نام و قیمت', 'layout' => 'minimal'],
        'editorial' => ['name' => 'مجله‌ای', 'description' => 'نمایش تصویری و بخش‌بندی برجسته', 'layout' => 'editorial'],
        'modern' => ['name' => 'مدرن', 'description' => 'کارت‌های جمع‌وجور با جزئیات نرم', 'layout' => 'modern'],
        'cafe' => ['name' => 'کافه', 'description' => 'سبک گرم مناسب کافه و نوشیدنی', 'layout' => 'cafe'],
        'contrast' => ['name' => 'کنتراست', 'description' => 'تضاد بصری بیشتر برای مرور سریع', 'layout' => 'contrast'],
    ];

    /** @return array<string, array{name: string, description: string, layout: string}> */
    public function availableFor(Business $business, EntitlementService $entitlements): array
    {
        $this->assertBusinessContext($business, $entitlements);
        $limit = $entitlements->getLimit('menu_template_count');

        if ($limit === -1) {
            return self::TEMPLATES;
        }

        if ($limit === null || $limit < 1) {
            return [];
        }

        return array_slice(self::TEMPLATES, 0, (int) $limit, true);
    }

    /** @return array{name: string, description: string, layout: string} */
    public function findAvailable(
        Business $business,
        EntitlementService $entitlements,
        string $template,
    ): array {
        $available = $this->availableFor($business, $entitlements);

        if (! isset($available[$template])) {
            throw new AccessDeniedHttpException('The selected menu template is not available for this plan.');
        }

        return $available[$template];
    }

    private function assertBusinessContext(Business $business, EntitlementService $entitlements): void
    {
        if (! $entitlements->isForBusiness($business)) {
            throw new \LogicException('The entitlement service is bound to a different business.');
        }
    }
}
