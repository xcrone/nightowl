<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'nightowl';

    /**
     * command/expression are shell-command and cron-expression text,
     * where substring matching matters more than tsvector's whole-word
     * matching.
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
        if (! Schema::connection($this->connection)->hasTable('nightowl_scheduled_tasks')) {
            return;
        }

        $connection = Schema::connection($this->connection)->getConnection();

        $connection->statement('CREATE INDEX IF NOT EXISTS nightowl_scheduled_tasks_command_trgm_index ON nightowl_scheduled_tasks USING GIN (command gin_trgm_ops)');
        $connection->statement('CREATE INDEX IF NOT EXISTS nightowl_scheduled_tasks_expression_trgm_index ON nightowl_scheduled_tasks USING GIN (expression gin_trgm_ops)');
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable('nightowl_scheduled_tasks')) {
            return;
        }

        $connection = Schema::connection($this->connection)->getConnection();

        $connection->statement('DROP INDEX IF EXISTS nightowl_scheduled_tasks_command_trgm_index');
        $connection->statement('DROP INDEX IF EXISTS nightowl_scheduled_tasks_expression_trgm_index');
    }
};
