<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization handled by permission middleware
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'max:1024'],
            'agency_id' => ['nullable', 'integer', Rule::exists('agencies', 'id')],
            'is_active' => ['sometimes', 'boolean'],
            'role_slugs' => ['sometimes', 'array'],
            'role_slugs.*' => ['string', 'max:60'],
        ];
    }
}
