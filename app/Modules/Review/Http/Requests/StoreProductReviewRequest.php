<?php

namespace App\Modules\Review\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['display_name', 'body'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'display_name' => ['nullable', 'string', 'max:80'],
            'body' => ['nullable', 'string', 'max:1500'],
        ];
    }
}
