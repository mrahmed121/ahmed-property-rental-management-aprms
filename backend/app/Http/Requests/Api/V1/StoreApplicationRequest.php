<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'agency_id' => ['nullable', 'integer', 'exists:agencies,id'],
        ];
    }
}
