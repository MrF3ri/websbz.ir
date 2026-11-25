<?php

namespace App\Modules\Branch\Http\Requests;

use App\Modules\Business\Models\Business;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBranchCatalogRequest extends FormRequest
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
        return [
            'categories' => ['present', 'array'],
            'categories.*.public_id' => ['required', 'string', 'distinct'],
            'categories.*.is_visible' => ['required', 'boolean'],
            'categories.*.sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'products' => ['present', 'array'],
            'products.*.public_id' => ['required', 'string', 'distinct'],
            'products.*.name_override' => ['nullable', 'string', 'max:160'],
            'products.*.description_override_enabled' => ['required', 'boolean'],
            'products.*.description_override' => ['nullable', 'string', 'max:5000'],
            'products.*.base_price_override' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'products.*.discount_override_mode' => ['required', Rule::in(['inherit', 'set', 'clear'])],
            'products.*.discount_price_override' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'products.*.is_visible' => ['required', 'boolean'],
            'products.*.is_available' => ['required', 'boolean'],
            'products.*.track_inventory' => ['required', 'boolean'],
            'products.*.stock_quantity' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'products.*.sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
