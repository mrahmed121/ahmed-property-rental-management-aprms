<?php

namespace App\Http\Requests\Api\V1;

use App\Domains\Leasing\Models\TenantApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScreeningDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clear' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'kyc_status' => ['nullable', Rule::in(TenantApplication::KYC_STATUSES)],
        ];
    }
}
