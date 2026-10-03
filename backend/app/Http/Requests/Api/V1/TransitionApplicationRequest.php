<?php

namespace App\Http\Requests\Api\V1;

use App\Domains\Leasing\Models\TenantApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(TenantApplication::STATUSES)],
            'decision_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
