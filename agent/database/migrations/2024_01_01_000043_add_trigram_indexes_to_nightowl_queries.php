<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'nightowl';

    /**
     * sql_query/file/connection are SQL fragments and file paths —
     * substring matching is essential here (searching part of a
     * table/column name should still match).
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
        if (! Schema::connection($this->connection)->hasTable('nightowl_queries')) {
            return;
        }

        $connection = Schema::connection($this->connection)->getConnection();

        $connection->statement('CREATE INDEX IF NOT EXISTS nightowl_queries_sql_query_trgm_index ON nightowl_queries USING GIN (sql_query gin_trgm_ops)');
        $connection->statement('CREATE INDEX IF NOT EXISTS nightowl_queries_file_trgm_index ON nightowl_queries USING GIN (file gin_trgm_ops)');
        $connection->statement('CREATE INDEX IF NOT EXISTS nightowl_queries_connection_trgm_index ON nightowl_queries USING GIN (connection gin_trgm_ops)');
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable('nightowl_queries')) {
            return;
        }

        $connection = Schema::connection($this->connection)->getConnection();

        $connection->statement('DROP INDEX IF EXISTS nightowl_queries_sql_query_trgm_index');
        $connection->statement('DROP INDEX IF EXISTS nightowl_queries_file_trgm_index');
        $connection->statement('DROP INDEX IF EXISTS nightowl_queries_connection_trgm_index');
    }
};
