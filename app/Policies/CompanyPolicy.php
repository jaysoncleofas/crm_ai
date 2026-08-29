<?php

namespace App\Policies;

class CompanyPolicy extends CrmPolicy
{
    protected function resource(): string
    {
        return 'companies';
    }
}
