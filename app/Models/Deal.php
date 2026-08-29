<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTags;
use App\Observers\CrmCacheObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[ObservedBy(CrmCacheObserver::class)]
class Deal extends Model
{
    use Auditable;
    use HasFactory;
    use HasTags;

    public const STATUS_OPEN = 'open';

    public const STATUS_WON = 'won';

    public const STATUS_LOST = 'lost';

    protected $fillable = [
        'name', 'pipeline_id', 'pipeline_stage_id', 'company_id', 'contact_id', 'owner_id',
        'amount', 'currency', 'status', 'probability', 'expected_close_date', 'closed_at',
        'won_reason', 'lost_reason', 'source', 'description', 'custom_fields',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'probability' => 'integer',
            'expected_close_date' => 'date',
            'closed_at' => 'datetime',
            'custom_fields' => 'array',
        ];
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function primaryContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class)->withPivot('role')->withTimestamps();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'related');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeWon(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_WON);
    }

    public function isClosed(): bool
    {
        return $this->status !== self::STATUS_OPEN;
    }
}
