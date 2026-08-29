<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One row of the Spatie activity_log audit trail. */
class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Cast to a Collection by the package; normalise to plain arrays for JSON.
        $changes = collect($this->attribute_changes ?? []);

        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'description' => $this->description,
            'event' => $this->event,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'causer_id' => $this->causer_id,
            'causer' => UserSummaryResource::make($this->whenLoaded('causer')),
            'changes' => [
                'attributes' => $changes->get('attributes', []),
                'old' => $changes->get('old', []),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
