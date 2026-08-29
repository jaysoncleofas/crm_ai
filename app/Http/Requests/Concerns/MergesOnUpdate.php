<?php

namespace App\Http\Requests\Concerns;

trait MergesOnUpdate
{
    /** True for PATCH/PUT, where every field is optional. */
    protected function isPartial(): bool
    {
        return $this->isMethod('PATCH') || $this->isMethod('PUT');
    }

    /**
     * `required` on create, `sometimes` on update — one rule set, no drift
     * between the two verbs.
     */
    protected function required(): string
    {
        return $this->isPartial() ? 'sometimes' : 'required';
    }
}
