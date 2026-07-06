<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'nightowl';

    /**
     * key/store are cache key and store identifiers, where substring
     * matching matters more than tsvector's whole-word matching.
     * A pg_trgm GIN index goes directly on the existing column — no new
     * column needed — and accelerates Postgres's native ILIKE '%...%'
     * automatically.
     *
     * Blocking CREATE INDEX (no CONCURRENTLY), matching the established
     * convention for these tables (000029, 000039): run during a deploy
     * window like the rest of nightowl:migrate.
     */
    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('nightowl_cache_events')) {
            return;
        }

        $connection = Schema::connection($this->connection)->getConnection();

        $connection->statement('CREATE INDEX IF NOT EXISTS nightowl_cache_events_key_trgm_index ON nightowl_cache_events USING GIN (key gin_trgm_ops)');
        $connection->statement('CREATE INDEX IF NOT EXISTS nightowl_cache_events_store_trgm_index ON nightowl_cache_events USING GIN (store gin_trgm_ops)');
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable('nightowl_cache_events')) {
            return;
        }

        $connection = Schema::connection($this->connection)->getConnection();

        $connection->statement('DROP INDEX IF EXISTS nightowl_cache_events_key_trgm_index');
        $connection->statement('DROP INDEX IF EXISTS nightowl_cache_events_store_trgm_index');
    }
};
