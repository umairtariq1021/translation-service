<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'locale' => [
                'required',
                'string',
                'exists:locales,code',
            ],
            'key' => [
                'required',
                'string',
                'max:255',
            ],
            'content' => [
                'required',
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