<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable: no backfill guarantee at the DB level yet, so this
            // can't be NOT NULL in the same migration (see api/CLAUDE.md's
            // uuid-public-ids retrofit notes). Backfilled below for any
            // existing rows so nothing ships with a null uuid in practice.
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        DB::table('users')->whereNull('uuid')->get(['id'])->each(function ($user) {
            DB::table('users')->where('id', $user->id)->update(['uuid' => (string) Str::uuid()]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
