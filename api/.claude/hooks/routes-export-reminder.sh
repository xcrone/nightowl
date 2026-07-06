#!/usr/bin/env bash
# PostToolUse (Write|Edit) hook for the NightOwl api.
# When a domain route file is written or edited (routes/api.php or a module's
# app/Domains/<Module>/Routes/api.php), remind Claude that the routes:export workflow is
# the target convention but ISN'T BUILT YET (see .claude/rules/routes.md). Non-blocking
# nudge: emits additionalContext and always exits 0.
set -euo pipefail

input="$(cat)"
file="$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty')"
[ -z "$file" ] && exit 0

# Only the api route source files — the aggregator and each module's Routes/api.php.
case "$file" in
  */routes/api.php) ;;
  */app/Domains/*/Routes/api.php) ;;
  *) exit 0 ;;
esac

msg="You edited an api route source. Once the routes:export pipeline exists (see .claude/rules/routes.md — it's a target convention, not yet built), routes are api-owned and this is where you'd regenerate the SPA's route map. Until then, the SPA calls endpoints directly via web/src/services/api.js — no export step to run."
jq -cn --arg m "$msg" '{hookSpecificOutput:{hookEventName:"PostToolUse",additionalContext:$m}}'
exit 0
