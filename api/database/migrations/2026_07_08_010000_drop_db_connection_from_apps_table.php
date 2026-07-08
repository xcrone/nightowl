<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops `apps.db_connection` — a leftover from the original NightOwl product
 * where each customer app had its own physical Postgres database. In this
 * rebuild, telemetry is multi-tenant via a single shared Postgres scoped by
 * `app_id` (see root CLAUDE.md), so the field was an unused free-text label.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apps', function (Blueprint $t) {
            $t->dropColumn('db_connection');
        });
    }

    public function down(): void
    {
        Schema::table('apps', function (Blueprint $t) {
            $t->string('db_connection')->nullable();
        });
    }
};
