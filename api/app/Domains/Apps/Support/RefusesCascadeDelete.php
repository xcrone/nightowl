<?php

namespace App\Domains\Apps\Support;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;

/**
 * Shared by DestroyOrg/DestroyTeam: both refuse to delete a parent row that
 * still has children (org -> teams, team -> apps) rather than relying on
 * the DB's `cascadeOnDelete()` to silently wipe every descendant from one
 * call. Callers are expected to have already re-fetched the parent with
 * `lockForUpdate()` inside a `DB::transaction()` before calling this, so
 * the exists() check below and the eventual delete() are atomic against a
 * concurrent insert of a new child row (see the Notes entry in this
 * domain's README.md for why the lock matters).
 */
trait RefusesCascadeDelete
{
    private function refuseIfHasChildren(Relation $children, string $message): ?JsonResponse
    {
        if ($children->exists()) {
            return response()->json(['message' => $message], 422);
        }

        return null;
    }
}
