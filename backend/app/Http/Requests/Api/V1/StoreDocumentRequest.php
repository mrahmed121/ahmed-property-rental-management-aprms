<?php

namespace App\Http\Requests\Api\V1;

use App\Domains\Property\Models\PropertyDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_type' => ['required', Rule::in(array_keys(PropertyDocument::ALLOWED_PARENTS))],
            'parent_id' => ['required', 'integer', 'min:1'],
            'file' => ['required', 'file', 'max:10240'], // 10 MB; mime checked in service
            'name' => ['nullable', 'string', 'max:255'],
            'document_type' => ['sometimes', Rule::in(PropertyDocument::TYPES)],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
