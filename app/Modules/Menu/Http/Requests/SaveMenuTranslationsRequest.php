<?php

namespace App\Modules\Menu\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveMenuTranslationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // BusinessPolicy and locale entitlements are enforced by the controller.
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'menu_title' => ['required', 'string', 'max:160'],
            'categories' => ['present', 'array'],
            'categories.*.public_id' => ['required', 'string', 'size:26', 'distinct'],
            'categories.*.name' => ['required', 'string', 'max:120'],
            'products' => ['present', 'array'],
            'products.*.public_id' => ['required', 'string', 'size:26', 'distinct'],
            'products.*.name' => ['required', 'string', 'max:180'],
            'products.*.description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
