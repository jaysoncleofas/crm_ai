<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Observers\CrmCacheObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(CrmCacheObserver::class)]
class PipelineStage extends Model
{
    use Auditable;
    use HasFactory;

    public const TYPE_OPEN = 'open';

    public const TYPE_WON = 'won';

    public const TYPE_LOST = 'lost';

    protected $fillable = ['pipeline_id', 'name', 'slug', 'position', 'probability', 'type', 'color'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'probability' => 'integer',
        ];
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function isClosed(): bool
    {
        return in_array($this->type, [self::TYPE_WON, self::TYPE_LOST], true);
    }
}
