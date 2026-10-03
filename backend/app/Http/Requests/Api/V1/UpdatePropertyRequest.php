<?php

namespace App\Http\Requests\Api\V1;

use App\Domains\Property\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'property_type' => ['sometimes', Rule::in(Property::TYPES)],
            'address' => ['sometimes', 'string', 'max:500'],
            'city' => ['sometimes', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::in(Property::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
