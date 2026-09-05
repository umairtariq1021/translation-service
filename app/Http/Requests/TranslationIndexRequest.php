<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TranslationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
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
            'tag' => [
                'sometimes',
                'string',
                'exists:tags,name',
            ],
            'search' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}