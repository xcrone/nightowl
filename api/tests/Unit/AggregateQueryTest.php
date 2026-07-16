<?php

namespace Tests\Unit;

use App\Support\AggregateQuery;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * App\Support\AggregateQuery::normalizeRow()'s handling of the MAX(<last>) alias.
 *
 * These rows come off the raw query builder, so the value is a naive
 * `Y-m-d H:i:s` string out of a `timestamp without time zone` column. Postgres
 * runs UTC, so the stored wall-clock IS UTC — but nothing in the string says so,
 * and the SPA's `new Date(value)` would read it as local time.
 */
class AggregateQueryTest extends TestCase
{
    private const ISO_WITH_ZONE = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\+\d{2}:\d{2}|-\d{2}:\d{2}|Z)$/';

    /** The alias is derived from config ('last_alias', else "last_<col>") — never a hardcoded list. */
    public function test_it_normalizes_the_configured_last_alias_to_iso8601(): void
    {
        $row = AggregateQuery::normalizeRow(
            ['last_triggered' => '2026-07-16 06:40:52'],
            ['last' => 'created_at', 'last_alias' => 'last_triggered'],
        );

        $this->assertMatchesRegularExpression(self::ISO_WITH_ZONE, $row['last_triggered']);
        $this->assertSame('2026-07-16T06:40:52+00:00', $row['last_triggered']);
    }

    public function test_it_normalizes_the_default_last_alias_when_no_override_is_configured(): void
    {
        $row = AggregateQuery::normalizeRow(
            ['last_created_at' => '2026-07-16 06:40:52'],
            ['last' => 'created_at'],
        );

        $this->assertSame('2026-07-16T06:40:52+00:00', $row['last_created_at']);
    }

    /** MAX() over an empty group is null — it must stay null, not become the epoch or "now". */
    public function test_a_null_last_value_stays_null(): void
    {
        $row = AggregateQuery::normalizeRow(
            ['last_triggered' => null],
            ['last' => 'created_at', 'last_alias' => 'last_triggered'],
        );

        $this->assertNull($row['last_triggered']);
    }

    /** The ungrouped panels row never selects the alias at all; the key must not be invented. */
    public function test_a_missing_last_key_is_not_added(): void
    {
        $row = AggregateQuery::normalizeRow(
            ['total' => 0],
            ['last' => 'created_at', 'last_alias' => 'last_triggered'],
        );

        $this->assertArrayNotHasKey('last_triggered', $row);
    }

    public function test_a_config_without_a_last_column_is_untouched(): void
    {
        $row = AggregateQuery::normalizeRow(['total' => 3], []);

        $this->assertSame(3, $row['total']);
    }

    /** Idempotent: an already-ISO value survives a second pass unchanged. */
    public function test_an_already_iso8601_value_is_preserved(): void
    {
        $row = AggregateQuery::normalizeRow(
            ['last_triggered' => '2026-07-16T06:40:52+00:00'],
            ['last' => 'created_at', 'last_alias' => 'last_triggered'],
        );

        $this->assertSame('2026-07-16T06:40:52+00:00', $row['last_triggered']);
    }

    /** A non-UTC ISO value keeps its instant rather than being re-stamped as UTC. */
    public function test_an_iso8601_value_with_an_offset_keeps_its_instant(): void
    {
        $row = AggregateQuery::normalizeRow(
            ['last_triggered' => '2026-07-16T14:40:52+08:00'],
            ['last' => 'created_at', 'last_alias' => 'last_triggered'],
        );

        $this->assertTrue(Carbon::parse('2026-07-16T06:40:52Z')->equalTo(Carbon::parse($row['last_triggered'])));
    }

    public function test_a_datetime_instance_is_serialized_to_iso8601(): void
    {
        $row = AggregateQuery::normalizeRow(
            ['last_triggered' => new \DateTime('2026-07-16 06:40:52', new \DateTimeZone('UTC'))],
            ['last' => 'created_at', 'last_alias' => 'last_triggered'],
        );

        $this->assertSame('2026-07-16T06:40:52+00:00', $row['last_triggered']);
    }

    /**
     * The naive DB string is UTC because Postgres is UTC — that must not be
     * re-interpreted through PHP's ambient timezone (or config('app.timezone')).
     */
    public function test_a_naive_value_is_read_as_utc_regardless_of_the_ambient_php_timezone(): void
    {
        $original = date_default_timezone_get();
        date_default_timezone_set('Asia/Singapore');

        try {
            $row = AggregateQuery::normalizeRow(
                ['last_triggered' => '2026-07-16 06:40:52'],
                ['last' => 'created_at', 'last_alias' => 'last_triggered'],
            );

            $this->assertTrue(
                Carbon::parse('2026-07-16T06:40:52Z')->equalTo(Carbon::parse($row['last_triggered'])),
                "Naive value was skewed by the ambient timezone: {$row['last_triggered']}",
            );
        } finally {
            date_default_timezone_set($original);
        }
    }
}
