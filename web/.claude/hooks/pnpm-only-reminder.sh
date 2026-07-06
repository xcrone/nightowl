#!/usr/bin/env bash
# PreToolUse (Bash) hook for the NightOwl web SPA.
# The package manager is pnpm (pnpm-lock.yaml is the lockfile) — npm/yarn would desync
# the lockfile. Non-blocking reminder when an npm/yarn invocation is detected. Exits 0.
set -euo pipefail

input="$(cat)"
cmd="$(printf '%s' "$input" | jq -r '.tool_input.command // empty')"
[ -z "$cmd" ] && exit 0

# Match npm/yarn as a command word (avoid matching e.g. "pnpm" or paths). Skip npx.
if printf '%s' "$cmd" | grep -Eq '(^|[[:space:]&|;])(npm|yarn)[[:space:]]'; then
  msg="REMINDER: the web SPA uses pnpm (pnpm-lock.yaml is the lockfile) — don't use npm or yarn, they desync the lockfile. Run web commands as 'docker compose exec web pnpm …' (e.g. pnpm add, pnpm install, pnpm test)."
  jq -cn --arg m "$msg" '{hookSpecificOutput:{hookEventName:"PreToolUse",additionalContext:$m}}'
fi
exit 0
