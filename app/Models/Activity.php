<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Observers\CrmCacheObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A CRM touchpoint (call, email, meeting, note, task).
 *
 * Not to be confused with Spatie\Activitylog\Models\Activity, which is the
 * audit trail — see the `activity_log` table.
 */
#[ObservedBy(CrmCacheObserver::class)]
class Activity extends Model
{
    use Auditable;
    use HasFactory;

    public const TYPES = ['call', 'email', 'meeting', 'note', 'task'];

    public const STATUS_PLANNED = 'planned';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'type', 'subject', 'body', 'status', 'direction', 'outcome', 'location',
        'duration_minutes', 'due_at', 'completed_at', 'owner_id',
        'related_type', 'related_id',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PLANNED)->whereNotNull('due_at')->where('due_at', '<', now());
    }
}
