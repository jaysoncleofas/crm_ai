<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\IncludesAuditTrail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    use IncludesAuditTrail;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'job_title' => $this->job_title,
            'lifecycle_stage' => $this->lifecycle_stage,
            'lead_status' => $this->lead_status,
            'lead_score' => $this->lead_score,
            'source' => $this->source,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'notes' => $this->notes,
            'custom_fields' => $this->custom_fields ?? [],
            'last_contacted_at' => $this->last_contacted_at?->toIso8601String(),
            'company_id' => $this->company_id,
            'company' => CompanySummaryResource::make($this->whenLoaded('company')),
            'owner_id' => $this->owner_id,
            'owner' => UserSummaryResource::make($this->whenLoaded('owner')),
            'deals' => DealSummaryResource::collection($this->whenLoaded('deals')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'activities_count' => $this->whenCounted('activities'),
            'audit' => $this->auditTrail(),
        ];
    }
}
