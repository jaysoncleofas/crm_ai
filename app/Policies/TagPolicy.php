<?php

namespace App\Policies;

class TagPolicy extends CrmPolicy
{
    protected function resource(): string
    {
        return 'tags';
    }

    /** Reference data is org-wide, not owned by any one user. */
    protected function ownerColumn(): ?string
    {
        return null;
    }
}
