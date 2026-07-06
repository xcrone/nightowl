<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'nightowl';

    /**
     * class/trace/file are identifier- and stack-trace-like text, where
     * substring matching matters more than tsvector's word-stemmed
     * matching (e.g. searching "NullPointer" should match mid-string in
     * "TypeError: NullPointerException").
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
        if (! Schema::connection($this->connection)->hasTable('nightowl_exceptions')) {
            return;
        }

        $connection = Schema::connection($this->connection)->getConnection();

        $connection->statement('CREATE INDEX IF NOT EXISTS nightowl_exceptions_class_trgm_index ON nightowl_exceptions USING GIN (class gin_trgm_ops)');
        $connection->statement('CREATE INDEX IF NOT EXISTS nightowl_exceptions_trace_trgm_index ON nightowl_exceptions USING GIN (trace gin_trgm_ops)');
        $connection->statement('CREATE INDEX IF NOT EXISTS nightowl_exceptions_file_trgm_index ON nightowl_exceptions USING GIN (file gin_trgm_ops)');
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable('nightowl_exceptions')) {
            return;
        }

        $connection = Schema::connection($this->connection)->getConnection();

        $connection->statement('DROP INDEX IF EXISTS nightowl_exceptions_class_trgm_index');
        $connection->statement('DROP INDEX IF EXISTS nightowl_exceptions_trace_trgm_index');
        $connection->statement('DROP INDEX IF EXISTS nightowl_exceptions_file_trgm_index');
    }
};
