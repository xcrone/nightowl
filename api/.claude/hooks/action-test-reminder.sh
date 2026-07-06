#!/usr/bin/env bash
# PostToolUse (Write|Edit) hook for the NightOwl api.
# When an Action under app/Domains/<Module>/Actions/<Name>.php is written or edited,
# remind Claude to create or update that Action's PHPUnit test in the same change
# (see the `action-test-sync` skill). This is a non-blocking nudge, not a gate:
# it emits additionalContext and always exits 0.
set -euo pipefail

input="$(cat)"

# Edit and Write both carry the target as tool_input.file_path.
file="$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty')"
[ -z "$file" ] && exit 0

# Only care about domain Action classes; never fire on the tests themselves.
case "$file" in
  *"/Actions/"*.php) ;;
  *) exit 0 ;;
esac
case "$file" in
  *Test.php) exit 0 ;;
esac

# Pull <Module> and <Name> from .../app/Domains/<Module>/Actions/<Name>.php
if [[ ! "$file" =~ app/Domains/([^/]+)/Actions/([^/]+)\.php$ ]]; then
  exit 0
fi
module="${BASH_REMATCH[1]}"
action="${BASH_REMATCH[2]}"

# api root = everything before "app/Domains" in the absolute path.
root="${file%%app/Domains/*}"
testdir="${root}tests/Feature/${module}"

if [ -d "$testdir" ] && ls "$testdir"/*Test.php >/dev/null 2>&1; then
  msg="You edited the Action ${module}/${action}. Per the action-test-sync skill, create or update its PHPUnit test in tests/Feature/${module}/ in this same change (happy path, authorize(), rules()), then run the gate: vendor/bin/phpunit, vendor/bin/pint."
else
  msg="You edited the Action ${module}/${action}, but tests/Feature/${module}/ has no test files yet. Per the action-test-sync skill, add a PHPUnit test covering this Action (happy path, authorize(), rules()) in this same change, then run: vendor/bin/phpunit, vendor/bin/pint."
fi

jq -cn --arg m "$msg" '{hookSpecificOutput:{hookEventName:"PostToolUse",additionalContext:$m}}'
exit 0
