<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MergesOnUpdate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TagRequest extends FormRequest
{
    use MergesOnUpdate;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [$this->required(), 'string', 'max:80'],
            'slug' => ['nullable', 'string', 'max:80', 'alpha_dash',
                Rule::unique('tags', 'slug')->ignore($this->route('tag')?->id)->whereNull('deleted_at')],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }
}
