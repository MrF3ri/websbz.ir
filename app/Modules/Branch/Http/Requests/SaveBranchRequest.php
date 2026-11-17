<?php

namespace App\Modules\Branch\Http\Requests;

use App\Modules\Business\Models\Business;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SaveBranchRequest extends FormRequest
{
    private const WEEKDAYS = ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'];

    public function authorize(): bool
    {
        $business = app(CurrentBusiness::class)->get();

        return $business instanceof Business
            && $this->user()?->can('update', $business) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $business = app(CurrentBusiness::class)->get();
        abort_unless($business instanceof Business, 404);

        $branch = null;
        $publicId = $this->route('branchPublicId');

        if (is_string($publicId)) {
            $branch = $business->branches()->where('public_id', $publicId)->firstOrFail();
        }

        $slugRule = Rule::unique('branches', 'slug')
            ->where(fn ($query) => $query->where('business_id', $business->getKey()));

        if ($branch !== null) {
            $slugRule->ignore($branch->getKey());
        }

        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slugRule],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:254'],
            'address' => ['nullable', 'string', 'max:5000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'opening_hours' => ['sometimes', 'nullable', 'array:'.implode(',', self::WEEKDAYS)],
            'opening_hours.*' => ['array', 'max:2'],
            'opening_hours.*.*' => ['array:open,close'],
            'opening_hours.*.*.open' => ['required', 'date_format:H:i'],
            'opening_hours.*.*.close' => ['required', 'date_format:H:i'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $schedule = $this->input('opening_hours');

            if (! is_array($schedule)) {
                return;
            }

            foreach (self::WEEKDAYS as $day) {
                $periods = $schedule[$day] ?? [];

                if (! is_array($periods)) {
                    continue;
                }

                $normalized = [];
                foreach ($periods as $index => $period) {
                    if (! is_array($period)
                        || ! isset($period['open'], $period['close'])
                        || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $period['open'])
                        || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $period['close'])) {
                        continue;
                    }

                    if ($period['close'] <= $period['open']) {
                        $validator->errors()->add(
                            "opening_hours.{$day}.{$index}.close",
                            'ساعت پایان باید بعد از ساعت شروع باشد.'
                        );
                    }

                    $normalized[] = [
                        'index' => $index,
                        'open' => $period['open'],
                        'close' => $period['close'],
                    ];
                }

                usort($normalized, fn (array $a, array $b): int => strcmp($a['open'], $b['open']));

                for ($i = 1; $i < count($normalized); $i++) {
                    $previous = $normalized[$i - 1];
                    $current = $normalized[$i];

                    if ($current['open'] < $previous['close']) {
                        $validator->errors()->add(
                            "opening_hours.{$day}.{$current['index']}.open",
                            'بازه‌های زمانی یک روز نباید با هم تداخل داشته باشند.'
                        );
                    }
                }
            }
        });
    }
}
