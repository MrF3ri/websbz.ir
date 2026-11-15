<?php

namespace App\Modules\Platform\Services;

use App\Models\User;
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
            foreach ($details as $key => $value) {
                PlatformSetting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value, 'updated_by' => $actor->getKey()],
                );
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
}
