<?php

namespace App\Policies;

class ContactPolicy extends CrmPolicy
{
    protected function resource(): string
    {
        return 'contacts';
    }
}
