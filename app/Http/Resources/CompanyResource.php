<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\IncludesAuditTrail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    use IncludesAuditTrail;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'domain' => $this->domain,
            'industry' => $this->industry,
            'size' => $this->size,
            'phone' => $this->phone,
            'website' => $this->website,
            'address' => [
                'line1' => $this->address_line1,
                'line2' => $this->address_line2,
                'city' => $this->city,
                'state' => $this->state,
                'postal_code' => $this->postal_code,
                'country' => $this->country,
            ],
            'annual_revenue' => $this->annual_revenue,
            'description' => $this->description,
            'custom_fields' => $this->custom_fields ?? [],
            'owner_id' => $this->owner_id,
            'owner' => UserSummaryResource::make($this->whenLoaded('owner')),
            'contacts_count' => $this->whenCounted('contacts'),
            'deals_count' => $this->whenCounted('deals'),
            'contacts' => ContactSummaryResource::collection($this->whenLoaded('contacts')),
            'deals' => DealSummaryResource::collection($this->whenLoaded('deals')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'audit' => $this->auditTrail(),
        ];
    }
}
