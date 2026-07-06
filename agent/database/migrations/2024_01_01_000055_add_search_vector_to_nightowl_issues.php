<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'nightowl';

    /**
     * exception_message/description are freeform prose — tsvector's
     * word-stemmed matching is the better fit vs. trigram substring
     * matching (already covered for exception_class by 000052's trigram
     * index). See 000053_add_search_vector_to_nightowl_logs for the
     * generated-column / IMMUTABLE-wrapper rationale shared by all
     * search_vector columns.
     */
    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('nightowl_issues')) {
            return;
        }

        $connection = Schema::connection($this->connection)->getConnection();

        $connection->statement(<<<'SQL'
            ALTER TABLE nightowl_issues
            ADD COLUMN IF NOT EXISTS search_vector tsvector
            GENERATED ALWAYS AS (
                nightowl_immutable_to_tsvector('english',
                    coalesce(exception_message, '') || ' ' || coalesce(description, '')
                )
            ) STORED
        SQL);

        $connection->statement(
            'CREATE INDEX IF NOT EXISTS nightowl_issues_search_vector_index ON nightowl_issues USING GIN (search_vector)'
        );
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable('nightowl_issues')) {
            return;
        }

        $connection = Schema::connection($this->connection)->getConnection();

        $connection->statement('DROP INDEX IF EXISTS nightowl_issues_search_vector_index');
        $connection->statement('ALTER TABLE nightowl_issues DROP COLUMN IF EXISTS search_vector');
    }
};
