<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The audit baseline every CRUD model in the CRM gets:
 *   - soft deletes (deleted_at)
 *   - blame stamps (created_by / updated_by / deleted_by)
 *   - an activity_log entry for every create, update, delete and restore
 */
trait Auditable
{
    use Blameable;
    use LogsActivity;
    use SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at', 'updated_by'])
            ->dontLogEmptyChanges()
            ->useLogName($this->getTable());
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return sprintf('%s was %s', class_basename($this), $eventName);
    }
}
