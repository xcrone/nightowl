<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'nightowl';

    /**
     * Postgres's built-in to_tsvector(regconfig, text) is STABLE, not
     * IMMUTABLE, so it can't be used directly inside a
     * `GENERATED ALWAYS AS (...) STORED` expression (Postgres rejects the
     * DDL with "generation expression is not immutable"). This wraps it in
     * our own SQL function explicitly marked IMMUTABLE — the standard
     * workaround, accepting that already-generated values won't reflect a
     * (never-planned) future change to the 'english' configuration's
     * dictionaries. Shared by every STORED tsvector column added below
     * (nightowl_logs, nightowl_exceptions, nightowl_issues) rather than one
     * wrapper per table.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->getConnection()->statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION nightowl_immutable_to_tsvector(config regconfig, content text)
            RETURNS tsvector
            LANGUAGE sql
            IMMUTABLE
            PARALLEL SAFE
            AS $$
                SELECT to_tsvector(config, content)
            $$
        SQL);
    }

    public function down(): void
    {
        Schema::connection($this->connection)->getConnection()->statement(
            'DROP FUNCTION IF EXISTS nightowl_immutable_to_tsvector(regconfig, text)'
        );
    }
};
