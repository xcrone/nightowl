#!/usr/bin/env bash
# PreToolUse (Bash) hook for the NightOwl api.
# BLOCKS destructive database commands (migrate:fresh, migrate:refresh, db:wipe) — these
# drop every table and destroy existing data. This is a strict rule, enforced by denying
# the tool call (permissionDecision:"deny"), not a reminder.
set -euo pipefail

input="$(cat)"
cmd="$(printf '%s' "$input" | jq -r '.tool_input.command // empty')"
[ -z "$cmd" ] && exit 0

# Match the destructive artisan commands anywhere in the command string.
if printf '%s' "$cmd" | grep -Eq 'migrate:fresh|migrate:refresh|db:wipe'; then
  reason="BLOCKED: destructive DB command detected. migrate:fresh / migrate:refresh / db:wipe drop every table and destroy existing data — never run them against this stack. To change schema, write a new migration and run 'docker compose exec api php artisan migrate', or undo with 'php artisan migrate:rollback'. Add --seed only when the data has not been seeded yet."
  jq -cn --arg r "$reason" '{hookSpecificOutput:{hookEventName:"PreToolUse",permissionDecision:"deny",permissionDecisionReason:$r}}'
  exit 0
fi
exit 0
