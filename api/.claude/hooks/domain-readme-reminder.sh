#!/usr/bin/env bash
# PostToolUse (Write|Edit) hook for the NightOwl api.
# When business logic changes inside a domain (Actions, Policies, Events, Jobs, Models),
# remind Claude to update that domain's README.md in the same change
# (domain-readme-sync / domain-doc skills). Non-blocking nudge; exits 0.
set -euo pipefail

input="$(cat)"
file="$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty')"
[ -z "$file" ] && exit 0

# Never fire on the tests themselves or on the README we are asking to update.
case "$file" in
  *Test.php) exit 0 ;;
  */README.md) exit 0 ;;
esac

# Only business-logic dirs inside a domain.
if [[ ! "$file" =~ app/Domains/([^/]+)/(Actions|Policies|Events|Jobs|Models)/ ]]; then
  exit 0
fi
module="${BASH_REMATCH[1]}"

msg="You changed business logic in the ${module} domain. Per the domain-readme-sync skill, update app/Domains/${module}/README.md in this same change so it reflects the current Actions/events/routes (see domain-doc for its shape)."
jq -cn --arg m "$msg" '{hookSpecificOutput:{hookEventName:"PostToolUse",additionalContext:$m}}'
exit 0
