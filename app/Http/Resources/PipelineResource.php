<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\IncludesAuditTrail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PipelineResource extends JsonResource
{
    use IncludesAuditTrail;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_default' => $this->is_default,
            'position' => $this->position,
            'stages' => PipelineStageResource::collection($this->whenLoaded('stages')),
            'deals_count' => $this->whenCounted('deals'),
            'audit' => $this->auditTrail(),
        ];
    }
}
