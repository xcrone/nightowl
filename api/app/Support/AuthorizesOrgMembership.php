<?php

namespace App\Support;

use App\Models\Org;
use App\Models\User;

/**
 * Shared by Actions (via `authorize()`) whose route resolves an Org, or a
 * Team/App reached through one, restricting access to that org's attached
 * members. Mirrors AuthorizesAppScope's role for the app_id-ownership
 * check — an indexed existence check rather than loading every member row
 * just to call `contains()` on the resulting collection.
 */
trait AuthorizesOrgMembership
{
    private function authorizeOrgMember(Org $org, ?User $user): bool
    {
        return $user !== null && $org->users()->whereKey($user->id)->exists();
    }
}
