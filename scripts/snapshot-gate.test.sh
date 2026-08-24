#!/usr/bin/env bash
# Deterministic shell integration test for scripts/snapshot-gate (SP-01, frozen scenarios 1-7,
# scenario 8 SNAPSHOT_CACHE_RED covers runtime-cache fail-closed hardening across
# subchecks 8a-8d: corrupt cache JSON, cache-file symlink to an outside sentinel,
# a held atomic lock directory, and a dangling cache-file symlink).
#
# CLI contract this test assumes for the not-yet-implemented scripts/snapshot-gate:
#   scripts/snapshot-gate check --manifest <package-manifest.json> [--reseal]
#     - manifest is JSON with required fields: packageId, worktreePath, baseCommit,
#       allowedFiles (array of paths relative to worktreePath)
#     - verifies worktreePath is the exact linked worktree the manifest claims (affinity),
#       that HEAD in that worktree equals baseCommit exactly (no drift), that the git index
#       has no staged changes (no mutation), and that any working-tree
#       diff (tracked or untracked) touches only allowedFiles
#     - on first successful check for a given packageId/worktreePath it seals a deterministic
#       content-hash snapshot; a later check without --reseal that finds the sealed content
#       hash changed is rejected as drift; --reseal re-seals the current state
#     - prints a single verdict token on stdout as the LAST line: PASS,
#       WORKTREE_AFFINITY_DENY, BASE_DRIFT, ALLOWED_FILES_VIOLATION, MULTI_PACKAGE_DIRTY,
#       SNAPSHOT_DRIFT, or GIT_MUTATION_DENY
#     - exit 0 only for verdict PASS; exit 1 for any denial verdict; exit 2 for usage/config
#       errors (missing/malformed manifest, missing required field, not a git worktree, etc.)
#
# scripts/snapshot-gate is implemented; scenarios 1-7 and 8a-8c are expected GREEN and
# subcheck 8d (dangling cache-file symlink) is the new RED target below.

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GATE="$SCRIPT_DIR/snapshot-gate"
SPEED_GATE="$SCRIPT_DIR/speed-gate"
WORK="$(mktemp -d "${TMPDIR:-/tmp}/snapshot-gate-test.XXXXXX")"
trap 'rm -rf "$WORK"' EXIT

# Isolate the runtime cache scenario8 exercises from whatever other cache
# entries this machine's shared TMPDIR may already hold, by pointing TMPDIR at
# a dedicated /runtime subdir created strictly after WORK exists.
RUNTIME="$WORK/runtime"
mkdir -p "$RUNTIME"
export TMPDIR="$RUNTIME"

pass=0
fail=0

report() {
  local name="$1" ok="$2" detail="$3"
  if [ "$ok" -eq 0 ]; then
    pass=$((pass + 1))
    printf 'ok - %s\n' "$name"
  else
    fail=$((fail + 1))
    printf 'not ok - %s: %s\n' "$name" "$detail"
  fi
}

manifest() {
  local file="$1" body="$2"
  printf '%s' "$body" >"$file"
}

# --- Disposable origin repo + linked worktree fixture, isolated under TMPDIR cache ---
ORIGIN="$WORK/origin.git"
git init --quiet "$ORIGIN"
(
  cd "$ORIGIN" || exit 1
  git config user.email "snapshot-gate-test@example.invalid"
  git config user.name "snapshot-gate-test"
  printf 'seed\n' >README.md
  git add README.md
  git commit --quiet -m "seed"
)
BASE_COMMIT=$(git -C "$ORIGIN" rev-parse HEAD)

WT="$WORK/wt-main"
git -C "$ORIGIN" worktree add --quiet "$WT" "$BASE_COMMIT"

ROOT_WT="$ORIGIN"

# --- Scenario 1: physical cwd == manifest worktreePath, but that worktreePath is the
#     primary checkout (not an isolated linked worktree) => WORKTREE_AFFINITY_DENY anyway ---
manifest "$WORK/s1.json" "{
  \"packageId\": \"pkg-affinity\",
  \"worktreePath\": \"$ROOT_WT\",
  \"baseCommit\": \"$BASE_COMMIT\",
  \"allowedFiles\": [\"README.md\"]
}"
out=$(cd "$ROOT_WT" && "$GATE" check --manifest "$WORK/s1.json" 2>"$WORK/s1.err")
status=$?
verdict=$(printf '%s\n' "$out" | tail -n1)
report "scenario1 wrong/root worktree affinity denies" $([ "$verdict" = "WORKTREE_AFFINITY_DENY" ] && [ "$status" -eq 1 ] && echo 0 || echo 1) "got verdict='$verdict' status=$status stderr=$(cat "$WORK/s1.err")"

# --- Scenario 2: HEAD != exact base => BASE_DRIFT ---
(
  cd "$WT" || exit 1
  printf 'drift\n' >>README.md
  git add README.md
  git commit --quiet -m "drift commit"
)
manifest "$WORK/s2.json" "{
  \"packageId\": \"pkg-base-drift\",
  \"worktreePath\": \"$WT\",
  \"baseCommit\": \"$BASE_COMMIT\",
  \"allowedFiles\": [\"scripts/snapshot-gate.test.sh\"]
}"
out=$(cd "$WT" && "$GATE" check --manifest "$WORK/s2.json" 2>"$WORK/s2.err")
status=$?
verdict=$(printf '%s\n' "$out" | tail -n1)
report "scenario2 HEAD past exact base denies BASE_DRIFT" $([ "$verdict" = "BASE_DRIFT" ] && [ "$status" -eq 1 ] && echo 0 || echo 1) "got verdict='$verdict' status=$status stderr=$(cat "$WORK/s2.err")"

(
  cd "$WT" || exit 1
  git reset --quiet --hard "$BASE_COMMIT"
)

# --- Scenario 3: allowed untracked file changes deterministic seal hash and verifies PASS ---
manifest "$WORK/s3.json" "{
  \"packageId\": \"pkg-seal\",
  \"worktreePath\": \"$WT\",
  \"baseCommit\": \"$BASE_COMMIT\",
  \"allowedFiles\": [\"scripts/snapshot-gate.test.sh\"]
}"
mkdir -p "$WT/scripts"
printf 'first content\n' >"$WT/scripts/snapshot-gate.test.sh"
out1=$(cd "$WT" && "$GATE" check --manifest "$WORK/s3.json" 2>"$WORK/s3-seal.err")
status1=$?
verdict1=$(printf '%s\n' "$out1" | tail -n1)
out2=$(cd "$WT" && "$GATE" check --manifest "$WORK/s3.json" 2>"$WORK/s3-verify.err")
status2=$?
verdict2=$(printf '%s\n' "$out2" | tail -n1)
hash1=$(printf '%s\n' "$out1" | grep -o '"snapshotHash":"[^"]*"' | head -n1)
hash2=$(printf '%s\n' "$out2" | grep -o '"snapshotHash":"[^"]*"' | head -n1)
seal_ok=1
if [ "$verdict1" = "PASS" ] && [ "$status1" -eq 0 ] && [ "$verdict2" = "PASS" ] && [ "$status2" -eq 0 ] \
  && [ -n "$hash1" ] && [ "$hash1" = "$hash2" ]; then
  seal_ok=0
fi
report "scenario3 allowed untracked change seals deterministic hash and PASS-verifies" $seal_ok "got verdict1='$verdict1' status1=$status1 hash1='$hash1' verdict2='$verdict2' status2=$status2 hash2='$hash2' stderr1=$(cat "$WORK/s3-seal.err") stderr2=$(cat "$WORK/s3-verify.err")"
rm -f "$WT/scripts/snapshot-gate.test.sh"

# --- Scenario 4: allowedFiles/untracked-name path-safety hardening ---

# 4a: changed/untracked outside allowed => ALLOWED_FILES_VIOLATION (original assertion, kept)
manifest "$WORK/s4.json" "{
  \"packageId\": \"pkg-allowed-violation\",
  \"worktreePath\": \"$WT\",
  \"baseCommit\": \"$BASE_COMMIT\",
  \"allowedFiles\": [\"scripts/snapshot-gate.test.sh\"]
}"
printf 'unexpected\n' >"$WT/NOT_ALLOWED.md"
out=$(cd "$WT" && "$GATE" check --manifest "$WORK/s4.json" 2>"$WORK/s4.err")
status=$?
verdict=$(printf '%s\n' "$out" | tail -n1)
s4a_ok=$([ "$verdict" = "ALLOWED_FILES_VIOLATION" ] && [ "$status" -eq 1 ] && echo 0 || echo 1)
s4a_detail="4a-outside-allowed verdict='$verdict' status=$status stderr=$(cat "$WORK/s4.err")"
rm -f "$WT/NOT_ALLOWED.md"

# 4b: allowedFiles containing a non-string element => usage/config exit 2
manifest "$WORK/s4b.json" "{
  \"packageId\": \"pkg-allowed-nonstring\",
  \"worktreePath\": \"$WT\",
  \"baseCommit\": \"$BASE_COMMIT\",
  \"allowedFiles\": [\"scripts/snapshot-gate.test.sh\", 5]
}"
out=$(cd "$WT" && "$GATE" check --manifest "$WORK/s4b.json" 2>"$WORK/s4b.err")
status=$?
verdict=$(printf '%s\n' "$out" | tail -n1)
s4b_ok=$([ "$status" -eq 2 ] && echo 0 || echo 1)
s4b_detail="4b-allowedFiles-non-string got verdict='$verdict' status=$status stderr=$(cat "$WORK/s4b.err")"

# 4c: allowedFiles containing an absolute or ../-traversal pattern => usage/config exit 2
manifest "$WORK/s4c.json" "{
  \"packageId\": \"pkg-allowed-traversal\",
  \"worktreePath\": \"$WT\",
  \"baseCommit\": \"$BASE_COMMIT\",
  \"allowedFiles\": [\"scripts/snapshot-gate.test.sh\", \"../outside.md\"]
}"
out=$(cd "$WT" && "$GATE" check --manifest "$WORK/s4c.json" 2>"$WORK/s4c.err")
status=$?
verdict=$(printf '%s\n' "$out" | tail -n1)
s4c_ok=$([ "$status" -eq 2 ] && echo 0 || echo 1)
s4c_detail="4c-allowedFiles-traversal got verdict='$verdict' status=$status stderr=$(cat "$WORK/s4c.err")"

manifest "$WORK/s4c2.json" "{
  \"packageId\": \"pkg-allowed-absolute\",
  \"worktreePath\": \"$WT\",
  \"baseCommit\": \"$BASE_COMMIT\",
  \"allowedFiles\": [\"scripts/snapshot-gate.test.sh\", \"/etc/passwd\"]
}"
out=$(cd "$WT" && "$GATE" check --manifest "$WORK/s4c2.json" 2>"$WORK/s4c2.err")
status=$?
verdict=$(printf '%s\n' "$out" | tail -n1)
s4c2_ok=$([ "$status" -eq 2 ] && echo 0 || echo 1)
s4c2_detail="4c2-allowedFiles-absolute got verdict='$verdict' status=$status stderr=$(cat "$WORK/s4c2.err")"

# 4c3: allowedFiles containing a JSON string with a literal embedded
# newline/control character (e.g. "safe\npattern") must fail closed as
# usage/config exit 2 -- never PASS. Line-oriented parsing of the
# allowedFiles array (reading jq -r '.allowedFiles[]' output one line at a
# time) would silently split such an entry into two separate "safe-looking"
# lines and never see the embedded newline to reject it.
manifest "$WORK/s4c3.json" "{
  \"packageId\": \"pkg-allowed-embedded-newline\",
  \"worktreePath\": \"$WT\",
  \"baseCommit\": \"$BASE_COMMIT\",
  \"allowedFiles\": [\"scripts/snapshot-gate.test.sh\", \"safe\npattern\"]
}"
out=$(cd "$WT" && "$GATE" check --manifest "$WORK/s4c3.json" 2>"$WORK/s4c3.err")
status=$?
verdict=$(printf '%s\n' "$out" | tail -n1)
s4c3_ok=$([ "$status" -eq 2 ] && [ "$verdict" != "PASS" ] && echo 0 || echo 1)
s4c3_detail="4c3-allowedFiles-embedded-newline got verdict='$verdict' status=$status stderr=$(cat "$WORK/s4c3.err")"

# 4d: untracked filename containing a literal newline, with a broad allowed
# pattern ("**"), must fail closed with exit 2 -- not PASS, and not silently
# dropped from the snapshot hash by mis-parsing newline-separated file lists.
manifest "$WORK/s4d.json" "{
  \"packageId\": \"pkg-allowed-newline-name\",
  \"worktreePath\": \"$WT\",
  \"baseCommit\": \"$BASE_COMMIT\",
  \"allowedFiles\": [\"**\"]
}"
NEWLINE_NAME=$'evil\nname.txt'
printf 'newline-in-filename\n' >"$WT/$NEWLINE_NAME"
out=$(cd "$WT" && "$GATE" check --manifest "$WORK/s4d.json" 2>"$WORK/s4d.err")
status=$?
verdict=$(printf '%s\n' "$out" | tail -n1)
s4d_ok=$([ "$status" -eq 2 ] && [ "$verdict" != "PASS" ] && echo 0 || echo 1)
s4d_detail="4d-untracked-newline-name-fails-closed got verdict='$verdict' status=$status stderr=$(cat "$WORK/s4d.err")"
find "$WT" -maxdepth 1 -name 'evil*' -exec rm -f {} +

scenario4_ok=1
if [ "$s4a_ok" -eq 0 ] && [ "$s4b_ok" -eq 0 ] && [ "$s4c_ok" -eq 0 ] && [ "$s4c2_ok" -eq 0 ] && [ "$s4c3_ok" -eq 0 ] && [ "$s4d_ok" -eq 0 ]; then
  scenario4_ok=0
fi
report "scenario4 allowedFiles/untracked-name path-safety hardening" $scenario4_ok "$s4a_detail | $s4b_detail | $s4c_detail | $s4c2_detail | $s4c3_detail | $s4d_detail"

# --- Scenario 5: pre-bind pkg-a on a clean worktree, allowed dirty change + --reseal PASSes,
#     THEN a different packageId on that still-dirty worktree => MULTI_PACKAGE_DIRTY ---
manifest "$WORK/s5a.json" "{
  \"packageId\": \"pkg-a\",
  \"worktreePath\": \"$WT\",
  \"baseCommit\": \"$BASE_COMMIT\",
  \"allowedFiles\": [\"scripts/snapshot-gate.test.sh\"]
}"
out=$(cd "$WT" && "$GATE" check --manifest "$WORK/s5a.json" 2>"$WORK/s5a-bind.err")
status=$?
verdict=$(printf '%s\n' "$out" | tail -n1)
s5a_bind_ok=$([ "$verdict" = "PASS" ] && [ "$status" -eq 0 ] && echo 0 || echo 1)
s5a_bind_detail="prereq-bind-pkg-a-clean verdict='$verdict' status=$status stderr=$(cat "$WORK/s5a-bind.err")"

mkdir -p "$WT/scripts"
printf 'owned by pkg-a\n' >"$WT/scripts/snapshot-gate.test.sh"
out=$(cd "$WT" && "$GATE" check --manifest "$WORK/s5a.json" --reseal 2>"$WORK/s5a-reseal.err")
status=$?
verdict=$(printf '%s\n' "$out" | tail -n1)
s5a_reseal_ok=$([ "$verdict" = "PASS" ] && [ "$status" -eq 0 ] && echo 0 || echo 1)
s5a_reseal_detail="prereq-pkg-a-allowed-dirty-reseal verdict='$verdict' status=$status stderr=$(cat "$WORK/s5a-reseal.err")"

manifest "$WORK/s5b.json" "{
  \"packageId\": \"pkg-b\",
  \"worktreePath\": \"$WT\",
  \"baseCommit\": \"$BASE_COMMIT\",
  \"allowedFiles\": [\"scripts/snapshot-gate.test.sh\"]
}"
out=$(cd "$WT" && "$GATE" check --manifest "$WORK/s5b.json" 2>"$WORK/s5b.err")
status=$?
verdict=$(printf '%s\n' "$out" | tail -n1)
s5b_ok=$([ "$verdict" = "MULTI_PACKAGE_DIRTY" ] && [ "$status" -eq 1 ] && echo 0 || echo 1)
scenario5_ok=1
if [ "$s5a_bind_ok" -eq 0 ] && [ "$s5a_reseal_ok" -eq 0 ] && [ "$s5b_ok" -eq 0 ]; then
  scenario5_ok=0
fi
report "scenario5 pre-bound pkg-a clean+reseal PASS then pkg-b denies MULTI_PACKAGE_DIRTY" $scenario5_ok "$s5a_bind_detail | $s5a_reseal_detail | pkg-b verdict='$verdict' status=$status stderr=$(cat "$WORK/s5b.err")"
rm -f "$WT/scripts/snapshot-gate.test.sh"

# --- Scenario 6: pre-bind pkg-drift on a clean worktree, then create+reseal the sealed
#     content, THEN a further byte change without --reseal => SNAPSHOT_DRIFT ---
manifest "$WORK/s6.json" "{
  \"packageId\": \"pkg-drift\",
  \"worktreePath\": \"$WT\",
  \"baseCommit\": \"$BASE_COMMIT\",
  \"allowedFiles\": [\"scripts/snapshot-gate.test.sh\"]
}"
out=$(cd "$WT" && "$GATE" check --manifest "$WORK/s6.json" 2>"$WORK/s6-bind.err")
status=$?
verdict=$(printf '%s\n' "$out" | tail -n1)
s6_bind_ok=$([ "$verdict" = "PASS" ] && [ "$status" -eq 0 ] && echo 0 || echo 1)
s6_bind_detail="prereq-bind-pkg-drift-clean verdict='$verdict' status=$status stderr=$(cat "$WORK/s6-bind.err")"

mkdir -p "$WT/scripts"
printf 'sealed content\n' >"$WT/scripts/snapshot-gate.test.sh"
out=$(cd "$WT" && "$GATE" check --manifest "$WORK/s6.json" --reseal 2>"$WORK/s6-seal.err")
status=$?
verdict=$(printf '%s\n' "$out" | tail -n1)
s6_seal_ok=$([ "$verdict" = "PASS" ] && [ "$status" -eq 0 ] && echo 0 || echo 1)
s6_seal_detail="prereq-seal-content verdict='$verdict' status=$status stderr=$(cat "$WORK/s6-seal.err")"

printf 'sealed content, one more byte\n' >"$WT/scripts/snapshot-gate.test.sh"
out=$(cd "$WT" && "$GATE" check --manifest "$WORK/s6.json" 2>"$WORK/s6-drift.err")
status=$?
verdict=$(printf '%s\n' "$out" | tail -n1)
s6_drift_ok=$([ "$verdict" = "SNAPSHOT_DRIFT" ] && [ "$status" -eq 1 ] && echo 0 || echo 1)
scenario6_ok=1
if [ "$s6_bind_ok" -eq 0 ] && [ "$s6_seal_ok" -eq 0 ] && [ "$s6_drift_ok" -eq 0 ]; then
  scenario6_ok=0
fi
report "scenario6 pre-bound+resealed pkg-drift then byte change without --reseal denies SNAPSHOT_DRIFT" $scenario6_ok "$s6_bind_detail | $s6_seal_detail | drift verdict='$verdict' status=$status stderr=$(cat "$WORK/s6-drift.err")"

out=$(cd "$WT" && "$GATE" check --manifest "$WORK/s6.json" --reseal 2>"$WORK/s6-reseal.err")
status=$?
verdict=$(printf '%s\n' "$out" | tail -n1)
report "scenario6b --reseal accepts the drifted byte and PASSes" $([ "$verdict" = "PASS" ] && [ "$status" -eq 0 ] && echo 0 || echo 1) "got verdict='$verdict' status=$status stderr=$(cat "$WORK/s6-reseal.err")"
rm -f "$WT/scripts/snapshot-gate.test.sh"

# --- Scenario 7: pre-bind pkg-index on a clean worktree, THEN staging a change into the
#     git index => GIT_MUTATION_DENY ---
manifest "$WORK/s7.json" "{
  \"packageId\": \"pkg-index\",
  \"worktreePath\": \"$WT\",
  \"baseCommit\": \"$BASE_COMMIT\",
  \"allowedFiles\": [\"scripts/snapshot-gate.test.sh\"]
}"
out=$(cd "$WT" && "$GATE" check --manifest "$WORK/s7.json" 2>"$WORK/s7-bind.err")
status=$?
verdict=$(printf '%s\n' "$out" | tail -n1)
s7_bind_ok=$([ "$verdict" = "PASS" ] && [ "$status" -eq 0 ] && echo 0 || echo 1)
s7_bind_detail="prereq-bind-pkg-index-clean verdict='$verdict' status=$status stderr=$(cat "$WORK/s7-bind.err")"

mkdir -p "$WT/scripts"
printf 'staged content\n' >"$WT/scripts/snapshot-gate.test.sh"
(
  cd "$WT" || exit 1
  git add scripts/snapshot-gate.test.sh
)
out=$(cd "$WT" && "$GATE" check --manifest "$WORK/s7.json" 2>"$WORK/s7.err")
status=$?
verdict=$(printf '%s\n' "$out" | tail -n1)
s7_stage_ok=$([ "$verdict" = "GIT_MUTATION_DENY" ] && [ "$status" -eq 1 ] && echo 0 || echo 1)
scenario7_ok=1
if [ "$s7_bind_ok" -eq 0 ] && [ "$s7_stage_ok" -eq 0 ]; then
  scenario7_ok=0
fi
report "scenario7 pre-bound pkg-index clean then staged index change denies GIT_MUTATION_DENY" $scenario7_ok "$s7_bind_detail | staged verdict='$verdict' status=$status stderr=$(cat "$WORK/s7.err")"
(
  cd "$WT" || exit 1
  git reset --quiet
)
rm -f "$WT/scripts/snapshot-gate.test.sh"

git -C "$ORIGIN" worktree remove --force "$WT" 2>/dev/null || true

# --- Scenario 8: runtime cache fail-closed on a fresh clean linked worktree ---
# The cache file the gate reads/writes is keyed by sha256 of the worktree's
# *physical* (symlink-resolved) toplevel path under
# "${TMPDIR}/zabuno-snapshot-gate-cache/<sha256>.json" -- TMPDIR was pointed
# at the isolated $RUNTIME dir above so this scenario never touches any other
# concurrent test run's cache entries.

WT8="$WORK/wt-scenario8"
git -C "$ORIGIN" worktree add --quiet "$WT8" "$BASE_COMMIT"
WT8_PHYS=$(cd "$WT8" && pwd -P)
CACHE_ROOT="$TMPDIR/zabuno-snapshot-gate-cache"
CACHE_KEY=$(printf '%s' "$WT8_PHYS" | shasum -a 256 | awk '{print $1}')
CACHE_FILE="$CACHE_ROOT/$CACHE_KEY.json"

manifest "$WORK/s8.json" "{
  \"packageId\": \"pkg-cache-fail-closed\",
  \"worktreePath\": \"$WT8\",
  \"baseCommit\": \"$BASE_COMMIT\",
  \"allowedFiles\": [\"scripts/snapshot-gate.test.sh\"]
}"

# 8a: cache file preholds invalid JSON -- the gate must fail closed (exit 2,
# never PASS) rather than silently treating malformed cache content as "no
# prior binding" and clobbering it.
rm -rf "$CACHE_ROOT"
mkdir -p "$CACHE_ROOT"
printf 'not valid json {' >"$CACHE_FILE"
before_8a=$(cat "$CACHE_FILE")
out=$(cd "$WT8" && "$GATE" check --manifest "$WORK/s8.json" 2>"$WORK/s8a.err")
status=$?
verdict=$(printf '%s\n' "$out" | tail -n1)
after_8a=$(cat "$CACHE_FILE" 2>/dev/null || echo "<missing>")
s8a_ok=$([ "$status" -eq 2 ] && [ "$verdict" != "PASS" ] && [ "$after_8a" = "$before_8a" ] && echo 0 || echo 1)
s8a_detail="8a-corrupt-cache-json got verdict='$verdict' status=$status cache_unchanged=$([ "$after_8a" = "$before_8a" ] && echo yes || echo no) stderr=$(cat "$WORK/s8a.err")"
rm -rf "$CACHE_ROOT"

# 8b: cache file preholds a symlink to a sentinel target outside the cache
# root -- the gate must fail closed and must not follow/replace/retarget the
# symlink (a bare `mv` onto a symlink unlinks and retargets it).
SENTINEL="$WORK/s8b-sentinel.json"
printf '{"packageId":"sentinel-owner","snapshotHash":"sentinel-hash"}' >"$SENTINEL"
mkdir -p "$CACHE_ROOT"
ln -s "$SENTINEL" "$CACHE_FILE"
before_8b_link=$(readlink "$CACHE_FILE")
before_8b_target=$(cat "$SENTINEL")
out=$(cd "$WT8" && "$GATE" check --manifest "$WORK/s8.json" 2>"$WORK/s8b.err")
status=$?
verdict=$(printf '%s\n' "$out" | tail -n1)
after_8b_link=$(readlink "$CACHE_FILE" 2>/dev/null || echo "<not-a-symlink>")
after_8b_target=$(cat "$SENTINEL" 2>/dev/null || echo "<missing>")
s8b_ok=$([ "$status" -eq 2 ] && [ "$verdict" != "PASS" ] \
  && [ "$after_8b_link" = "$before_8b_link" ] && [ "$after_8b_target" = "$before_8b_target" ] \
  && echo 0 || echo 1)
s8b_detail="8b-cache-symlink-sentinel got verdict='$verdict' status=$status link_unchanged=$([ "$after_8b_link" = "$before_8b_link" ] && echo yes || echo no) target_unchanged=$([ "$after_8b_target" = "$before_8b_target" ] && echo yes || echo no) stderr=$(cat "$WORK/s8b.err")"
rm -rf "$CACHE_ROOT"
rm -f "$SENTINEL"

# 8c: an atomic mkdir-based lock directory next to the cache file is already
# held (as if by a concurrent gate invocation) -- this check must honor that
# lock and fail closed (exit 2, never PASS) rather than proceeding past it.
mkdir -p "$CACHE_ROOT"
LOCK_DIR="${CACHE_FILE}.lock"
mkdir "$LOCK_DIR"
if [ -d "$LOCK_DIR" ]; then
  lock_precondition_ok=1
else
  lock_precondition_ok=0
fi
out=$(cd "$WT8" && "$GATE" check --manifest "$WORK/s8.json" 2>"$WORK/s8c.err")
status=$?
verdict=$(printf '%s\n' "$out" | tail -n1)
s8c_ok=$([ "$lock_precondition_ok" -eq 1 ] && [ "$status" -eq 2 ] && [ "$verdict" != "PASS" ] && echo 0 || echo 1)
s8c_detail="8c-atomic-lock-held got verdict='$verdict' status=$status lock_precondition=$([ "$lock_precondition_ok" -eq 1 ] && echo yes || echo no) stderr=$(cat "$WORK/s8c.err")"
rmdir "$LOCK_DIR" 2>/dev/null || true
rm -rf "$CACHE_ROOT"

# 8d: cache file preholds a dangling symlink (its target path does not
# exist) -- the gate must fail closed (exit 2, never PASS) rather than
# following/creating/replacing the dangling symlink's target or the
# symlink itself.
DANGLING_TARGET="$WORK/s8d-missing-target.json"
mkdir -p "$CACHE_ROOT"
ln -s "$DANGLING_TARGET" "$CACHE_FILE"
before_8d_link=$(readlink "$CACHE_FILE")
out=$(cd "$WT8" && "$GATE" check --manifest "$WORK/s8.json" 2>"$WORK/s8d.err")
status=$?
verdict=$(printf '%s\n' "$out" | tail -n1)
after_8d_link=$(readlink "$CACHE_FILE" 2>/dev/null || echo "<not-a-symlink>")
s8d_ok=$([ "$status" -eq 2 ] && [ "$verdict" != "PASS" ] \
  && [ "$after_8d_link" = "$before_8d_link" ] && [ ! -e "$DANGLING_TARGET" ] \
  && echo 0 || echo 1)
s8d_detail="8d-cache-dangling-symlink got verdict='$verdict' status=$status link_unchanged=$([ "$after_8d_link" = "$before_8d_link" ] && echo yes || echo no) target_still_missing=$([ ! -e "$DANGLING_TARGET" ] && echo yes || echo no) stderr=$(cat "$WORK/s8d.err")"
rm -rf "$CACHE_ROOT"

scenario8_ok=1
if [ "$s8a_ok" -eq 0 ] && [ "$s8b_ok" -eq 0 ] && [ "$s8c_ok" -eq 0 ] && [ "$s8d_ok" -eq 0 ]; then
  scenario8_ok=0
fi
report "scenario8 runtime cache fail-closed (corrupt json / symlink sentinel / held lock / dangling symlink)" $scenario8_ok "$s8a_detail | $s8b_detail | $s8c_detail | $s8d_detail"

git -C "$ORIGIN" worktree remove --force "$WT8" 2>/dev/null || true

# --- Speed-gate self-check: this package as a normal-lane, 7-targeted-test, 1-file package ---
if [ -x "$SPEED_GATE" ]; then
  SPEED_WORK="$WORK/speed-gate-manifest"
  mkdir -p "$SPEED_WORK"
  cat >"$SPEED_WORK/development-speed-budget.json" <<'JSON'
{
  "checkpointCadenceMinutesMax": 20,
  "lanes": {
    "normal": { "targetedTestMax": 8, "targetedTestMin": 3, "testFilesMax": 2, "reviewerFullSuiteRunsMax": 0 }
  },
  "highRiskPathPatterns": [],
  "highRiskSemanticClasses": []
}
JSON
  cat >"$SPEED_WORK/package-manifest.json" <<'JSON'
{
  "lane": "normal",
  "targetedTestCount": 8,
  "testFilesChanged": 1,
  "changedPaths": ["scripts/snapshot-gate.test.sh"],
  "elapsedCheckpointMinutes": 12,
  "snapshotHash": "snapshot-gate-red-package"
}
JSON
  out=$("$SPEED_GATE" check --manifest "$SPEED_WORK/package-manifest.json" --config "$SPEED_WORK/development-speed-budget.json" 2>"$SPEED_WORK/speed-gate.err")
  status=$?
  verdict=$(printf '%s\n' "$out" | tail -n1)
  report "speed-gate normal lane 8 targeted tests / 1 file PASSes" $([ "$verdict" = "PASS" ] && [ "$status" -eq 0 ] && echo 0 || echo 1) "got verdict='$verdict' status=$status stderr=$(cat "$SPEED_WORK/speed-gate.err")"
else
  report "speed-gate normal lane 8 targeted tests / 1 file PASSes" 1 "scripts/speed-gate not found or not executable at $SPEED_GATE"
fi

printf '\n%d passed, %d failed\n' "$pass" "$fail"
[ "$fail" -eq 0 ]
