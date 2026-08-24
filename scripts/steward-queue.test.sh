#!/usr/bin/env bash
# Deterministic shell integration test for scripts/steward-queue
# (fast-delivery genome overlay item 7, frozen scenarios 1-8).
#
# CLI contract this test assumes for the not-yet-implemented
# scripts/steward-queue:
#
#   scripts/steward-queue plan --manifests <dir> --intake <intake.json> \
#     --config <development-speed-budget.json>
#
#     - intake JSON shape is exactly {"entries":[{"packageId":"...",
#       "sequence":0}, ...]}: exact keys, nonempty entries, packageIds match
#       the canonical identifier grammar, unique packageIds, unique
#       nonnegative integer sequences, and the intake packageId set must
#       exactly equal the package set produced by
#       scripts/conflict-scheduler plan --manifests <dir> --config <config>
#       (that call must itself succeed as SCHEDULE_PLAN/0; any other
#       verdict/exit is invalid)
#     - config must carry moduleFramework.stewardLanes.lanes, nonempty, and
#       every lane must have concurrentActivePackages == 1,
#       queueDiscipline == "FIFO", and a nonempty coversFiles array with no
#       duplicate entries -- any drift from that is invalid
#     - each manifest's sharedSurfacesTouched names the steward lane(s) that
#       manifest touches; an unknown lane name is invalid
#     - prerequisites for a package are the union of (a) its manifest's
#       in-batch dependsOnPackages and (b) for every lane it touches, the
#       immediate FIFO predecessor in that lane's intake-sequence-ordered
#       queue (the package with the next-lower intake sequence among
#       packages touching that lane; none for the earliest)
#     - a cycle in the combined prerequisite graph (dependency edges plus
#       FIFO lane-predecessor edges) is invalid
#     - waves are recomputed from scratch (not merely a deferral of the
#       conflict-scheduler's own waves) using conflictEdges (from
#       conflict-scheduler's plan) union prerequisites as the combined
#       blocking relation: a package is eligible once every prerequisite is
#       scheduled; eligible packages are scanned in ascending intake
#       sequence order (ties broken by packageId) and accepted into the
#       current wave unless it is an exact conflictEdges endpoint already
#       accepted this wave, or it touches a steward lane already touched by
#       a package already accepted this wave; packages in different lanes
#       (or no lane) may share a wave freely; a multi-lane package couples
#       only the lanes it actually touches
#     - pure printer: no Git mutation, no queue/admission side effects
#     - fails STEWARD_PLAN_INVALID/nonzero (no plan JSON on stdout) for:
#       unknown args, malformed/missing intake, intake/package-set mismatch,
#       duplicate/noninteger/negative sequence, unknown lane name, lane
#       config drift (concurrency != 1, discipline != FIFO, empty/duplicate
#       coversFiles), missing/empty stewardLanes.lanes, a conflict-scheduler
#       failure, or a prerequisite cycle
#     - on success, prints exactly one compact deterministic JSON object on
#       stdout, followed by the verdict token STEWARD_PLAN on the last
#       line, exit 0. The JSON includes:
#         packages       -- lexically sorted packageIds
#         intake         -- the parsed intake entries, sorted by sequence
#         laneQueues     -- object keyed by lane name -> packageIds ordered
#                            by intake sequence (FIFO), for lanes that have
#                            at least one touching package in the batch
#         prerequisites  -- object keyed by packageId -> sorted array of
#                            prerequisite packageIds (dependency union FIFO
#                            predecessor)
#         conflictEdges  -- conflict-scheduler's normalized conflict edges
#         waves          -- greedy deterministic array-of-arrays,
#                            recomputed as described above, each wave's
#                            packageIds lexically sorted
#     - identical logical input (manifests / intake entries) provided in a
#       different filesystem/file-listing/JSON-key order produces a
#       byte-identical plan, with laneQueues/prerequisites/waves all in
#       their documented sorted/deterministic form
#
# No implementation exists yet at scripts/steward-queue: every scenario
# below asserts the REAL target behavior (verdict token, exit code, and
# specific jq-extracted plan keys), so every scenario must currently fail
# (RED) because the steward-queue binary itself is absent -- not because of
# a shell syntax error in this test. This test does NOT assert brittle
# full-JSON snapshots; it asserts exact token/exit/key invariants.

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
STEWARD="$SCRIPT_DIR/steward-queue"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
SPEED_BUDGET_CONFIG="$REPO_ROOT/config/development-speed-budget.json"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

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

HASH_A="00000000000000000000000000000000000000000000000000000000000000aa"
HASH_B="00000000000000000000000000000000000000000000000000000000000000bb"

for _h_name in HASH_A HASH_B; do
  _h_val="${!_h_name}"
  if [ "${#_h_val}" -ne 64 ] || ! printf '%s' "$_h_val" | grep -Eq '^[0-9a-f]{64}$'; then
    printf 'not ok - fixture %s is not exactly 64 lowercase hex chars (len=%s)\n' "$_h_name" "${#_h_val}" >&2
    exit 1
  fi
done

# pkg <id> <module> <file> <deps-json> <lanes-json>
pkg() {
  local id="$1" module="$2" file="$3" deps="$4" lanes="$5"
  cat <<JSON
{
  "packageId": "$id",
  "module": "$module",
  "allowedFiles": ["$file"],
  "writeSet": ["$file"],
  "readSet": [],
  "contractsConsumed": [],
  "contractsChanged": [],
  "tablesOrMigrationsTouched": [],
  "sharedSurfacesTouched": $lanes,
  "dependsOnPackages": $deps
}
JSON
}

intake_file() {
  local file="$1"
  shift
  local entries="$1"
  printf '{"entries":%s}\n' "$entries" >"$file"
}

# run_plan <manifests-dir> <intake-file> [<config-file>]
run_plan() {
  local dir="$1" intake="$2" cfg="${3:-$SPEED_BUDGET_CONFIG}"
  local errfile="$WORK/last.err"
  out=$("$STEWARD" plan --manifests "$dir" --intake "$intake" --config "$cfg" 2>"$errfile")
  ec=$?
  verdict=$(printf '%s\n' "$out" | tail -n1)
  json=$(printf '%s\n' "$out" | sed '$d')
  errtext=$(cat "$errfile")
}

# ============================================================================
# Scenario 1: two packages in the same routes lane, given reverse-lexical
# packageIds but ascending intake sequence, must obey FIFO by sequence (not
# by packageId) -- appearing in separate waves in sequence order, and in
# that same order inside laneQueues.routes.
# ============================================================================
mkdir -p "$WORK/s1"
manifest "$WORK/s1/pkg-z.json" "$(pkg pkg-routes-z MenuCatalog routes/api.php '[]' '["routes"]')"
manifest "$WORK/s1/pkg-a.json" "$(pkg pkg-routes-a MenuCatalog routes/api.php '[]' '["routes"]')"
intake_file "$WORK/s1-intake.json" '[{"packageId":"pkg-routes-z","sequence":0},{"packageId":"pkg-routes-a","sequence":1}]'
run_plan "$WORK/s1" "$WORK/s1-intake.json"
s1_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "STEWARD_PLAN" ] && command -v jq >/dev/null 2>&1; then
  lane_queue=$(printf '%s' "$json" | jq -c '.laneQueues.routes // []' 2>/dev/null)
  z_wave=$(printf '%s' "$json" | jq -r '.waves | to_entries[] | select(.value | index("pkg-routes-z") != null) | .key' 2>/dev/null)
  a_wave=$(printf '%s' "$json" | jq -r '.waves | to_entries[] | select(.value | index("pkg-routes-a") != null) | .key' 2>/dev/null)
  if [ "$lane_queue" = '["pkg-routes-z","pkg-routes-a"]' ] \
    && [ -n "$z_wave" ] && [ -n "$a_wave" ] && [ "$z_wave" -lt "$a_wave" ] 2>/dev/null; then
    s1_ok=0
  fi
fi
report "scenario1 same-lane FIFO obeys intake sequence, not lexical packageId order" $s1_ok "expected STEWARD_PLAN with laneQueues.routes=[pkg-routes-z,pkg-routes-a] and pkg-routes-z scheduled strictly before pkg-routes-a; got ec=$ec verdict='$verdict' json=$json stderr=$errtext"

# ============================================================================
# Scenario 2: three packages in disjoint lanes (routes, i18n) plus a package
# touching no lane at all share wave 0.
# ============================================================================
mkdir -p "$WORK/s2"
manifest "$WORK/s2/pkg-routes.json" "$(pkg pkg-disj-routes MenuCatalog routes/api.php '[]' '["routes"]')"
manifest "$WORK/s2/pkg-i18n.json" "$(pkg pkg-disj-i18n MenuCatalog resources/js/i18n/workspace.ts '[]' '["i18n"]')"
manifest "$WORK/s2/pkg-none.json" "$(pkg pkg-disj-none MenuCatalog app/MenuCatalog/NoLane.php '[]' '[]')"
intake_file "$WORK/s2-intake.json" '[{"packageId":"pkg-disj-routes","sequence":0},{"packageId":"pkg-disj-i18n","sequence":1},{"packageId":"pkg-disj-none","sequence":2}]'
run_plan "$WORK/s2" "$WORK/s2-intake.json"
s2_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "STEWARD_PLAN" ] && command -v jq >/dev/null 2>&1; then
  wave0=$(printf '%s' "$json" | jq -c '.waves[0] // [] | sort' 2>/dev/null)
  if [ "$wave0" = '["pkg-disj-i18n","pkg-disj-none","pkg-disj-routes"]' ]; then
    s2_ok=0
  fi
fi
report "scenario2 disjoint lanes and no-lane package share wave 0" $s2_ok "expected STEWARD_PLAN with wave 0 == all three packages; got ec=$ec verdict='$verdict' json=$json stderr=$errtext"

# ============================================================================
# Scenario 3: a multi-lane package (routes + i18n) blocks a later routes
# entry and a later i18n entry (they must wait for it), but a concurrent
# ci-lane package remains unaffected and shares the multi-lane package's
# wave.
# ============================================================================
mkdir -p "$WORK/s3"
manifest "$WORK/s3/pkg-multi.json" "$(pkg pkg-multi-lane MenuCatalog routes/api.php '[]' '["routes","i18n"]')"
manifest "$WORK/s3/pkg-routes2.json" "$(pkg pkg-routes-second MenuCatalog routes/api.php '[]' '["routes"]')"
manifest "$WORK/s3/pkg-i18n2.json" "$(pkg pkg-i18n-second MenuCatalog resources/js/i18n/workspace.ts '[]' '["i18n"]')"
manifest "$WORK/s3/pkg-ci.json" "$(pkg pkg-ci-indep MenuCatalog .github/workflows/ci.yml '[]' '["ci"]')"
intake_file "$WORK/s3-intake.json" '[{"packageId":"pkg-multi-lane","sequence":0},{"packageId":"pkg-routes-second","sequence":1},{"packageId":"pkg-i18n-second","sequence":2},{"packageId":"pkg-ci-indep","sequence":3}]'
run_plan "$WORK/s3" "$WORK/s3-intake.json"
s3_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "STEWARD_PLAN" ] && command -v jq >/dev/null 2>&1; then
  multi_wave=$(printf '%s' "$json" | jq -r '.waves | to_entries[] | select(.value | index("pkg-multi-lane") != null) | .key' 2>/dev/null)
  routes2_wave=$(printf '%s' "$json" | jq -r '.waves | to_entries[] | select(.value | index("pkg-routes-second") != null) | .key' 2>/dev/null)
  i18n2_wave=$(printf '%s' "$json" | jq -r '.waves | to_entries[] | select(.value | index("pkg-i18n-second") != null) | .key' 2>/dev/null)
  ci_wave=$(printf '%s' "$json" | jq -r '.waves | to_entries[] | select(.value | index("pkg-ci-indep") != null) | .key' 2>/dev/null)
  if [ -n "$multi_wave" ] && [ -n "$routes2_wave" ] && [ -n "$i18n2_wave" ] && [ -n "$ci_wave" ] \
    && [ "$multi_wave" -lt "$routes2_wave" ] 2>/dev/null && [ "$multi_wave" -lt "$i18n2_wave" ] 2>/dev/null \
    && [ "$multi_wave" = "$ci_wave" ]; then
    s3_ok=0
  fi
fi
report "scenario3 multi-lane package blocks later routes/i18n entries but not independent ci package" $s3_ok "expected STEWARD_PLAN with pkg-multi-lane strictly before pkg-routes-second and pkg-i18n-second, while pkg-ci-indep shares pkg-multi-lane's wave; got ec=$ec verdict='$verdict' json=$json stderr=$errtext"

# ============================================================================
# Scenario 4: manifest dependsOnPackages and lane-FIFO prerequisites are
# both honored together in the recomputed order -- C depends on B (manifest
# DAG) and also follows A in the same lane via FIFO, but B and A are in
# different lanes with no manifest relation to each other, so B/A may
# coexist in wave 0 while C waits for both.
# ============================================================================
mkdir -p "$WORK/s4"
manifest "$WORK/s4/pkg-a.json" "$(pkg pkg-combo-a MenuCatalog routes/api.php '[]' '["routes"]')"
manifest "$WORK/s4/pkg-b.json" "$(pkg pkg-combo-b MenuCatalog resources/js/i18n/workspace.ts '[]' '["i18n"]')"
manifest "$WORK/s4/pkg-c.json" "$(pkg pkg-combo-c MenuCatalog routes/api.php '["pkg-combo-b"]' '["routes"]')"
intake_file "$WORK/s4-intake.json" '[{"packageId":"pkg-combo-a","sequence":0},{"packageId":"pkg-combo-b","sequence":1},{"packageId":"pkg-combo-c","sequence":2}]'
run_plan "$WORK/s4" "$WORK/s4-intake.json"
s4_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "STEWARD_PLAN" ] && command -v jq >/dev/null 2>&1; then
  c_prereqs=$(printf '%s' "$json" | jq -c '.prerequisites["pkg-combo-c"] // [] | sort' 2>/dev/null)
  a_wave=$(printf '%s' "$json" | jq -r '.waves | to_entries[] | select(.value | index("pkg-combo-a") != null) | .key' 2>/dev/null)
  b_wave=$(printf '%s' "$json" | jq -r '.waves | to_entries[] | select(.value | index("pkg-combo-b") != null) | .key' 2>/dev/null)
  c_wave=$(printf '%s' "$json" | jq -r '.waves | to_entries[] | select(.value | index("pkg-combo-c") != null) | .key' 2>/dev/null)
  if [ "$c_prereqs" = '["pkg-combo-a","pkg-combo-b"]' ] \
    && [ -n "$a_wave" ] && [ -n "$b_wave" ] && [ -n "$c_wave" ] \
    && [ "$a_wave" = "$b_wave" ] \
    && [ "$c_wave" -gt "$a_wave" ] 2>/dev/null && [ "$c_wave" -gt "$b_wave" ] 2>/dev/null; then
    s4_ok=0
  fi
fi
report "scenario4 manifest dependency and FIFO lane prerequisite are both honored" $s4_ok "expected STEWARD_PLAN with pkg-combo-c prerequisites == [pkg-combo-a,pkg-combo-b], pkg-combo-a and pkg-combo-b sharing a wave, and pkg-combo-c strictly later; got ec=$ec verdict='$verdict' json=$json stderr=$errtext"

# ============================================================================
# Scenario 5: two packages in different lanes (routes, i18n) that also have
# an exact write/write conflict (conflict-scheduler conflictEdges) must
# still be separated into different waves, proving lane-FIFO and
# conflictEdges compose rather than one overriding the other.
# ============================================================================
mkdir -p "$WORK/s5"
manifest "$WORK/s5/pkg-a.json" "$(pkg pkg-xconf-a MenuCatalog app/Shared/Same.php '[]' '["routes"]')"
manifest "$WORK/s5/pkg-b.json" "$(pkg pkg-xconf-b MenuCatalog app/Shared/Same.php '[]' '["i18n"]')"
intake_file "$WORK/s5-intake.json" '[{"packageId":"pkg-xconf-a","sequence":0},{"packageId":"pkg-xconf-b","sequence":1}]'
run_plan "$WORK/s5" "$WORK/s5-intake.json"
s5_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "STEWARD_PLAN" ] && command -v jq >/dev/null 2>&1; then
  a_wave=$(printf '%s' "$json" | jq -r '.waves | to_entries[] | select(.value | index("pkg-xconf-a") != null) | .key' 2>/dev/null)
  b_wave=$(printf '%s' "$json" | jq -r '.waves | to_entries[] | select(.value | index("pkg-xconf-b") != null) | .key' 2>/dev/null)
  has_edge=$(printf '%s' "$json" | jq -r '[.conflictEdges[] | select(.packageIds == ["pkg-xconf-a","pkg-xconf-b"])] | length' 2>/dev/null)
  if [ -n "$a_wave" ] && [ -n "$b_wave" ] && [ "$a_wave" != "$b_wave" ] && [ "$has_edge" = "1" ]; then
    s5_ok=0
  fi
fi
# Scenario 5b (same top-level scenario 5): packageId-concatenation collision
# guard. Sorted pair [a,bc] and sorted pair [ab,c] both naively concatenate
# to "abc" if the conflict-map key does not preserve a collision-safe
# boundary between the two packageIds. a and bc write the same file (the
# sole intended conflict pair); ab and c write distinct unique files and
# must NOT be treated as conflicting with each other or confused with the
# [a,bc] pair.
mkdir -p "$WORK/s5b"
manifest "$WORK/s5b/pkg-a.json" "$(pkg a MenuCatalog app/Shared/Collide.php '[]' '[]')"
manifest "$WORK/s5b/pkg-bc.json" "$(pkg bc MenuCatalog app/Shared/Collide.php '[]' '[]')"
manifest "$WORK/s5b/pkg-ab.json" "$(pkg ab MenuCatalog app/Shared/UniqueAb.php '[]' '[]')"
manifest "$WORK/s5b/pkg-c.json" "$(pkg c MenuCatalog app/Shared/UniqueC.php '[]' '[]')"
intake_file "$WORK/s5b-intake.json" '[{"packageId":"a","sequence":0},{"packageId":"bc","sequence":1},{"packageId":"ab","sequence":2},{"packageId":"c","sequence":3}]'
run_plan "$WORK/s5b" "$WORK/s5b-intake.json"
s5b_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "STEWARD_PLAN" ] && command -v jq >/dev/null 2>&1; then
  a_wave=$(printf '%s' "$json" | jq -r '.waves | to_entries[] | select(.value | index("a") != null) | .key' 2>/dev/null)
  bc_wave=$(printf '%s' "$json" | jq -r '.waves | to_entries[] | select(.value | index("bc") != null) | .key' 2>/dev/null)
  ab_wave=$(printf '%s' "$json" | jq -r '.waves | to_entries[] | select(.value | index("ab") != null) | .key' 2>/dev/null)
  c_wave=$(printf '%s' "$json" | jq -r '.waves | to_entries[] | select(.value | index("c") != null) | .key' 2>/dev/null)
  has_ab_edge=$(printf '%s' "$json" | jq -r '[.conflictEdges[] | select(.packageIds == ["a","bc"])] | length' 2>/dev/null)
  if [ -n "$a_wave" ] && [ -n "$bc_wave" ] && [ -n "$ab_wave" ] && [ -n "$c_wave" ] \
    && [ "$a_wave" != "$bc_wave" ] && [ "$has_ab_edge" = "1" ] && [ "$ab_wave" = "$c_wave" ]; then
    s5b_ok=0
  fi
fi
s5_ok=$((s5_ok + s5b_ok))
[ "$s5_ok" -gt 0 ] && s5_ok=1
report "scenario5 different-lane packages with exact write/write conflict still separate" $s5_ok "expected STEWARD_PLAN with pkg-xconf-a and pkg-xconf-b in different waves and a conflictEdges entry between them; also expected packageId-concat collision guard: a/bc separated with conflictEdges=[a,bc] while ab/c share a wave; got ec=$ec verdict='$verdict' json=$json stderr=$errtext"

# ============================================================================
# Scenario 6: invalid intake shapes are all STEWARD_PLAN_INVALID/nonzero:
# missing an entry for a batch package, an extra entry for a package not in
# the batch, a duplicate packageId, and a duplicate/noninteger/negative
# sequence.
# ============================================================================
mkdir -p "$WORK/s6"
manifest "$WORK/s6/pkg-a.json" "$(pkg pkg-badintake-a MenuCatalog routes/api.php '[]' '["routes"]')"
manifest "$WORK/s6/pkg-b.json" "$(pkg pkg-badintake-b MenuCatalog resources/js/i18n/workspace.ts '[]' '["i18n"]')"

intake_file "$WORK/s6-missing.json" '[{"packageId":"pkg-badintake-a","sequence":0}]'
run_plan "$WORK/s6" "$WORK/s6-missing.json"
s6a_ok=$([ "$ec" -ne 0 ] && [ "$verdict" = "STEWARD_PLAN_INVALID" ] && echo 0 || echo 1)

intake_file "$WORK/s6-extra.json" '[{"packageId":"pkg-badintake-a","sequence":0},{"packageId":"pkg-badintake-b","sequence":1},{"packageId":"pkg-badintake-ghost","sequence":2}]'
run_plan "$WORK/s6" "$WORK/s6-extra.json"
s6b_ok=$([ "$ec" -ne 0 ] && [ "$verdict" = "STEWARD_PLAN_INVALID" ] && echo 0 || echo 1)

intake_file "$WORK/s6-dupid.json" '[{"packageId":"pkg-badintake-a","sequence":0},{"packageId":"pkg-badintake-a","sequence":1}]'
run_plan "$WORK/s6" "$WORK/s6-dupid.json"
s6c_ok=$([ "$ec" -ne 0 ] && [ "$verdict" = "STEWARD_PLAN_INVALID" ] && echo 0 || echo 1)

intake_file "$WORK/s6-dupseq.json" '[{"packageId":"pkg-badintake-a","sequence":0},{"packageId":"pkg-badintake-b","sequence":0}]'
run_plan "$WORK/s6" "$WORK/s6-dupseq.json"
s6d_ok=$([ "$ec" -ne 0 ] && [ "$verdict" = "STEWARD_PLAN_INVALID" ] && echo 0 || echo 1)

intake_file "$WORK/s6-nonint.json" '[{"packageId":"pkg-badintake-a","sequence":0.5},{"packageId":"pkg-badintake-b","sequence":1}]'
run_plan "$WORK/s6" "$WORK/s6-nonint.json"
s6e_ok=$([ "$ec" -ne 0 ] && [ "$verdict" = "STEWARD_PLAN_INVALID" ] && echo 0 || echo 1)

intake_file "$WORK/s6-neg.json" '[{"packageId":"pkg-badintake-a","sequence":-1},{"packageId":"pkg-badintake-b","sequence":1}]'
run_plan "$WORK/s6" "$WORK/s6-neg.json"
s6f_ok=$([ "$ec" -ne 0 ] && [ "$verdict" = "STEWARD_PLAN_INVALID" ] && echo 0 || echo 1)

s6_ok=$((s6a_ok + s6b_ok + s6c_ok + s6d_ok + s6e_ok + s6f_ok))
[ "$s6_ok" -gt 0 ] && s6_ok=1
report "scenario6 missing/extra/duplicate intake IDs or duplicate/noninteger/negative sequences invalid" $s6_ok "expected STEWARD_PLAN_INVALID/nonzero for all six malformed intake variants; got a=$s6a_ok b=$s6b_ok c=$s6c_ok d=$s6d_ok e=$s6e_ok f=$s6f_ok"

# ============================================================================
# Scenario 7: unknown lane name in a manifest, lane config drift
# (concurrency != 1), malformed intake JSON, and a conflict-scheduler-
# invalid batch (duplicate packageId across manifests) are all
# STEWARD_PLAN_INVALID/nonzero.
# ============================================================================
mkdir -p "$WORK/s7-unknownlane"
manifest "$WORK/s7-unknownlane/pkg-a.json" "$(pkg pkg-unknownlane-a MenuCatalog app/Unknown.php '[]' '["notARealLane"]')"
intake_file "$WORK/s7-unknownlane-intake.json" '[{"packageId":"pkg-unknownlane-a","sequence":0}]'
run_plan "$WORK/s7-unknownlane" "$WORK/s7-unknownlane-intake.json"
s7a_ok=$([ "$ec" -ne 0 ] && [ "$verdict" = "STEWARD_PLAN_INVALID" ] && echo 0 || echo 1)

mkdir -p "$WORK/s7-drift"
manifest "$WORK/s7-drift/pkg-a.json" "$(pkg pkg-drift-a MenuCatalog routes/api.php '[]' '["routes"]')"
intake_file "$WORK/s7-drift-intake.json" '[{"packageId":"pkg-drift-a","sequence":0}]'
jq '.moduleFramework.stewardLanes.lanes.routes.concurrentActivePackages = 2' "$SPEED_BUDGET_CONFIG" >"$WORK/s7-drift-config.json"
run_plan "$WORK/s7-drift" "$WORK/s7-drift-intake.json" "$WORK/s7-drift-config.json"
s7b_ok=$([ "$ec" -ne 0 ] && [ "$verdict" = "STEWARD_PLAN_INVALID" ] && echo 0 || echo 1)

mkdir -p "$WORK/s7-malformed"
manifest "$WORK/s7-malformed/pkg-a.json" "$(pkg pkg-malformed-a MenuCatalog routes/api.php '[]' '["routes"]')"
printf 'not json at all {' >"$WORK/s7-malformed-intake.json"
run_plan "$WORK/s7-malformed" "$WORK/s7-malformed-intake.json"
s7c_ok=$([ "$ec" -ne 0 ] && [ "$verdict" = "STEWARD_PLAN_INVALID" ] && echo 0 || echo 1)

mkdir -p "$WORK/s7-schedinvalid"
manifest "$WORK/s7-schedinvalid/pkg-a.json" "$(pkg pkg-schedinvalid-dup MenuCatalog routes/api.php '[]' '["routes"]')"
manifest "$WORK/s7-schedinvalid/pkg-b.json" "$(pkg pkg-schedinvalid-dup MenuCatalog resources/js/i18n/workspace.ts '[]' '["i18n"]')"
intake_file "$WORK/s7-schedinvalid-intake.json" '[{"packageId":"pkg-schedinvalid-dup","sequence":0}]'
run_plan "$WORK/s7-schedinvalid" "$WORK/s7-schedinvalid-intake.json"
s7d_ok=$([ "$ec" -ne 0 ] && [ "$verdict" = "STEWARD_PLAN_INVALID" ] && echo 0 || echo 1)

s7_ok=$((s7a_ok + s7b_ok + s7c_ok + s7d_ok))
[ "$s7_ok" -gt 0 ] && s7_ok=1
report "scenario7 unknown lane, lane config drift, malformed intake, or scheduler-invalid batch invalid" $s7_ok "expected STEWARD_PLAN_INVALID/nonzero for all four variants; got unknownlane=$s7a_ok drift=$s7b_ok malformed=$s7c_ok schedinvalid=$s7d_ok"

# ============================================================================
# Scenario 8: identical logical input (same manifests, same intake entries)
# supplied in a different filesystem/file-naming and JSON-entry-listing
# order yields a byte-identical plan, with laneQueues/prerequisites/waves
# all rendered in their documented sorted/deterministic form.
# ============================================================================
mkdir -p "$WORK/s8-order1" "$WORK/s8-order2"
manifest "$WORK/s8-order1/aaa-first.json" "$(pkg pkg-order-a MenuCatalog routes/api.php '[]' '["routes"]')"
manifest "$WORK/s8-order1/bbb-second.json" "$(pkg pkg-order-b MenuCatalog resources/js/i18n/workspace.ts '[]' '["i18n"]')"
manifest "$WORK/s8-order1/ccc-third.json" "$(pkg pkg-order-c MenuCatalog routes/api.php '[]' '["routes"]')"
manifest "$WORK/s8-order2/zzz-c.json" "$(pkg pkg-order-c MenuCatalog routes/api.php '[]' '["routes"]')"
manifest "$WORK/s8-order2/yyy-b.json" "$(pkg pkg-order-b MenuCatalog resources/js/i18n/workspace.ts '[]' '["i18n"]')"
manifest "$WORK/s8-order2/xxx-a.json" "$(pkg pkg-order-a MenuCatalog routes/api.php '[]' '["routes"]')"
intake_file "$WORK/s8-intake1.json" '[{"packageId":"pkg-order-a","sequence":0},{"packageId":"pkg-order-b","sequence":1},{"packageId":"pkg-order-c","sequence":2}]'
intake_file "$WORK/s8-intake2.json" '[{"packageId":"pkg-order-c","sequence":2},{"packageId":"pkg-order-a","sequence":0},{"packageId":"pkg-order-b","sequence":1}]'
run_plan "$WORK/s8-order1" "$WORK/s8-intake1.json"
json1="$json"; ec1="$ec"; verdict1="$verdict"
run_plan "$WORK/s8-order2" "$WORK/s8-intake2.json"
json2="$json"; ec2="$ec"; verdict2="$verdict"
s8_ok=1
if [ "$ec1" -eq 0 ] && [ "$verdict1" = "STEWARD_PLAN" ] && [ "$ec2" -eq 0 ] && [ "$verdict2" = "STEWARD_PLAN" ] \
  && [ -n "$json1" ] && [ "$json1" = "$json2" ]; then
  s8_ok=0
fi
report "scenario8 filesystem/entry order changes produce byte-identical plan" $s8_ok "expected identical STEWARD_PLAN JSON regardless of manifest filename order and intake entry listing order; got ec1=$ec1 verdict1=$verdict1 ec2=$ec2 verdict2=$verdict2 json1=$json1 json2=$json2"

# ============================================================================
printf -- '--- steward-queue.test.sh: %d passed, %d failed ---\n' "$pass" "$fail"
[ "$fail" -eq 0 ]
