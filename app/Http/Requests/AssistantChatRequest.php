<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssistantChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:2', 'max:2000'],
            'conversation_id' => [
                'nullable',
                'integer',
                // Scoped to the caller so a guessed id cannot open someone
                // else's transcript.
                Rule::exists('ai_conversations', 'id')
                    ->where('user_id', $this->user()->id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
