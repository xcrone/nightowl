<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OrgInvitation extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // uuid-public-ids: every new org invitation gets a public uuid
        // identifier, generated on create — never the numeric id.
        static::creating(function (OrgInvitation $invitation) {
            $invitation->uuid ??= (string) Str::uuid();
        });
    }

    /** Bind route {org_invitation} by the public uuid, never the numeric id. */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function org(): BelongsTo
    {
        return $this->belongsTo(Org::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
        ];
    }
}
