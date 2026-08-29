<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MergesOnUpdate;
use App\Models\PipelineStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PipelineRequest extends FormRequest
{
    use MergesOnUpdate;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $pipelineId = $this->route('pipeline')?->id;

        return [
            'name' => [$this->required(), 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150', 'alpha_dash',
                Rule::unique('pipelines', 'slug')->ignore($pipelineId)->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_default' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'stages' => ['sometimes', 'array', 'min:1'],
            'stages.*.id' => ['nullable', 'integer', Rule::exists('pipeline_stages', 'id')],
            'stages.*.name' => ['required', 'string', 'max:120'],
            'stages.*.probability' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'stages.*.type' => ['sometimes', Rule::in([
                PipelineStage::TYPE_OPEN, PipelineStage::TYPE_WON, PipelineStage::TYPE_LOST,
            ])],
            'stages.*.color' => ['sometimes', 'string', 'max:20'],
        ];
    }
}
