<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'nightowl';

    /**
     * url/route_name/route_path/route_action are URL and route
     * identifiers, where a user recalling a path fragment needs substring
     * matching rather than tsvector's whole-word matching.
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
        if (! Schema::connection($this->connection)->hasTable('nightowl_requests')) {
            return;
        }

        $connection = Schema::connection($this->connection)->getConnection();

        $connection->statement('CREATE INDEX IF NOT EXISTS nightowl_requests_url_trgm_index ON nightowl_requests USING GIN (url gin_trgm_ops)');
        $connection->statement('CREATE INDEX IF NOT EXISTS nightowl_requests_route_name_trgm_index ON nightowl_requests USING GIN (route_name gin_trgm_ops)');
        $connection->statement('CREATE INDEX IF NOT EXISTS nightowl_requests_route_path_trgm_index ON nightowl_requests USING GIN (route_path gin_trgm_ops)');
        $connection->statement('CREATE INDEX IF NOT EXISTS nightowl_requests_route_action_trgm_index ON nightowl_requests USING GIN (route_action gin_trgm_ops)');
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable('nightowl_requests')) {
            return;
        }

        $connection = Schema::connection($this->connection)->getConnection();

        $connection->statement('DROP INDEX IF EXISTS nightowl_requests_url_trgm_index');
        $connection->statement('DROP INDEX IF EXISTS nightowl_requests_route_name_trgm_index');
        $connection->statement('DROP INDEX IF EXISTS nightowl_requests_route_path_trgm_index');
        $connection->statement('DROP INDEX IF EXISTS nightowl_requests_route_action_trgm_index');
    }
};
