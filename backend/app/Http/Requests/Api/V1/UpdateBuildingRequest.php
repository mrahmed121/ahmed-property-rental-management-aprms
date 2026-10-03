<?php

namespace App\Http\Requests\Api\V1;

use App\Domains\Property\Models\Building;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBuildingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $buildingId = $this->route('building');

        return [
            'name' => [
                'sometimes', 'string', 'max:255',
                Rule::unique('buildings', 'name')
                    ->where('property_id', $this->getBuildingPropertyId($buildingId))
                    ->whereNull('deleted_at')
                    ->ignore($buildingId),
            ],
            'floors' => ['nullable', 'integer', 'min:0', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::in(Building::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function getBuildingPropertyId($buildingId): ?int
    {
        return Building::withoutGlobalScopes()->where('id', $buildingId)->value('property_id');
    }
}
