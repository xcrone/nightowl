<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'nightowl';

    /**
     * message is freeform prose — tsvector's word-stemmed matching is the
     * better fit vs. trigram substring matching (already covered for
     * class/trace/file by 000042's trigram indexes). See
     * 000053_add_search_vector_to_nightowl_logs for the generated-column /
     * IMMUTABLE-wrapper rationale shared by all search_vector columns.
     */
    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('nightowl_exceptions')) {
            return;
        }

        $connection = Schema::connection($this->connection)->getConnection();

        $connection->statement(<<<'SQL'
            ALTER TABLE nightowl_exceptions
            ADD COLUMN IF NOT EXISTS search_vector tsvector
            GENERATED ALWAYS AS (
                nightowl_immutable_to_tsvector('english', coalesce(message, ''))
            ) STORED
        SQL);

        $connection->statement(
            'CREATE INDEX IF NOT EXISTS nightowl_exceptions_search_vector_index ON nightowl_exceptions USING GIN (search_vector)'
        );
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable('nightowl_exceptions')) {
            return;
        }

        $connection = Schema::connection($this->connection)->getConnection();

        $connection->statement('DROP INDEX IF EXISTS nightowl_exceptions_search_vector_index');
        $connection->statement('ALTER TABLE nightowl_exceptions DROP COLUMN IF EXISTS search_vector');
    }
};
