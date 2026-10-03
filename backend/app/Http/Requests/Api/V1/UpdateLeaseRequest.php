<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after:start_date'],
            'monthly_rent' => ['sometimes', 'numeric', 'min:0', 'max:100000000'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
