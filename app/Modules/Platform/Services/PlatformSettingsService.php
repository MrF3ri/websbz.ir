<?php

namespace App\Modules\Platform\Services;

use App\Models\User;
use App\Modules\Platform\Models\PlatformAuditLog;
use App\Modules\Platform\Models\PlatformSetting;
use Illuminate\Support\Facades\DB;

class PlatformSettingsService
{
    /** @return array{bank_name: string, card_number: string, card_holder: string} */
    public function cardDetails(): array
    {
        $values = PlatformSetting::query()
            ->whereIn('key', ['bank_name', 'card_number', 'card_holder'])
            ->pluck('value', 'key');

        return [
            'bank_name' => (string) ($values['bank_name'] ?? ''),
            'card_number' => (string) ($values['card_number'] ?? ''),
            'card_holder' => (string) ($values['card_holder'] ?? ''),
        ];
    }

    /** @param array{bank_name: string, card_number: string, card_holder: string} $details */
    public function updateCardDetails(array $details, User $actor): void
    {
        DB::transaction(function () use ($details, $actor): void {
            $before = $this->auditSnapshot($this->cardDetails());
            $after = $this->auditSnapshot($details);

            foreach ($details as $key => $value) {
                PlatformSetting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value, 'updated_by' => $actor->getKey()],
                );
            }

            if ($before !== $after) {
                PlatformAuditLog::query()->create([
                    'actor_id' => $actor->getKey(),
                    'action' => 'platform.settings.card_details.updated',
                    'target_type' => 'platform_settings',
                    'target_id' => 'card_details',
                    'before_data' => $before,
                    'after_data' => $after,
                ]);
            }
        }, attempts: 3);
    }

    public function hasCardDetails(): bool
    {
        $details = $this->cardDetails();

        return $details['bank_name'] !== ''
            && $details['card_number'] !== ''
            && $details['card_holder'] !== '';
    }

    /**
     * Card numbers are never persisted in audit snapshots; only their last four digits are retained.
     *
     * @param array{bank_name: string, card_number: string, card_holder: string} $details
     * @return array{bank_name: string, card_number_last4: string|null, card_holder: string}
     */
    private function auditSnapshot(array $details): array
    {
        $number = $details['card_number'];

        return [
            'bank_name' => $details['bank_name'],
            'card_number_last4' => $number === '' ? null : substr($number, -4),
            'card_holder' => $details['card_holder'],
        ];
    }
}
