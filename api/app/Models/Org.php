<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /** Bind route {org} by the public uuid, never the numeric id. */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /** Pending/accepted/declined invitations issued for this org. */
    public function invitations(): HasMany
    {
        return $this->hasMany(OrgInvitation::class);
    }

    /**
     * The org's owner — never reassigned for a personal (`is_personal`)
     * org, transferable otherwise via TransferOrgOwnership. Nullable:
     * pre-existing orgs backfilled with no attached member at all have no
     * candidate owner (see 2026_07_07_120000_add_owner_to_orgs_table).
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
