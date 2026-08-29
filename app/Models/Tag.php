<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Observers\CrmCacheObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

#[ObservedBy(CrmCacheObserver::class)]
class Tag extends Model
{
    use Auditable;
    use HasFactory;

    protected $fillable = ['name', 'slug', 'color'];

    public function contacts(): MorphToMany
    {
        return $this->morphedByMany(Contact::class, 'taggable')->withTimestamps();
    }

    public function companies(): MorphToMany
    {
        return $this->morphedByMany(Company::class, 'taggable')->withTimestamps();
    }

    public function deals(): MorphToMany
    {
        return $this->morphedByMany(Deal::class, 'taggable')->withTimestamps();
    }
}
