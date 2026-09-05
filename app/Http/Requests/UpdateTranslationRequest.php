<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'locale' => [
                'sometimes',
                'string',
                'exists:locales,code',
            ],
            'key' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'content' => [
                'sometimes',
                'string',
            ],
            'tags' => [
                'sometimes',
                'array',
            ],
            'tags.*' => [
                'string',
                'exists:tags,name',
            ],
        ];
    }
}