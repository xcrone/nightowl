<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orgs', function (Blueprint $table) {
            // Nullable: not every pre-existing org necessarily has a member
            // to backfill from (an org with zero attached members has no
            // candidate owner) — see the backfill loop below, which leaves
            // owner_id null in that case rather than forcing a fake value.
            $table->foreignId('owner_id')->nullable()->after('uuid')->constrained('users')->nullOnDelete();
            $table->boolean('is_personal')->default(false)->after('owner_id');
        });

        // Backfill: every pre-existing org's owner becomes its first
        // attached member (by user_id), so ownership-gated Actions
        // (TransferOrgOwnership) have something to check against for orgs
        // created before this column existed. is_personal is left at its
        // default false for all of them — retroactively deciding which
        // pre-existing orgs "are" someone's personal org isn't something
        // this migration can infer safely.
        DB::table('orgs')->orderBy('id')->get(['id'])->each(function ($org) {
            $ownerId = DB::table('org_user')->where('org_id', $org->id)->orderBy('user_id')->value('user_id');

            if ($ownerId !== null) {
                DB::table('orgs')->where('id', $org->id)->update(['owner_id' => $ownerId]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orgs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
            $table->dropColumn('is_personal');
        });
    }
};
