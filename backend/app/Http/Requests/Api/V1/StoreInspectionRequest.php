<?php

namespace App\Http\Requests\Api\V1;

use App\Domains\Leasing\Models\MoveOutInspection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lease_id' => ['required', 'integer', 'exists:leases,id'],
            'inspection_date' => ['required', 'date'],
            'condition' => ['required', Rule::in(MoveOutInspection::CONDITIONS)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'damage_observations' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
