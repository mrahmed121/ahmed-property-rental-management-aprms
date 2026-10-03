<?php

namespace App\Http\Requests\Api\V1;

use App\Domains\Property\Models\Building;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBuildingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'name' => [
                'required', 'string', 'max:255',
                // Unique per property (DB constraint is the backstop).
                Rule::unique('buildings', 'name')
                    ->where('property_id', $this->input('property_id'))
                    ->whereNull('deleted_at'),
            ],
            'floors' => ['nullable', 'integer', 'min:0', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::in(Building::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
