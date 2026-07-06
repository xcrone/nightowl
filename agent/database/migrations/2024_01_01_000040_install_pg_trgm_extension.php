<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'nightowl';

    /**
     * pg_trgm powers the trigram GIN indexes added by the migrations that
     * follow (identifier/stack-trace/URL columns where ILIKE '%...%'
     * substring search matters more than tsvector word-stemming). It's a
     * "trusted" extension (Postgres 13+), so the nightowl DB user — the
     * database owner, not necessarily superuser — can install it without
     * elevated privileges.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->getConnection()->statement(
            'CREATE EXTENSION IF NOT EXISTS pg_trgm'
        );
    }

    public function down(): void
    {
        // Safe to run last on rollback: every migration after this one drops
        // its own trigram indexes / generated column in its down(), and
        // artisan runs down()s in reverse (newest-first) order, so nothing
        // still depends on pg_trgm by the time this fires.
        Schema::connection($this->connection)->getConnection()->statement(
            'DROP EXTENSION IF EXISTS pg_trgm'
        );
    }
};
