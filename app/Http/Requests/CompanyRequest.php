<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MergesOnUpdate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    use MergesOnUpdate;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [$this->required(), 'string', 'max:200'],
            'domain' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:120'],
            'size' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:120'],
            'annual_revenue' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:10000'],
            'custom_fields' => ['nullable', 'array'],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')->whereNull('deleted_at')],
        ];
    }
}
