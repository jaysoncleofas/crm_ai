<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\CausesActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use Auditable;
    use CausesActivity;
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'job_title', 'phone', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /** Never let a credential reach the audit trail. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(['password', 'remember_token'])
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at', 'updated_by', 'last_login_at'])
            ->dontLogEmptyChanges()
            ->useLogName('users');
    }

    public function ownedContacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'owner_id');
    }

    public function ownedCompanies(): HasMany
    {
        return $this->hasMany(Company::class, 'owner_id');
    }

    public function ownedDeals(): HasMany
    {
        return $this->hasMany(Deal::class, 'owner_id');
    }

    public function ownedActivities(): HasMany
    {
        return $this->hasMany(Activity::class, 'owner_id');
    }
}
