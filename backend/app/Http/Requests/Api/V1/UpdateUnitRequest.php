<?php

namespace App\Http\Requests\Api\V1;

use App\Domains\Property\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unitId = $this->route('unit');

        return [
            'unit_number' => [
                'sometimes', 'string', 'max:40',
                Rule::unique('units', 'unit_number')
                    ->where('building_id', $this->getUnitBuildingId($unitId))
                    ->whereNull('deleted_at')
                    ->ignore($unitId),
            ],
            'floor' => ['nullable', 'integer', 'min:-5', 'max:200'],
            'unit_type' => ['sometimes', Rule::in(Unit::TYPES)],
            'area_sqft' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:50'],
            'bathrooms' => ['nullable', 'integer', 'min:0', 'max:50'],
            'status' => ['sometimes', Rule::in(Unit::STATUSES)],
            'market_rent' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function getUnitBuildingId($unitId): ?int
    {
        return Unit::withoutGlobalScopes()->where('id', $unitId)->value('building_id');
    }
}
