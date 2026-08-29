<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MergesOnUpdate;
use App\Models\Deal;
use App\Models\PipelineStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DealRequest extends FormRequest
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
            'pipeline_id' => [$this->required(), 'integer', Rule::exists('pipelines', 'id')->whereNull('deleted_at')],
            'pipeline_stage_id' => [$this->required(), 'integer', Rule::exists('pipeline_stages', 'id')->whereNull('deleted_at')],
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->whereNull('deleted_at')],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'amount' => ['sometimes', 'numeric', 'min:0', 'max:99999999999.99'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'status' => ['sometimes', Rule::in([Deal::STATUS_OPEN, Deal::STATUS_WON, Deal::STATUS_LOST])],
            'probability' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'won_reason' => ['nullable', 'string', 'max:255'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:10000'],
            'custom_fields' => ['nullable', 'array'],
            'contacts' => ['sometimes', 'array'],
            'contacts.*' => ['integer', Rule::exists('contacts', 'id')->whereNull('deleted_at')],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')->whereNull('deleted_at')],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $stageId = $this->input('pipeline_stage_id');
                $pipelineId = $this->input('pipeline_id') ?? $this->route('deal')?->pipeline_id;

                if (! $stageId || ! $pipelineId || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $belongs = PipelineStage::query()
                    ->whereKey($stageId)
                    ->where('pipeline_id', $pipelineId)
                    ->exists();

                if (! $belongs) {
                    $validator->errors()->add('pipeline_stage_id', 'The selected stage does not belong to this pipeline.');
                }
            },
        ];
    }
}
