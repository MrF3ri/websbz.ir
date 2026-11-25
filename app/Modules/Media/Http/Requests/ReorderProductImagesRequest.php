<?php

namespace App\Modules\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderProductImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'media_public_ids' => ['required', 'array', 'min:1', 'max:5'],
            'media_public_ids.*' => ['required', 'string', 'size:26', 'distinct'],
        ];
    }
}
