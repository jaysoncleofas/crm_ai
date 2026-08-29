<?php

namespace App\Observers;

use App\Support\CrmCache;
use Illuminate\Database\Eloquent\Model;

/**
 * Any write to a CRM record invalidates the cached aggregates that read it.
 * Attached with #[ObservedBy] on each cached-into model.
 */
class CrmCacheObserver
{
    public function saved(Model $model): void
    {
        CrmCache::flushAll();
    }

    public function deleted(Model $model): void
    {
        CrmCache::flushAll();
    }

    public function restored(Model $model): void
    {
        CrmCache::flushAll();
    }

    public function forceDeleted(Model $model): void
    {
        CrmCache::flushAll();
    }
}
