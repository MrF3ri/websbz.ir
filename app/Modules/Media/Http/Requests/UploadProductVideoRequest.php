<?php

namespace App\Modules\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadProductVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // ProductPolicy is checked after resolving the tenant-owned product.
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'video' => ['required', 'file', 'max:10240'],
        ];
    }
}
