<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends CrmPolicy
{
    protected function resource(): string
    {
        return 'users';
    }

    protected function ownerColumn(): ?string
    {
        return null;
    }

    /** Anyone may edit their own profile; editing others needs the permission. */
    public function update(User $user, Model $record): bool
    {
        return $user->is($record) || $user->can($this->permission('update'));
    }

    /**
     * Deleting yourself would lock you out mid-session, so it is refused for
     * everyone — including admins, who would otherwise pass via before().
     */
    public function before(User $user, string $ability, mixed $record = null): ?bool
    {
        if (in_array($ability, ['delete', 'forceDelete'], true)
            && $record instanceof User && $user->is($record)) {
            return false;
        }

        return parent::before($user, $ability, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->can($this->permission('delete'));
    }
}
