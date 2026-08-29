<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MergesOnUpdate;
use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactRequest extends FormRequest
{
    use MergesOnUpdate;

    /** Authorization lives in ContactPolicy, applied by the controller. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => [$this->required(), 'string', 'max:100'],
            'last_name' => [$this->required(), 'string', 'max:100'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'lifecycle_stage' => ['sometimes', Rule::in(Contact::LIFECYCLE_STAGES)],
            'lead_status' => ['sometimes', Rule::in(Contact::LEAD_STATUSES)],
            'lead_score' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'source' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'custom_fields' => ['nullable', 'array'],
            'last_contacted_at' => ['nullable', 'date'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')->whereNull('deleted_at')],
        ];
    }

    public function attributes(): array
    {
        return [
            'company_id' => 'company',
            'owner_id' => 'owner',
        ];
    }
}
