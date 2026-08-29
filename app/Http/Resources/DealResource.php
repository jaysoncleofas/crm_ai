<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\IncludesAuditTrail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DealResource extends JsonResource
{
    use IncludesAuditTrail;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'probability' => $this->probability,
            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'won_reason' => $this->won_reason,
            'lost_reason' => $this->lost_reason,
            'source' => $this->source,
            'description' => $this->description,
            'custom_fields' => $this->custom_fields ?? [],
            'pipeline_id' => $this->pipeline_id,
            'pipeline' => PipelineSummaryResource::make($this->whenLoaded('pipeline')),
            'pipeline_stage_id' => $this->pipeline_stage_id,
            'stage' => PipelineStageSummaryResource::make($this->whenLoaded('stage')),
            'company_id' => $this->company_id,
            'company' => CompanySummaryResource::make($this->whenLoaded('company')),
            'contact_id' => $this->contact_id,
            'primary_contact' => ContactSummaryResource::make($this->whenLoaded('primaryContact')),
            'contacts' => ContactSummaryResource::collection($this->whenLoaded('contacts')),
            'owner_id' => $this->owner_id,
            'owner' => UserSummaryResource::make($this->whenLoaded('owner')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'activities_count' => $this->whenCounted('activities'),
            'audit' => $this->auditTrail(),
        ];
    }
}
