<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\IncludesAuditTrail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TagResource extends JsonResource
{
    use IncludesAuditTrail;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'color' => $this->color,
            'audit' => $this->auditTrail(),
        ];
    }
}
