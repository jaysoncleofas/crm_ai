<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stamps created_by / updated_by / deleted_by on every write.
 *
 * Pair with Illuminate\Database\Eloquent\SoftDeletes — `deleted_by` is only
 * meaningful for a soft delete, so it is skipped when force deleting.
 */
trait Blameable
{
    public static function bootBlameable(): void
    {
        static::creating(function (self $model): void {
            $userId = static::blameableUserId();

            if ($userId !== null) {
                $model->created_by ??= $userId;
                $model->updated_by ??= $userId;
            }
        });

        static::updating(function (self $model): void {
            $userId = static::blameableUserId();

            // Restoring clears the delete stamp rather than recording an update.
            if ($userId !== null && ! $model->isDirty('deleted_at')) {
                $model->updated_by = $userId;
            }
        });

        static::deleting(function (self $model): void {
            $softDeleting = method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting();

            if ($softDeleting && ($userId = static::blameableUserId()) !== null) {
                $model->deleted_by = $userId;
                $model->saveQuietly();
            }
        });

        static::restoring(function (self $model): void {
            $model->deleted_by = null;
            $model->updated_by = static::blameableUserId() ?? $model->updated_by;
        });
    }

    protected static function blameableUserId(): ?int
    {
        return auth()->id();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
