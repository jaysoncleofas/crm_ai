<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\IncludesAuditTrail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PipelineStageResource extends JsonResource
{
    use IncludesAuditTrail;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pipeline_id' => $this->pipeline_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'position' => $this->position,
            'probability' => $this->probability,
            'type' => $this->type,
            'color' => $this->color,
            'deals_count' => $this->whenCounted('deals'),
            'audit' => $this->auditTrail(),
        ];
    }
}
