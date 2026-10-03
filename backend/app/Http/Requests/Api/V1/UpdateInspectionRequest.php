<?php

namespace App\Http\Requests\Api\V1;

use App\Domains\Leasing\Models\MoveOutInspection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inspection_date' => ['sometimes', 'date'],
            'condition' => ['sometimes', Rule::in(MoveOutInspection::CONDITIONS)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'damage_observations' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
