<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'nightowl';

    /**
     * subject/mailable/recipients are mail metadata, where substring
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
        if (! Schema::connection($this->connection)->hasTable('nightowl_mail')) {
            return;
        }

        $connection = Schema::connection($this->connection)->getConnection();

        $connection->statement('CREATE INDEX IF NOT EXISTS nightowl_mail_subject_trgm_index ON nightowl_mail USING GIN (subject gin_trgm_ops)');
        $connection->statement('CREATE INDEX IF NOT EXISTS nightowl_mail_mailable_trgm_index ON nightowl_mail USING GIN (mailable gin_trgm_ops)');
        $connection->statement('CREATE INDEX IF NOT EXISTS nightowl_mail_recipients_trgm_index ON nightowl_mail USING GIN (recipients gin_trgm_ops)');
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable('nightowl_mail')) {
            return;
        }

        $connection = Schema::connection($this->connection)->getConnection();

        $connection->statement('DROP INDEX IF EXISTS nightowl_mail_subject_trgm_index');
        $connection->statement('DROP INDEX IF EXISTS nightowl_mail_mailable_trgm_index');
        $connection->statement('DROP INDEX IF EXISTS nightowl_mail_recipients_trgm_index');
    }
};
