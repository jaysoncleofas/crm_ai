<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Slim shape for a company referenced from another record.
 *
 * Nested references only ever need enough to render a link, so list endpoints
 * can select `company:id,name` instead of the whole row.
 */
class CompanySummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
