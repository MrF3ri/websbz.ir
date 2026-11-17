<?php

namespace App\Modules\Branch\Http\Requests;

use App\Modules\Business\Models\Business;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBranchRequest extends FormRequest
{
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
        ];
    }
}
