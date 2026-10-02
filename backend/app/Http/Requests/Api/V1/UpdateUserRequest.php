<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization handled by permission middleware
    }

    public function rules(): array
    {
        $userId = $this->route('user');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['sometimes', 'string', 'min:8', 'max:1024'],
            'is_active' => ['sometimes', 'boolean'],
            'role_slugs' => ['sometimes', 'array'],
            'role_slugs.*' => ['string', 'max:60'],
        ];
    }
}
