<?php

namespace App\Http\Requests\Api\V1;

use App\Domains\Property\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission middleware + service scoping
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'property_type' => ['required', Rule::in(Property::TYPES)],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::in(Property::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            // Super Admin may target an agency; others are forced to their own.
            'agency_id' => ['nullable', 'integer', 'exists:agencies,id'],
        ];
    }
}
