<?php

namespace App\Http\Resources\Concerns;

use App\Http\Resources\UserSummaryResource;

trait IncludesAuditTrail
{
    /**
     * The audit block every CRUD resource exposes, so the UI can always show
     * who touched a record and when.
     */
    protected function auditTrail(): array
    {
        return [
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'deleted_by' => $this->deleted_by,
            'creator' => UserSummaryResource::make($this->whenLoaded('creator')),
            'updater' => UserSummaryResource::make($this->whenLoaded('updater')),
            'deleter' => UserSummaryResource::make($this->whenLoaded('deleter')),
            'is_deleted' => $this->deleted_at !== null,
        ];
    }
}
