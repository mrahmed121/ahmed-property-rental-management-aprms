<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'application_id' => ['nullable', 'integer', 'exists:tenant_applications,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'monthly_rent' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'agency_id' => ['nullable', 'integer', 'exists:agencies,id'],
        ];
    }
}
