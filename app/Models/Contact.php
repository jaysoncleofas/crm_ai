<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTags;
use App\Observers\CrmCacheObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[ObservedBy(CrmCacheObserver::class)]
class Contact extends Model
{
    use Auditable;
    use HasFactory;
    use HasTags;

    public const LIFECYCLE_STAGES = [
        'subscriber', 'lead', 'marketing_qualified_lead', 'sales_qualified_lead',
        'opportunity', 'customer', 'evangelist', 'other',
    ];

    public const LEAD_STATUSES = [
        'new', 'open', 'in_progress', 'open_deal', 'unqualified',
        'attempted_to_contact', 'connected', 'bad_timing',
    ];

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone', 'mobile', 'job_title',
        'company_id', 'owner_id', 'lifecycle_stage', 'lead_status', 'lead_score',
        'source', 'city', 'state', 'country', 'notes', 'custom_fields', 'last_contacted_at',
    ];

    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
            'lead_score' => 'integer',
            'last_contacted_at' => 'datetime',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim("{$this->first_name} {$this->last_name}"));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function deals(): BelongsToMany
    {
        return $this->belongsToMany(Deal::class)->withPivot('role')->withTimestamps();
    }

    public function primaryDeals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'related');
    }
}
