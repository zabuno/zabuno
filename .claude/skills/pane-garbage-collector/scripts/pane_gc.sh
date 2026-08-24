#!/usr/bin/env bash
# Pane garbage collector: dry-run by default, archives at most one fully
# proven safe Pane per invocation. Event-driven only — this script must
# never be invoked from a timer/daemon/cron/sleep-loop; it is meant to be
# run once, on demand, in response to a real lifecycle event.
#
# Usage: pane_gc.sh [--apply] [--current-pane <ID>]
#
# Safety contract (see .claude/skills/pane-garbage-collector/SKILL.md):
#   - dry-run unless --apply is passed
#   - in --apply mode, a non-empty caller id is mandatory (--current-pane,
#     PANE_GC_CURRENT_PANE_ID, or PANE_SESSION_ID) or the run fails closed
#     with zero archives; dry-run may still run without one
#   - protects: current/caller pane (by id AND by worktree realpath
#     affinity — any realpath resolution failure also protects), non-stopped
#     status, pinned, any panel with initialized=true or an unverifiable
#     panels response, dirty/untracked/unpushed/missing/unverifiable
#     worktrees, and any ambiguous status
#   - at most one archive per run, exactly:
#       runpane panes archive --pane <ID> --source agent --yes --json
#   - a candidate is only ever reported "archived" once that command exits
#     zero; a dry-run only ever reports it "eligible", never "archived"
#   - never issues kill/signal/force/reset --hard/stash/checkout --/delete
#   - fails closed on doctor failure, missing jq, archive-command failure,
#     or any parse ambiguity

set -u

APPLY=0
CURRENT_PANE_ARG=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --apply)
      APPLY=1
      shift
      ;;
    --current-pane)
      CURRENT_PANE_ARG="${2:-}"
      shift 2
      ;;
    *)
      echo "pane_gc: unknown argument: $1" >&2
      exit 2
      ;;
  esac
done

CURRENT_PANE_ID="${CURRENT_PANE_ARG:-${PANE_GC_CURRENT_PANE_ID:-${PANE_SESSION_ID:-}}}"

if [[ "${APPLY}" -eq 1 && -z "${CURRENT_PANE_ID}" ]]; then
  echo "pane_gc: --apply requires a non-empty caller id (--current-pane, PANE_GC_CURRENT_PANE_ID, or PANE_SESSION_ID); failing closed" >&2
  exit 1
fi

CALLER_REALPATH=""
if CALLER_REALPATH="$(pwd -P 2>/dev/null)"; then
  :
else
  CALLER_REALPATH=""
fi

worktree_matches_caller() {
  # worktree_matches_caller <path> -> 0 (protect) if it resolves to the
  # caller's own realpath, or if either side cannot be resolved.
  local wt="$1"
  [[ -n "${CALLER_REALPATH}" ]] || return 0
  [[ -n "${wt}" && -d "${wt}" ]] || return 0
  local wt_real
  wt_real="$(cd "${wt}" 2>/dev/null && pwd -P 2>/dev/null)" || return 0
  [[ -n "${wt_real}" ]] || return 0
  [[ "${wt_real}" == "${CALLER_REALPATH}" ]]
}

command -v jq >/dev/null 2>&1 || {
  echo "pane_gc: jq is required; failing closed (no archive)" >&2
  exit 1
}
command -v runpane >/dev/null 2>&1 || {
  echo "pane_gc: runpane is required; failing closed (no archive)" >&2
  exit 1
}

# --- Preflight: runpane doctor ----------------------------------------------
if ! runpane doctor >/dev/null 2>&1; then
  echo "pane_gc: runpane doctor failed; failing closed (no archive)" >&2
  exit 1
fi

# --- Fetch panes list --------------------------------------------------------
PANES_JSON="$(runpane panes list --json 2>/dev/null)" || {
  echo "pane_gc: runpane panes list failed; failing closed" >&2
  exit 1
}
echo "${PANES_JSON}" | jq -e '.panes' >/dev/null 2>&1 || {
  echo "pane_gc: unparsable panes list; failing closed" >&2
  exit 1
}

PANE_COUNT="$(echo "${PANES_JSON}" | jq '.panes | length')"

worktree_is_clean_and_pushed() {
  # worktree_is_clean_and_pushed <path> -> 0 if fully clean+pushed+readable, 1 otherwise
  local wt="$1"

  [[ -n "${wt}" && -d "${wt}" ]] || return 1
  git -C "${wt}" rev-parse --is-inside-work-tree >/dev/null 2>&1 || return 1

  local porcelain
  porcelain="$(git -C "${wt}" status --porcelain 2>/dev/null)" || return 1
  [[ -z "${porcelain}" ]] || return 1

  local upstream
  upstream="$(git -C "${wt}" rev-parse --abbrev-ref --symbolic-full-name '@{u}' 2>/dev/null)" || return 1
  [[ -n "${upstream}" ]] || return 1

  local ahead
  ahead="$(git -C "${wt}" rev-list --count '@{u}..HEAD' 2>/dev/null)" || return 1
  [[ "${ahead}" =~ ^[0-9]+$ ]] || return 1
  [[ "${ahead}" -eq 0 ]] || return 1

  return 0
}

pane_has_initialized_panel() {
  # pane_has_initialized_panel <pane_id> -> 0 (protect) if any panel
  # initialized=true, OR if the query fails, OR the JSON is invalid/missing
  # its .panels array. Only a valid JSON response proving no initialized=true
  # panel returns non-zero (safe to proceed).
  local pane_id="$1"
  local panels_json
  panels_json="$(runpane panels list --pane "${pane_id}" --json 2>/dev/null)" || return 0
  echo "${panels_json}" | jq -e '(.panels | type) == "array"' >/dev/null 2>&1 || return 0
  echo "${panels_json}" | jq -e '.panels | any(.initialized == true)' >/dev/null 2>&1
}

DONE=0
IDX=0
while [[ "${IDX}" -lt "${PANE_COUNT}" ]]; do
  PANE="$(echo "${PANES_JSON}" | jq -c ".panes[${IDX}]")"
  IDX=$((IDX + 1))

  [[ "${DONE}" -eq 0 ]] || break

  PANE_ID="$(echo "${PANE}" | jq -r '.id // empty')"
  STATUS="$(echo "${PANE}" | jq -r '.status // empty')"
  PINNED="$(echo "${PANE}" | jq -r 'if .pinned == null then true else .pinned end')"
  WT_PATH="$(echo "${PANE}" | jq -r '.worktreePath // empty')"

  [[ -n "${PANE_ID}" ]] || continue

  # Protect: current/caller pane, by id.
  if [[ -n "${CURRENT_PANE_ID}" && "${PANE_ID}" == "${CURRENT_PANE_ID}" ]]; then
    continue
  fi

  # Protect: current/caller pane, by worktree affinity (realpath match, or
  # any realpath resolution failure on either side).
  if worktree_matches_caller "${WT_PATH}"; then
    continue
  fi

  # Protect: anything other than a proven "stopped" status.
  [[ "${STATUS}" == "stopped" ]] || continue

  # Protect: pinned panes.
  [[ "${PINNED}" == "false" ]] || continue

  # Protect: any initialized panel, or an unverifiable panels response.
  if pane_has_initialized_panel "${PANE_ID}"; then
    continue
  fi

  # Protect: dirty/untracked/unpushed/missing/unverifiable worktrees.
  worktree_is_clean_and_pushed "${WT_PATH}" || continue

  # Fully proven safe candidate.
  if [[ "${APPLY}" -eq 1 ]]; then
    if runpane panes archive --pane "${PANE_ID}" --source agent --yes --json >/dev/null; then
      DONE=1
      echo "pane_gc: archived pane ${PANE_ID}"
    else
      echo "pane_gc: archive command failed for ${PANE_ID}; failing closed" >&2
      exit 1
    fi
  else
    DONE=1
    echo "pane_gc: eligible candidate ${PANE_ID} (dry-run, not archived)"
  fi
done

if [[ "${DONE}" -eq 0 ]]; then
  echo "pane_gc: no eligible pane found"
fi

# --- Post-action PTY pressure probe -----------------------------------------
# Injected contract only (PANE_GC_PTY_USED/PANE_GC_PTY_MAX); when unset, fall
# back to a cheap, read-only local probe: count DISTINCT OPEN /dev/ttys*
# descriptors via a single lsof pass over all processes (never per-path
# arguments), and read the configured max via sysctl. Counting device nodes
# under /dev would count allocated-but-idle PTYs, not open ones, and could
# report permanent false pressure — lsof's open-file view is required. This
# fallback is never exercised by the acceptance test, only by real
# invocations that omit the injected vars.
PTY_USED="${PANE_GC_PTY_USED:-}"
PTY_MAX="${PANE_GC_PTY_MAX:-}"

if [[ -z "${PTY_USED}" || -z "${PTY_MAX}" ]]; then
  if command -v sysctl >/dev/null 2>&1 && command -v lsof >/dev/null 2>&1; then
    PTY_MAX="$(sysctl -n kern.tty.ptmx_max 2>/dev/null || true)"
    PTY_USED="$(lsof -nP 2>/dev/null | awk '$9 ~ /^\/dev\/ttys[0-9A-Fa-f]+$/ { seen[$9]=1 } END { print length(seen) }')"
    [[ "${PTY_USED}" =~ ^[0-9]+$ ]] || PTY_USED=""
  fi
fi

if [[ -n "${PTY_USED}" && -n "${PTY_MAX}" && "${PTY_MAX}" != "0" ]]; then
  RATIO_OK="$(awk -v used="${PTY_USED}" -v max="${PTY_MAX}" -v thr="${PANE_GC_PTY_THRESHOLD:-0.8}" \
    'BEGIN { print (max > 0 && (used / max) >= thr) ? "1" : "0" }' 2>/dev/null || echo "0")"
  if [[ "${RATIO_OK}" == "1" ]]; then
    echo "PANE_RESTART_REQUIRED"
  fi
else
  echo "pane_gc: PTY pressure probe unavailable; not guessing"
fi

exit 0
