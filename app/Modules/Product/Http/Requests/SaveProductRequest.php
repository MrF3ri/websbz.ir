<?php

namespace App\Modules\Product\Http\Requests;

use App\Modules\Product\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // ProductPolicy is invoked once by the controller; this request owns validation only.
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_available')) {
            $this->merge(['is_available' => $this->boolean('is_available')]);
        }
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'category_public_id' => ['required', 'string', 'size:26'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'base_price' => ['required', 'integer', 'min:0'],
            'discount_price' => ['nullable', 'integer', 'min:0', 'lt:base_price'],
            'is_available' => ['sometimes', 'boolean'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
        ];
    }
}
