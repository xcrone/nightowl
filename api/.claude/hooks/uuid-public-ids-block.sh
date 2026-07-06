#!/usr/bin/env bash
# PreToolUse (Write|Edit) hook for the NightOwl api.
# Enforces the uuid-public-ids rule: UUIDs are the only public identifier — the
# auto-increment integer `id` must never reach the SPA. BLOCKS (permissionDecision:"deny")
# when the PROPOSED content of a Resource/DTO leaks the integer PK, or a new user-facing
# migration creates a table without a `uuid` column. Best-effort content-pattern block —
# tune the patterns if it false-positives on internal-only serialization.
set -euo pipefail

input="$(cat)"
file="$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty')"
[ -z "$file" ] && exit 0

# Only care about identifier-bearing files inside a domain, plus migrations.
is_resource=0; is_migration=0
case "$file" in
  */app/Domains/*/Resources/*.php) is_resource=1 ;;
  */database/migrations/*.php|*/Migrations/*.php) is_migration=1 ;;
  *) exit 0 ;;
esac

# The proposed content: Write carries `content`, Edit carries `new_string`.
content="$(printf '%s' "$input" | jq -r '.tool_input.content // .tool_input.new_string // empty')"
[ -z "$content" ] && exit 0

deny() {
  jq -cn --arg r "$1" '{hookSpecificOutput:{hookEventName:"PreToolUse",permissionDecision:"deny",permissionDecisionReason:$r}}'
  exit 0
}

if [ "$is_resource" = 1 ]; then
  # A Resource/DTO serializing the integer primary key to the boundary.
  # Flags: an 'id' => ... array key, or $this->id / ->id being exposed.
  if printf '%s' "$content" | grep -Eq "['\"]id['\"][[:space:]]*=>|\\\$this->id\b|->getKey\(\)"; then
    deny "BLOCKED (uuid-public-ids): this Resource/DTO appears to serialize the integer primary key to the SPA ('id' => / \$this->id / getKey()). UUIDs are the only public identifier — expose the model's 'uuid' under a 'uuid' key and never the auto-increment id. See the uuid-public-ids skill."
  fi
fi

if [ "$is_migration" = 1 ]; then
  # A new user-facing table (Schema::create) that never defines a uuid column.
  if printf '%s' "$content" | grep -Eq 'Schema::create'; then
    if ! printf '%s' "$content" | grep -Eq "['\"]uuid['\"]|->uuid\(|->uuid\b"; then
      deny "BLOCKED (uuid-public-ids): this migration creates a table without a 'uuid' column. Every user-facing table needs an indexed public 'uuid' column (models bind routes on it and serialize it, never the integer id). Add \$table->uuid('uuid')->unique(); or, if this table is genuinely never exposed to the SPA, restructure so no internal id crosses the boundary. See the uuid-public-ids skill."
    fi
  fi
fi

exit 0
