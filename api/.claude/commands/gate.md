---
description: Run the api "before declaring done" gate — PHPUnit + Pint — and report pass/fail.
allowed-tools: Bash
---

Run the NightOwl api gate and report the result. Execute both, and don't stop at the
first failure — run both so the user sees every issue at once:

1. `vendor/bin/phpunit`
2. `vendor/bin/pint`

If arguments were provided (`$ARGUMENTS`), treat them as a PHPUnit filter for step 1
(`vendor/bin/phpunit --filter=$ARGUMENTS`) but still run Pint across the suite.

Summarize: which of the two passed/failed, and for any failure the concise list of
offending files/tests. This is the gate — nothing is "done" until both pass. (There is no
PHPStan/Larastan configured on this side yet — don't invent a step for it.)
