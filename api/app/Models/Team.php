<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Team extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // uuid-public-ids: every new team gets a public uuid identifier
        // going forward; existing rows are backfilled by the retrofit
        // migration (2026_07_06_120001_add_uuid_to_teams_table).
        static::creating(function (Team $team) {
            $team->uuid ??= (string) Str::uuid();
        });
    }

    /** Bind route {team} by the public uuid, never the numeric id. */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function org(): BelongsTo
    {
        return $this->belongsTo(Org::class);
    }

    public function apps(): HasMany
    {
        return $this->hasMany(App::class);
    }
}
