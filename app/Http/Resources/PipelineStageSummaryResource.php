<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Slim shape for the stage badge shown on a deal row or card. */
class PipelineStageSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pipeline_id' => $this->pipeline_id,
            'name' => $this->name,
            'color' => $this->color,
            'type' => $this->type,
        ];
    }
}
