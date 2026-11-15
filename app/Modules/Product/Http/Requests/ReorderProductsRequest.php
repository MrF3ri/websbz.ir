<?php

namespace App\Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'product_public_ids' => ['required', 'array', 'min:2', 'max:100'],
            'product_public_ids.*' => ['required', 'string', 'size:26', 'distinct'],
        ];
    }
}
