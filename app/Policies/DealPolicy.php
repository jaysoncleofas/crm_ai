<?php

namespace App\Policies;

class DealPolicy extends CrmPolicy
{
    protected function resource(): string
    {
        return 'deals';
    }
}
