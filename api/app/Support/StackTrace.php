<?php

namespace App\Support;

/**
 * Light parse of a PHP stack-trace string into structured file:line frames for
 * the exception-detail and issue-detail Stack Trace panels. The single source
 * of truth for both App\Actions\Exceptions\ShowExceptionGroup and
 * App\Domains\Issues\Actions\ShowIssue (which delegates here — this lives in
 * App\Support, a shared namespace the Issues domain may import).
 */
class StackTrace
{
    /** @return array<int, array{index:int, file:string, line:int, function:string}> */
    public static function parse(?string $trace): array
    {
        if (! $trace) {
            return [];
        }

        $frames = [];

        foreach (preg_split('/\r?\n/', $trace) as $i => $line) {
            // A frame is `#N FILE(LINE): CALL` or `#N FILE(LINE)` (no call).
            // Take LINE from the `(\d+)` that delimits the file from the call
            // — the one immediately before `): ` (or the end of the line) —
            // so a parenthesized number inside the file PATH (e.g.
            // `/build/lib(1)/file.php(45)`) is never mistaken for the line
            // number. The first pattern (non-greedy file, anchored on the
            // `): ` call delimiter) covers frames with a call; the second
            // (greedy file, `(\d+)` at end of line) covers frames without one.
            if (preg_match('/^(?:#\d+\s+)?(.+?)\((\d+)\):\s*(.*)$/', $line, $m)
                || preg_match('/^(?:#\d+\s+)?(.+)\((\d+)\)\s*$/', $line, $m)) {
                $frames[] = [
                    'index' => $i,
                    'file' => trim($m[1]),
                    'line' => (int) $m[2],
                    'function' => trim($m[3] ?? ''),
                ];
            }
        }

        return $frames;
    }
}
