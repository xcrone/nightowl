<?php

namespace NightOwl\Agent;

/**
 * The one write operation SqsPoller depends on. SqliteBuffer is `final`
 * (per this package's convention — see agent/CLAUDE.md) and so can't be
 * class-mocked directly in tests; this narrow, single-method seam exists
 * purely to make SqsPoller testable without weakening SqliteBuffer itself.
 */
interface RawPayloadAppender
{
    public function appendRaw(string $json): void;
}
