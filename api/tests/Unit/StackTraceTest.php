<?php

namespace Tests\Unit;

use App\Support\StackTrace;
use PHPUnit\Framework\TestCase;

/**
 * App\Support\StackTrace::parse — the single stack-trace parser shared by
 * App\Actions\Exceptions\ShowExceptionGroup and
 * App\Domains\Issues\Actions\ShowIssue.
 */
class StackTraceTest extends TestCase
{
    public function test_null_or_empty_trace_yields_no_frames(): void
    {
        $this->assertSame([], StackTrace::parse(null));
        $this->assertSame([], StackTrace::parse(''));
    }

    public function test_parses_plain_frames_into_file_line_function(): void
    {
        $frames = StackTrace::parse("#0 /app/Services/Foo.php(12): bar()\n#1 /app/Jobs/Baz.php(44): qux()");

        $this->assertCount(2, $frames);
        $this->assertSame(['index' => 0, 'file' => '/app/Services/Foo.php', 'line' => 12, 'function' => 'bar()'], $frames[0]);
        $this->assertSame(['index' => 1, 'file' => '/app/Jobs/Baz.php', 'line' => 44, 'function' => 'qux()'], $frames[1]);
    }

    public function test_parenthesized_number_in_the_path_is_not_mistaken_for_the_line(): void
    {
        // The path segment `/build/lib(1)` contains a parenthesized number; the
        // real line/file delimiter is the `(45): ` immediately before the call.
        $frames = StackTrace::parse('#0 /build/lib(1)/file.php(45): Foo->bar()');

        $this->assertCount(1, $frames);
        $this->assertSame('/build/lib(1)/file.php', $frames[0]['file']);
        $this->assertSame(45, $frames[0]['line']);
        $this->assertSame('Foo->bar()', $frames[0]['function']);
    }

    public function test_frame_without_a_call_still_parses_file_and_line(): void
    {
        $frames = StackTrace::parse('#3 /build/lib(1)/file.php(99)');

        $this->assertCount(1, $frames);
        $this->assertSame('/build/lib(1)/file.php', $frames[0]['file']);
        $this->assertSame(99, $frames[0]['line']);
        $this->assertSame('', $frames[0]['function']);
    }
}
