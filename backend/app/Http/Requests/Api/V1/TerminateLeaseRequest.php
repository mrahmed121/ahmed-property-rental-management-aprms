<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class TerminateLeaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'termination_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
