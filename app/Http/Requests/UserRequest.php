<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MergesOnUpdate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    use MergesOnUpdate;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => [$this->required(), 'string', 'max:150'],
            'email' => [$this->required(), 'email:rfc', 'max:255',
                Rule::unique('users', 'email')->ignore($userId)->whereNull('deleted_at')],
            'password' => [$this->isPartial() ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'job_title' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ];
    }
}
