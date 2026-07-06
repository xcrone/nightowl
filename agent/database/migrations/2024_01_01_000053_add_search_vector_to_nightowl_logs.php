<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'nightowl';

    /**
     * message/context/extra are freeform prose — tsvector's word-stemmed
     * matching (e.g. "fail" matching "failed") is the better fit here vs.
     * trigram substring matching. search_vector is a STORED generated
     * column so Postgres maintains it automatically on every insert;
     * Laravel's schema builder has no first-class support for generated
     * tsvector columns, so this is raw DDL via ->statement(), same as other
     * migrations already do for anything the builder can't express.
     *
     * Uses nightowl_immutable_to_tsvector (000041) instead of to_tsvector
     * directly — see that migration's docblock for why.
     *
     * coalesce(...,'') on every column: to_tsvector('english', NULL || X)
     * is NULL, which would silently drop the whole row from tsvector
     * search whenever context or extra is null.
     */
    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('nightowl_logs')) {
            return;
        }

        $connection = Schema::connection($this->connection)->getConnection();

        $connection->statement(<<<'SQL'
            ALTER TABLE nightowl_logs
            ADD COLUMN IF NOT EXISTS search_vector tsvector
            GENERATED ALWAYS AS (
                nightowl_immutable_to_tsvector('english',
                    coalesce(message, '') || ' ' || coalesce(context, '') || ' ' || coalesce(extra, '')
                )
            ) STORED
        SQL);

        $connection->statement(
            'CREATE INDEX IF NOT EXISTS nightowl_logs_search_vector_index ON nightowl_logs USING GIN (search_vector)'
        );
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable('nightowl_logs')) {
            return;
        }

        $connection = Schema::connection($this->connection)->getConnection();

        $connection->statement('DROP INDEX IF EXISTS nightowl_logs_search_vector_index');
        $connection->statement('ALTER TABLE nightowl_logs DROP COLUMN IF EXISTS search_vector');
    }
};
