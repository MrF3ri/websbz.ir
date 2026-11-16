<?php

namespace App\Modules\Platform\Services;

use App\Modules\Subscription\Models\Plan;

class PlatformConfigurationHealthService
{
    /**
     * @return list<array{key: string, label: string, status: string, message: string, value: string|null}>
     */
    public function checks(): array
    {
        $paymentDestinationConfigured = app(PlatformSettingsService::class)->hasCardDetails();
        $activePlanCount = Plan::query()->where('is_active', true)->count();

        $localDisk = config('filesystems.disks.local', []);
        $publicDisk = config('filesystems.disks.public', []);
        $privateReceiptStorageConfigured = is_array($localDisk)
            && ($localDisk['driver'] ?? null) === 'local'
            && is_string($localDisk['root'] ?? null)
            && $localDisk['root'] !== ''
            && $localDisk['root'] !== (is_array($publicDisk) ? ($publicDisk['root'] ?? null) : null);

        $queueConnectionName = config('queue.default');
        $queueConnection = is_string($queueConnectionName)
            ? config('queue.connections.'.$queueConnectionName)
            : null;
        $queueDriver = is_array($queueConnection) ? ($queueConnection['driver'] ?? null) : null;
        $queueConfigured = is_string($queueDriver) && $queueDriver !== 'sync';

        return [
            [
                'key' => 'payment_destination',
                'label' => 'مقصد پرداخت دستی',
                'status' => $paymentDestinationConfigured ? 'ok' : 'warning',
                'message' => $paymentDestinationConfigured
                    ? 'اطلاعات مقصد پرداخت کامل ثبت شده است.'
                    : 'اطلاعات کارت مقصد پرداخت در تنظیمات پلتفرم کامل نیست.',
                'value' => null,
            ],
            [
                'key' => 'active_plans',
                'label' => 'طرح‌های قابل فروش',
                'status' => $activePlanCount > 0 ? 'ok' : 'warning',
                'message' => $activePlanCount > 0
                    ? 'حداقل یک طرح فعال برای انتخاب وجود دارد.'
                    : 'هیچ طرح فعالی برای انتخاب و خرید وجود ندارد.',
                'value' => (string) $activePlanCount,
            ],
            [
                'key' => 'private_receipt_storage',
                'label' => 'ذخیره‌سازی خصوصی رسیدها',
                'status' => $privateReceiptStorageConfigured ? 'ok' : 'warning',
                'message' => $privateReceiptStorageConfigured
                    ? 'دیسک رسیدها روی مسیر محلی جدا از فضای عمومی تنظیم شده است.'
                    : 'دیسک رسیدها باید روی فضای خصوصی و جدا از فضای عمومی تنظیم شود.',
                'value' => null,
            ],
            [
                'key' => 'queue_backend',
                'label' => 'بک‌اند صف پردازش',
                'status' => $queueConfigured ? 'ok' : 'warning',
                'message' => $queueConfigured
                    ? 'اتصال صف پردازش پیکربندی شده است؛ اجرای worker در این بررسی پایش نمی‌شود.'
                    : 'اتصال صف پردازش روی حالت هم‌زمان یا پیکربندی‌نشده قرار دارد.',
                'value' => is_string($queueDriver) ? $queueDriver : null,
            ],
        ];
    }
}
