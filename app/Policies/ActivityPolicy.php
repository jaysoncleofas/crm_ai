<?php

namespace App\Policies;

class ActivityPolicy extends CrmPolicy
{
    protected function resource(): string
    {
        return 'activities';
    }
}
