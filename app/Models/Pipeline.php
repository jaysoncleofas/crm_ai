<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Observers\CrmCacheObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(CrmCacheObserver::class)]
class Pipeline extends Model
{
    use Auditable;
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'is_default', 'position'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function stages(): HasMany
    {
        return $this->hasMany(PipelineStage::class)->orderBy('position');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }
}
