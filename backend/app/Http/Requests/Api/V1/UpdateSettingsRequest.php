<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization handled by permission middleware
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array', 'max:100'],
            'settings.*.key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9_.]+$/'],
            'settings.*.value' => ['nullable'],
            'settings.*.type' => ['sometimes', 'in:string,integer,boolean,json'],
            'settings.*.group' => ['sometimes', 'string', 'max:60'],
        ];
    }
}
