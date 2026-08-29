<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTags;
use App\Observers\CrmCacheObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[ObservedBy(CrmCacheObserver::class)]
class Company extends Model
{
    use Auditable;
    use HasFactory;
    use HasTags;

    protected $fillable = [
        'name', 'domain', 'industry', 'size', 'phone', 'website',
        'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country',
        'annual_revenue', 'description', 'custom_fields', 'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
            'annual_revenue' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'related');
    }
}
