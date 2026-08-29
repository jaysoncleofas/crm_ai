<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\IncludesAuditTrail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    use IncludesAuditTrail;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'subject' => $this->subject,
            'body' => $this->body,
            'status' => $this->status,
            'direction' => $this->direction,
            'outcome' => $this->outcome,
            'location' => $this->location,
            'duration_minutes' => $this->duration_minutes,
            'due_at' => $this->due_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'is_overdue' => $this->status === 'planned'
                && $this->due_at !== null
                && $this->due_at->isPast(),
            'owner_id' => $this->owner_id,
            'owner' => UserSummaryResource::make($this->whenLoaded('owner')),
            'related_type' => $this->related_type,
            'related_id' => $this->related_id,
            'related' => $this->whenLoaded('related', fn () => [
                'type' => $this->related_type,
                'id' => $this->related_id,
                'label' => $this->relatedLabel(),
            ]),
            'audit' => $this->auditTrail(),
        ];
    }

    protected function relatedLabel(): ?string
    {
        return match ($this->related_type) {
            'contact' => $this->related?->full_name,
            'company', 'deal' => $this->related?->name,
            default => null,
        };
    }
}
