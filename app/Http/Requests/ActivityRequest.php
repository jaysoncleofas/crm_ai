<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MergesOnUpdate;
use App\Models\Activity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivityRequest extends FormRequest
{
    use MergesOnUpdate;

    /** Records an activity may hang off, keyed by morph alias. */
    public const RELATED_TYPES = ['contact' => 'contacts', 'company' => 'companies', 'deal' => 'deals'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => [$this->required(), Rule::in(Activity::TYPES)],
            'subject' => [$this->required(), 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:20000'],
            'status' => ['sometimes', Rule::in([
                Activity::STATUS_PLANNED, Activity::STATUS_COMPLETED, Activity::STATUS_CANCELED,
            ])],
            'direction' => ['nullable', Rule::in(['inbound', 'outbound'])],
            'outcome' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'due_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'related_type' => ['nullable', 'required_with:related_id', Rule::in(array_keys(self::RELATED_TYPES))],
            'related_id' => [
                'nullable',
                'integer',
                'required_with:related_type',
                Rule::exists(self::RELATED_TYPES[$this->input('related_type')] ?? 'contacts', 'id')
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
