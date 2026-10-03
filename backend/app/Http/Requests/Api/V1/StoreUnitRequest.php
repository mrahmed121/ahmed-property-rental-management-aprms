<?php

namespace App\Http\Requests\Api\V1;

use App\Domains\Property\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => ['required', 'integer', 'exists:buildings,id'],
            'unit_number' => [
                'required', 'string', 'max:40',
                // Unique within the building (DB constraint is the backstop).
                Rule::unique('units', 'unit_number')
                    ->where('building_id', $this->input('building_id'))
                    ->whereNull('deleted_at'),
            ],
            'floor' => ['nullable', 'integer', 'min:-5', 'max:200'],
            'unit_type' => ['required', Rule::in(Unit::TYPES)],
            'area_sqft' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:50'],
            'bathrooms' => ['nullable', 'integer', 'min:0', 'max:50'],
            'status' => ['sometimes', Rule::in(Unit::STATUSES)],
            'market_rent' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
