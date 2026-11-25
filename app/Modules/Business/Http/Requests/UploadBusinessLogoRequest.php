<?php

namespace App\Modules\Business\Http\Requests;

use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Foundation\Http\FormRequest;

class UploadBusinessLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $business = app(CurrentBusiness::class)->get();

        return $business !== null
            && $this->user()?->can('update', $business) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'logo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
