<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared shape for every CRM resource policy.
 *
 * Two axes decide access:
 *   1. the caller holds `<resource>.<action>` (Spatie permission), and
 *   2. the record is in scope — either the caller owns it, or they hold the
 *      cross-cutting `records.manage-any` permission (managers and admins).
 */
abstract class CrmPolicy
{
    /** Permission prefix, e.g. "contacts". */
    abstract protected function resource(): string;

    /** Column holding the record owner, or null when the resource has no owner. */
    protected function ownerColumn(): ?string
    {
        return 'owner_id';
    }

    public function before(User $user, string $ability, mixed $record = null): ?bool
    {
        // A deactivated account keeps its roles but loses every ability.
        if (! $user->is_active) {
            return false;
        }

        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can($this->permission('view'));
    }

    public function view(User $user, Model $record): bool
    {
        // Reading is org-wide: reps need to see the whole book of business.
        return $user->can($this->permission('view'));
    }

    public function create(User $user): bool
    {
        return $user->can($this->permission('create'));
    }

    public function update(User $user, Model $record): bool
    {
        return $user->can($this->permission('update')) && $this->inScope($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->can($this->permission('delete')) && $this->inScope($user, $record);
    }

    public function restore(User $user, Model $record): bool
    {
        return $user->can($this->permission('restore')) && $this->inScope($user, $record);
    }

    /** Hard deletes are reserved for admins, handled by before(). */
    public function forceDelete(User $user, Model $record): bool
    {
        return false;
    }

    protected function permission(string $action): string
    {
        return "{$this->resource()}.{$action}";
    }

    /**
     * Writes are narrowed to owned records unless the caller can manage any,
     * which matches how sales teams work: see everything, edit your own.
     */
    protected function inScope(User $user, Model $record): bool
    {
        $column = $this->ownerColumn();

        if ($column === null || $user->can('records.manage-any')) {
            return true;
        }

        return $record->getAttribute($column) === $user->getKey();
    }
}
