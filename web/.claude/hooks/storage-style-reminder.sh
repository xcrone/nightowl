#!/usr/bin/env bash
# PostToolUse (Write|Edit) hook for the NightOwl web SPA.
# Nudges on styling when the PROPOSED content of a src/ file appears to break the
# Tailwind-only rule (web/CLAUDE.md): no inline style="", <style> blocks, or raw hex
# colours. Non-blocking reminder; conservative (checks only the edited file's proposed
# content). No persistence restriction here — this app legitimately persists several
# Pinia-store values to localStorage (period/timezone/timeFormat, theme), unlike a
# single-token convention.
set -euo pipefail

input="$(cat)"
file="$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty')"
[ -z "$file" ] && exit 0

case "$file" in
  */src/*) ;;
  *) exit 0 ;;
esac

content="$(printf '%s' "$input" | jq -r '.tool_input.content // .tool_input.new_string // empty')"
[ -z "$content" ] && exit 0

if printf '%s' "$content" | grep -Eq 'style="|<style|#[0-9a-fA-F]{3,6}\b'; then
  msg="REMINDER: use Tailwind utilities only (web/CLAUDE.md) — no inline style=\"\", <style> blocks, or raw hex colours. If Tailwind's utilities can't express it, prefer @apply or an arbitrary value."
  jq -cn --arg m "$msg" '{hookSpecificOutput:{hookEventName:"PostToolUse",additionalContext:$m}}'
fi
exit 0
