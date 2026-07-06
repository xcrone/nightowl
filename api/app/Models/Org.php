<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Org extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // uuid-public-ids: every new org gets a public uuid identifier
        // going forward; existing rows are backfilled by the retrofit
        // migration (2026_07_06_120000_add_uuid_to_orgs_table).
        static::creating(function (Org $org) {
            $org->uuid ??= (string) Str::uuid();
        });
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
