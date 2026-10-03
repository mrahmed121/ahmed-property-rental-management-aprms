<?php

namespace App\Http\Requests\Api\V1;

use App\Domains\Leasing\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'status' => ['sometimes', Rule::in(Tenant::STATUSES)],
            'kyc_status' => ['sometimes', Rule::in(Tenant::KYC_STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
