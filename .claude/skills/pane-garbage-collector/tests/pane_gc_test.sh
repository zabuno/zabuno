#!/usr/bin/env bash
# RED acceptance test for the (not-yet-written) Pane garbage collector script.
#
# Contract under test for .claude/skills/pane-garbage-collector/scripts/pane_gc.sh:
#   - event-driven only: never a timer/daemon/scheduled process (no crontab/launchd/sleep-loop use)
#   - dry-run by default (no state-changing command unless --apply is passed)
#   - `runpane panes list --json` carries NO "current" field. The caller/current Pane must be
#     protected by comparing the pane id against PANE_GC_CURRENT_PANE_ID (env) or an explicit
#     --current-pane <ID> argument.
#   - a candidate is also protected if `runpane panels list --pane <ID> --json` reports any
#     panel with initialized=true — Pane status=stopped alone is NOT sufficient proof the pane
#     is idle, and panel active=true alone is NOT proof of a running process either.
#   - dirty / untracked / unpushed state is derived from real `git -C <worktree> ...` results
#     against fixture git repositories (not hand-authored porcelain text).
#   - protects: caller/current Pane, active/running Panes, pinned Panes, any initialized panel,
#     dirty worktrees, untracked files, unpushed commits, missing/unreadable/unverifiable
#     worktrees, and any ambiguous candidate (fails closed, not eligible)
#   - only a stopped, unpinned, no-initialized-panel, fully clean, pushed, non-current Pane may
#     ever be eligible
#   - in --apply mode, at most one non-force command may be issued per archived pane, exactly:
#       runpane panes archive --pane <ID> --source agent --yes --json
#   - never issues kill/signal/force/reset/stash/delete against Pane or git state
#   - if the mocked "doctor" check fails, the run must fail closed: zero archive commands issued
#   - PTY pressure is an injectable test contract only: PANE_GC_PTY_USED / PANE_GC_PTY_MAX. If
#     post-archive USED/MAX >= 1.0, the script must emit the literal token PANE_RESTART_REQUIRED
#     on stdout and must NOT itself attempt any Pane restart/relaunch command. No fictional
#     `runpane pty ...` subcommand is assumed; a future implementation may fall back to a real
#     local probe when these env vars are unset, but this test never exercises that fallback.
#   - fixtures here are fully self-contained (temp dir + mocked `runpane` + real throwaway git
#     repos under WORKDIR) and never touch real Pane state or the real repository
#
# This test is intentionally RED right now: scripts/pane_gc.sh does not exist yet.

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SKILL_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
IMPL="${SKILL_DIR}/scripts/pane_gc.sh"

PASS=0
FAIL=0

fail() { FAIL=$((FAIL + 1)); echo "FAIL: $1"; }
pass() { PASS=$((PASS + 1)); echo "PASS: $1"; }

# --- Fixture sandbox --------------------------------------------------------
# Everything (including all captured command output) lives under WORKDIR, a
# throwaway temp dir. No real Pane, no real git remote, no real runpane
# binary is touched by this test.
WORKDIR="$(mktemp -d "${TMPDIR:-/tmp}/pane_gc_test.XXXXXX")"
cleanup() { rm -rf "${WORKDIR}"; }
trap cleanup EXIT

OUT_DIR="${WORKDIR}/out"
mkdir -p "${OUT_DIR}"

FIXTURE_BIN="${WORKDIR}/bin"
mkdir -p "${FIXTURE_BIN}"

CALL_LOG="${WORKDIR}/calls.log"
: > "${CALL_LOG}"

FIXTURE_DIR="${WORKDIR}/fixtures"
mkdir -p "${FIXTURE_DIR}"
PANES_LIST_ACTIVE="${WORKDIR}/panes_list_active.json"

# --- Mocked runpane ----------------------------------------------------------
# Records every invocation, never touches real Pane state. `panes list` reads
# whatever file PANE_GC_TEST_PANES_LIST points at so each scenario can swap in
# a different candidate set. `panels list` reads a per-pane fixture file.
cat > "${FIXTURE_BIN}/runpane" <<'MOCK'
#!/usr/bin/env bash
echo "runpane $*" >> "${PANE_GC_TEST_CALL_LOG}"

if [[ "$1" == "doctor" ]]; then
  [[ "${PANE_GC_TEST_DOCTOR_STATUS:-ok}" == "fail" ]] && exit 1
  exit 0
fi

if [[ "$1" == "panes" && "$2" == "list" ]]; then
  cat "${PANE_GC_TEST_PANES_LIST}"
  exit 0
fi

if [[ "$1" == "panels" && "$2" == "list" ]]; then
  # Expect: panels list --pane <ID> --json
  if [[ "$3" != "--pane" || -z "${4:-}" || "$5" != "--json" ]]; then
    echo "MOCK_ERROR: malformed panels list invocation: $*" >&2
    exit 2
  fi
  pane_id="$4"
  fx="${PANE_GC_TEST_FIXTURE_DIR}/panels_${pane_id}.json"
  if [[ -f "${fx}" ]]; then
    cat "${fx}"
  else
    echo '{"panels":[]}'
  fi
  exit 0
fi

if [[ "$1" == "panes" && "$2" == "archive" ]]; then
  # Must be exactly: panes archive --pane <ID> --source agent --yes --json
  if [[ "$3" != "--pane" || -z "${4:-}" || "$5" != "--source" || "$6" != "agent" || "$7" != "--yes" || "$8" != "--json" ]]; then
    echo "MOCK_ERROR: malformed archive invocation: $*" >&2
    exit 2
  fi
  echo "{\"pane\":\"$4\",\"archived\":true}"
  exit 0
fi

echo "MOCK_ERROR: unexpected runpane invocation: $*" >&2
exit 3
MOCK
chmod +x "${FIXTURE_BIN}/runpane"

# --- git shim ----------------------------------------------------------------
# NOT a fake git: logs the call, refuses destructive subcommands outright (the
# implementation must never issue them), then execs the real system git so
# dirty/untracked/unpushed results come from an actual `git -C <worktree> ...`
# run against the throwaway fixture repos below.
REAL_GIT="$(command -v git)"
cat > "${FIXTURE_BIN}/git" <<MOCK
#!/usr/bin/env bash
echo "git \$*" >> "\${PANE_GC_TEST_CALL_LOG}"
case " \$* " in
  *" reset --hard "*|*" clean -"*|*" stash "*|*" checkout -- "*)
    echo "MOCK_ERROR: destructive git subcommand invoked: \$*" >&2
    exit 9
    ;;
esac
exec "${REAL_GIT}" "\$@"
MOCK
chmod +x "${FIXTURE_BIN}/git"

export PANE_GC_TEST_CALL_LOG="${CALL_LOG}"
export PANE_GC_TEST_FIXTURE_DIR="${FIXTURE_DIR}"
export PATH="${FIXTURE_BIN}:${PATH}"

# --- Real throwaway git repos, one per worktree condition -------------------
WT_ROOT="${WORKDIR}/worktrees"
mkdir -p "${WT_ROOT}"
REMOTE_ROOT="${WORKDIR}/remotes"
mkdir -p "${REMOTE_ROOT}"

make_repo() {
  # make_repo <name> -> sets up WT_ROOT/<name> as a real git repo with a
  # pushed bare remote and one committed file, working tree clean.
  local name="$1" wt="${WT_ROOT}/$1" remote="${REMOTE_ROOT}/$1.git"
  "${REAL_GIT}" init -q --bare "${remote}"
  "${REAL_GIT}" -C "${WORKDIR}" clone -q "${remote}" "${wt}"
  "${REAL_GIT}" -C "${wt}" config user.email "test@example.invalid"
  "${REAL_GIT}" -C "${wt}" config user.name "Pane GC Test"
  echo "seed" > "${wt}/tracked.txt"
  "${REAL_GIT}" -C "${wt}" add tracked.txt
  "${REAL_GIT}" -C "${wt}" commit -q -m "seed"
  "${REAL_GIT}" -C "${wt}" push -q origin HEAD:refs/heads/main -u
}

# eligible / current: clean, committed, pushed.
make_repo "eligible"
make_repo "current_candidate"
make_repo "panel_active"
make_repo "pinned"
make_repo "dirty"
make_repo "untracked"
make_repo "unpushed"
make_repo "ambiguous"
# "missing" worktree deliberately not created on disk.

# dirty: modify the tracked file without committing.
echo "changed" > "${WT_ROOT}/dirty/tracked.txt"

# untracked: add a new file, never `git add`ed.
echo "new" > "${WT_ROOT}/untracked/scratch.txt"

# unpushed: commit locally, do not push.
echo "local only" > "${WT_ROOT}/unpushed/tracked.txt"
"${REAL_GIT}" -C "${WT_ROOT}/unpushed" commit -q -am "local-only change"

# Sanity: prove the fixtures actually exercise real git state (guards this
# test itself against a no-op git shim silently reporting everything clean).
[[ -n "$("${REAL_GIT}" -C "${WT_ROOT}/dirty" status --porcelain)" ]] || fail "fixture bug: dirty worktree is not actually dirty"
[[ -n "$("${REAL_GIT}" -C "${WT_ROOT}/untracked" status --porcelain)" ]] || fail "fixture bug: untracked worktree has no untracked entry"
[[ -z "$("${REAL_GIT}" -C "${WT_ROOT}/eligible" status --porcelain)" ]] || fail "fixture bug: eligible worktree is not clean"
UNPUSHED_COUNT="$("${REAL_GIT}" -C "${WT_ROOT}/unpushed" rev-list --count '@{u}..HEAD' 2>/dev/null || echo -1)"
[[ "${UNPUSHED_COUNT}" -ge 1 ]] || fail "fixture bug: unpushed worktree has no unpushed commits ahead of upstream"

# --- Panel fixtures: initialized=true must protect regardless of status ----
cat > "${FIXTURE_DIR}/panels_pane-eligible.json" <<'JSON'
{"panels":[{"initialized": false}]}
JSON
cat > "${FIXTURE_DIR}/panels_pane-current.json" <<'JSON'
{"panels":[{"initialized": false}]}
JSON
cat > "${FIXTURE_DIR}/panels_pane-panel-active.json" <<'JSON'
{"panels":[{"initialized": false, "active": true}, {"initialized": true}]}
JSON
cat > "${FIXTURE_DIR}/panels_pane-pinned.json" <<'JSON'
{"panels":[{"initialized": false}]}
JSON
cat > "${FIXTURE_DIR}/panels_pane-dirty.json" <<'JSON'
{"panels":[{"initialized": false}]}
JSON
cat > "${FIXTURE_DIR}/panels_pane-untracked.json" <<'JSON'
{"panels":[{"initialized": false}]}
JSON
cat > "${FIXTURE_DIR}/panels_pane-unpushed.json" <<'JSON'
{"panels":[{"initialized": false}]}
JSON
cat > "${FIXTURE_DIR}/panels_pane-missing.json" <<'JSON'
{"panels":[{"initialized": false}]}
JSON
cat > "${FIXTURE_DIR}/panels_pane-ambiguous.json" <<'JSON'
{"panels":[{"initialized": false}]}
JSON

# --- Pane list builders ------------------------------------------------------
# NOTE: no "current" field anywhere — the live schema does not carry one.
pane_json() {
  # pane_json <id> <status> <pinned> <worktree>
  printf '{"id":"%s","status":"%s","pinned":%s,"worktreePath":"%s"}' "$1" "$2" "$3" "$4"
}

write_panes_list() {
  # write_panes_list <outfile> <pane_json...>
  local out="$1"; shift
  {
    printf '{"panes":['
    local first=1
    for p in "$@"; do
      [[ $first -eq 1 ]] || printf ','
      printf '%s' "$p"
      first=0
    done
    printf ']}'
  } > "${out}"
}

P_ELIGIBLE="$(pane_json pane-eligible stopped false "${WT_ROOT}/eligible")"
P_CURRENT="$(pane_json pane-current stopped false "${WT_ROOT}/current_candidate")"
P_ACTIVE="$(pane_json pane-active running false "${WT_ROOT}/eligible")"
P_PANEL_ACTIVE="$(pane_json pane-panel-active stopped false "${WT_ROOT}/panel_active")"
P_PINNED="$(pane_json pane-pinned stopped true "${WT_ROOT}/pinned")"
P_DIRTY="$(pane_json pane-dirty stopped false "${WT_ROOT}/dirty")"
P_UNTRACKED="$(pane_json pane-untracked stopped false "${WT_ROOT}/untracked")"
P_UNPUSHED="$(pane_json pane-unpushed stopped false "${WT_ROOT}/unpushed")"
P_MISSING="$(pane_json pane-missing stopped false "${WT_ROOT}/does-not-exist")"
P_AMBIGUOUS="$(pane_json pane-ambiguous unknown false "${WT_ROOT}/ambiguous")"

# Full mixed roster: used for the "exactly one, exact shape" assertion.
write_panes_list "${WORKDIR}/panes_list_full.json" \
  "${P_ELIGIBLE}" "${P_ACTIVE}" "${P_PANEL_ACTIVE}" "${P_PINNED}" "${P_DIRTY}" \
  "${P_UNTRACKED}" "${P_UNPUSHED}" "${P_MISSING}" "${P_AMBIGUOUS}"

run_impl() {
  # run_impl <panes_list_file> [extra args...]
  local list_file="$1"; shift
  PANE_GC_TEST_PANES_LIST="${list_file}" "${IMPL}" "$@"
}

# --- Guard: implementation must exist before any behavioral assertion ------
if [[ ! -x "${IMPL}" ]]; then
  fail "implementation script missing or not executable: ${IMPL} (expected .claude/skills/pane-garbage-collector/scripts/pane_gc.sh to exist and be executable)"
  echo ""
  echo "== pane_gc_test.sh summary =="
  echo "PASS=${PASS} FAIL=${FAIL}"
  echo "MISSING_IMPLEMENTATION: ${IMPL}"
  exit 1
fi

# =============================================================================
# Behavioral assertions (only reachable once IMPL exists)
# =============================================================================

# 1. Dry-run by default: no archive command issued without --apply.
: > "${CALL_LOG}"
PANE_GC_CURRENT_PANE_ID="pane-current" \
  run_impl "${WORKDIR}/panes_list_full.json" \
  > "${OUT_DIR}/dryrun.out" 2>&1
if grep -q "runpane panes archive" "${CALL_LOG}"; then
  fail "default (no --apply) run issued an archive command; must be dry-run only"
else
  pass "default run is dry-run (no archive command issued)"
fi

# 2. Apply mode archives only the eligible pane, with the exact command shape,
#    and every other roster member is left alone (mixed-roster sanity check;
#    isolation of each individual protection rule is proven in section 4).
: > "${CALL_LOG}"
PANE_GC_TEST_DOCTOR_STATUS=ok PANE_GC_CURRENT_PANE_ID="pane-current" \
  run_impl "${WORKDIR}/panes_list_full.json" --apply \
  > "${OUT_DIR}/apply_full.out" 2>&1
ARCHIVE_CALLS="$(grep -c "runpane panes archive" "${CALL_LOG}" || true)"
if [[ "${ARCHIVE_CALLS}" -ne 1 ]]; then
  fail "expected exactly 1 archive command in --apply mode, got ${ARCHIVE_CALLS}"
else
  pass "exactly one archive command issued in --apply mode"
fi
if grep -q "runpane panes archive --pane pane-eligible --source agent --yes --json" "${CALL_LOG}"; then
  pass "archive command matches required exact shape for the eligible pane"
else
  fail "archive command did not match required exact shape for pane-eligible"
fi
for protected in pane-active pane-panel-active pane-pinned pane-dirty pane-untracked pane-unpushed pane-missing pane-ambiguous; do
  if grep -q "runpane panes archive --pane ${protected} " "${CALL_LOG}"; then
    fail "protected pane was archived: ${protected}"
  else
    pass "protected pane left alone (mixed roster): ${protected}"
  fi
done

# 3. No destructive/force verbs ever appear in the call log.
: > "${CALL_LOG}"
PANE_GC_TEST_DOCTOR_STATUS=ok PANE_GC_CURRENT_PANE_ID="pane-current" \
  run_impl "${WORKDIR}/panes_list_full.json" --apply \
  > "${OUT_DIR}/apply_verbs.out" 2>&1
if grep -Eiq "(^|[[:space:]])(kill|signal|--force|-f[[:space:]]|reset --hard|stash|delete)([[:space:]]|$)" "${CALL_LOG}"; then
  fail "call log contains a forbidden destructive/force verb"
else
  pass "no destructive/force verbs issued"
fi

# 4. Isolation: each protected condition, alone in a single-candidate roster
#    (so max-one-archive-per-run cannot mask a false eligibility), must yield
#    ZERO archive calls.
assert_zero_archives_alone() {
  # assert_zero_archives_alone <label> <current_pane_id> <pane_json...>
  local label="$1" current="$2"; shift 2
  local list_file="${WORKDIR}/panes_list_iso_$(echo "${label}" | tr -c 'a-zA-Z0-9' '_').json"
  write_panes_list "${list_file}" "$@"
  : > "${CALL_LOG}"
  PANE_GC_TEST_DOCTOR_STATUS=ok PANE_GC_CURRENT_PANE_ID="${current}" \
    run_impl "${list_file}" --apply \
    > "${OUT_DIR}/iso_$(echo "${label}" | tr -c 'a-zA-Z0-9' '_').out" 2>&1
  if grep -q "runpane panes archive" "${CALL_LOG}"; then
    fail "isolated protected condition still archived: ${label}"
  else
    pass "isolated protected condition yields zero archives: ${label}"
  fi
}

# current pane: sole candidate IS the current pane (would otherwise look
# eligible — stopped, unpinned, clean, pushed).
assert_zero_archives_alone "current-pane" "pane-current" "${P_CURRENT}"
# active/running status, sole candidate.
assert_zero_archives_alone "active-status" "pane-none" "${P_ACTIVE}"
# stopped status but an initialized panel — proves status=stopped alone is
# not sufficient.
assert_zero_archives_alone "initialized-panel" "pane-none" "${P_PANEL_ACTIVE}"
# pinned, sole candidate.
assert_zero_archives_alone "pinned" "pane-none" "${P_PINNED}"
# dirty worktree, sole candidate.
assert_zero_archives_alone "dirty-worktree" "pane-none" "${P_DIRTY}"
# untracked files, sole candidate.
assert_zero_archives_alone "untracked-files" "pane-none" "${P_UNTRACKED}"
# unpushed commits, sole candidate.
assert_zero_archives_alone "unpushed-commits" "pane-none" "${P_UNPUSHED}"
# missing worktree, sole candidate.
assert_zero_archives_alone "missing-worktree" "pane-none" "${P_MISSING}"
# ambiguous/unrecognized status, sole candidate.
assert_zero_archives_alone "ambiguous-status" "pane-none" "${P_AMBIGUOUS}"

# Positive control for the isolation harness itself: the eligible pane alone
# (as sole candidate, current pane pointed elsewhere) MUST be archived, or
# the isolation assertions above would be vacuously true.
: > "${CALL_LOG}"
write_panes_list "${WORKDIR}/panes_list_iso_positive.json" "${P_ELIGIBLE}"
PANE_GC_TEST_DOCTOR_STATUS=ok PANE_GC_CURRENT_PANE_ID="pane-none" \
  run_impl "${WORKDIR}/panes_list_iso_positive.json" --apply \
  > "${OUT_DIR}/iso_positive.out" 2>&1
if grep -q "runpane panes archive --pane pane-eligible --source agent --yes --json" "${CALL_LOG}"; then
  pass "positive control: truly eligible sole candidate is archived (isolation harness is not vacuous)"
else
  fail "positive control failed: truly eligible sole candidate was NOT archived — isolation results above are not trustworthy"
fi

# --current-pane argument form (alternative to PANE_GC_CURRENT_PANE_ID) must
# also protect the caller.
: > "${CALL_LOG}"
write_panes_list "${WORKDIR}/panes_list_iso_current_arg.json" "${P_CURRENT}"
run_impl "${WORKDIR}/panes_list_iso_current_arg.json" --apply --current-pane pane-current \
  > "${OUT_DIR}/iso_current_arg.out" 2>&1
if grep -q "runpane panes archive" "${CALL_LOG}"; then
  fail "--current-pane argument form did not protect the caller's pane"
else
  pass "--current-pane argument form protects the caller's pane"
fi

# 5. Doctor failure => fail closed, zero archive commands (against the full
#    mixed roster, which contains a genuinely eligible pane).
: > "${CALL_LOG}"
PANE_GC_TEST_DOCTOR_STATUS=fail PANE_GC_CURRENT_PANE_ID="pane-current" \
  run_impl "${WORKDIR}/panes_list_full.json" --apply \
  > "${OUT_DIR}/doctorfail.out" 2>&1
if grep -q "runpane panes archive" "${CALL_LOG}"; then
  fail "archive command issued despite failed doctor check"
else
  pass "doctor failure fails closed (zero archive commands)"
fi

# 6. Post-archive PTY pressure at/above threshold emits PANE_RESTART_REQUIRED,
#    and the script never issues a restart/relaunch command itself. Pressure
#    is injected purely via PANE_GC_PTY_USED / PANE_GC_PTY_MAX — no fictional
#    `runpane pty ...` subcommand is assumed.
: > "${CALL_LOG}"
PANE_GC_TEST_DOCTOR_STATUS=ok PANE_GC_CURRENT_PANE_ID="pane-current" \
  PANE_GC_PTY_USED=85 PANE_GC_PTY_MAX=100 \
  run_impl "${WORKDIR}/panes_list_full.json" --apply \
  > "${OUT_DIR}/pressure_high.out" 2>&1
if grep -q "PANE_RESTART_REQUIRED" "${OUT_DIR}/pressure_high.out"; then
  pass "PANE_RESTART_REQUIRED emitted when injected PTY pressure >= threshold"
else
  fail "PANE_RESTART_REQUIRED not emitted despite injected PTY pressure >= threshold"
fi
if grep -Eiq "restart|relaunch" "${CALL_LOG}"; then
  fail "script itself attempted a Pane restart/relaunch command"
else
  pass "script never attempts a Pane restart itself"
fi

# Low pressure must NOT emit the token.
: > "${CALL_LOG}"
PANE_GC_TEST_DOCTOR_STATUS=ok PANE_GC_CURRENT_PANE_ID="pane-current" \
  PANE_GC_PTY_USED=10 PANE_GC_PTY_MAX=100 \
  run_impl "${WORKDIR}/panes_list_full.json" --apply \
  > "${OUT_DIR}/pressure_low.out" 2>&1
if grep -q "PANE_RESTART_REQUIRED" "${OUT_DIR}/pressure_low.out"; then
  fail "PANE_RESTART_REQUIRED emitted despite low injected PTY pressure"
else
  pass "PANE_RESTART_REQUIRED correctly withheld under low PTY pressure"
fi

# 7. Event-driven only: source must never invoke crontab/launchctl/at, or
#    contain a sleep-based polling loop.
if grep -Eq "crontab|launchctl|(^|[^_])\bat[[:space:]]+now" "${IMPL}"; then
  fail "implementation appears to install a timer/daemon/scheduled trigger (crontab/launchctl/at)"
else
  pass "implementation contains no timer/daemon scheduling calls"
fi
if grep -Eq "while[[:space:]]+true.*do|sleep[[:space:]]+[0-9]+.*while" "${IMPL}"; then
  fail "implementation appears to run a sleep-based polling loop (not event-driven)"
else
  pass "implementation contains no sleep-based polling loop"
fi

echo ""
echo "== pane_gc_test.sh summary =="
echo "PASS=${PASS} FAIL=${FAIL}"
[[ "${FAIL}" -eq 0 ]]
