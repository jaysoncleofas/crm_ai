<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\IncludesAuditTrail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    use IncludesAuditTrail;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'job_title' => $this->job_title,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()),
            // Only the caller's own record carries the permission list, and only
            // when it was eager loaded — a list endpoint must not lazy load it.
            'permissions' => $this->when(
                ($request->user()?->is($this->resource) ?? false) && $this->relationLoaded('permissions'),
                fn () => $this->getAllPermissions()->pluck('name')->values()
            ),
            'audit' => $this->auditTrail(),
        ];
    }
}
