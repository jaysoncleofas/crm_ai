<?php

namespace App\Policies;

class PipelinePolicy extends CrmPolicy
{
    protected function resource(): string
    {
        return 'pipelines';
    }

    /** Reference data is org-wide, not owned by any one user. */
    protected function ownerColumn(): ?string
    {
        return null;
    }
}
