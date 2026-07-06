<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'nightowl';

    /**
     * notification/channel/notifiable_type are class/channel identifiers,
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
        if (! Schema::connection($this->connection)->hasTable('nightowl_notifications')) {
            return;
        }

        $connection = Schema::connection($this->connection)->getConnection();

        $connection->statement('CREATE INDEX IF NOT EXISTS nightowl_notifications_notification_trgm_index ON nightowl_notifications USING GIN (notification gin_trgm_ops)');
        $connection->statement('CREATE INDEX IF NOT EXISTS nightowl_notifications_channel_trgm_index ON nightowl_notifications USING GIN (channel gin_trgm_ops)');
        $connection->statement('CREATE INDEX IF NOT EXISTS nightowl_notifications_notifiable_type_trgm_index ON nightowl_notifications USING GIN (notifiable_type gin_trgm_ops)');
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable('nightowl_notifications')) {
            return;
        }

        $connection = Schema::connection($this->connection)->getConnection();

        $connection->statement('DROP INDEX IF EXISTS nightowl_notifications_notification_trgm_index');
        $connection->statement('DROP INDEX IF EXISTS nightowl_notifications_channel_trgm_index');
        $connection->statement('DROP INDEX IF EXISTS nightowl_notifications_notifiable_type_trgm_index');
    }
};
