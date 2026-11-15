<?php

namespace App\Modules\Category\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'category_public_ids' => ['required', 'array', 'min:2', 'max:100'],
            'category_public_ids.*' => ['required', 'string', 'size:26', 'distinct'],
        ];
    }
}
