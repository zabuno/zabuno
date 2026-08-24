#!/usr/bin/env bash
# Deterministic shell integration test for scripts/conflict-scheduler
# (fast-delivery genome overlay item 6, frozen scenarios 1-8).
#
# CLI contract this test assumes for the not-yet-implemented
# scripts/conflict-scheduler:
#
#   scripts/conflict-scheduler plan --manifests <dir> --config <development-speed-budget.json>
#     - individually requires every manifest in <dir> to PASS
#       scripts/module-graph-gate validate --manifest <that file> --config <config>
#     - requires unique nonempty packageId across the batch and a nonempty
#       batch (>= 1 manifest)
#     - consumes scripts/module-graph-gate conflicts --manifests <dir> --config
#       <config>; a valid gate result is either NO_CONFLICTS/exit 0, or a
#       compact JSON conflict-edge array on stdout followed by
#       CONFLICT_EDGES_FOUND/exit 1 -- any other gate output shape is invalid
#     - directed in-batch dependsOnPackages must be topologically satisfied;
#       an out-of-batch dependency is already merged and never blocks
#       scheduling
#     - fails SCHEDULE_INVALID/nonzero (no plan JSON on stdout) for: unknown
#       args, an invalid manifest, a duplicate packageId, a dependency cycle,
#       or no schedulable candidate
#     - on success, prints exactly one compact deterministic JSON object on
#       stdout, followed by the verdict token SCHEDULE_PLAN on the last
#       line, exit 0. The JSON includes:
#         packages       -- lexically sorted packageIds
#         conflictEdges  -- the gate's conflict edges, sorted
#         components     -- connected conflict components, including
#                            isolated singleton packages, as sorted arrays
#                            of sorted packageIds
#         waves          -- greedy deterministic array-of-arrays; within each
#                            wave packageIds are lexically sorted; every
#                            dependsOnPackages target that is in-batch
#                            appears in a strictly earlier wave; no wave
#                            contains both endpoints of an exact conflict
#                            edge; at each wave, lexically sorted eligible
#                            packages are greedily accepted if they conflict
#                            with none already accepted in that wave (so
#                            non-adjacent vertices of a larger conflict
#                            component may still share a wave)
#     - pure printer: no Git mutation, no admission/queue side effects
#
# No implementation exists yet at scripts/conflict-scheduler: every scenario
# below asserts the REAL target behavior (verdict token, exit code, and
# specific jq-extracted plan keys), so every scenario must currently fail
# (RED) because the scheduler binary itself is absent -- not because of a
# shell syntax error in this test. This test does NOT assert brittle
# full-JSON snapshots; it asserts exact token/exit/key invariants.

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SCHEDULER="$SCRIPT_DIR/conflict-scheduler"
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

pkg() {
  local id="$1" module="$2" file="$3" deps="$4"
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
  "sharedSurfacesTouched": [],
  "dependsOnPackages": $deps
}
JSON
}

# run_plan <manifests-dir> -> sets $out, $ec, $verdict, $json (last line
# stripped, plan JSON candidate = first stdout line)
run_plan() {
  local dir="$1"
  local errfile="$2"
  out=$("$SCHEDULER" plan --manifests "$dir" --config "$SPEED_BUDGET_CONFIG" 2>"$errfile")
  ec=$?
  verdict=$(printf '%s\n' "$out" | tail -n1)
  json=$(printf '%s\n' "$out" | sed '$d')
}

# ============================================================================
# Scenario 1: a write/write conflict separates two packages into different
# waves, and the conflict reason string is preserved in conflictEdges.
# ============================================================================
mkdir -p "$WORK/s1"
manifest "$WORK/s1/pkg-a.json" "$(pkg pkg-ww-a Authorization app/Authorization/Same.php '[]')"
manifest "$WORK/s1/pkg-b.json" "$(pkg pkg-ww-b Authorization app/Authorization/Same.php '[]')"
run_plan "$WORK/s1" "$WORK/s1.err"
s1_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "SCHEDULE_PLAN" ] && command -v jq >/dev/null 2>&1; then
  a_wave=$(printf '%s' "$json" | jq -r '.waves | to_entries[] | select(.value | index("pkg-ww-a") != null) | .key' 2>/dev/null)
  b_wave=$(printf '%s' "$json" | jq -r '.waves | to_entries[] | select(.value | index("pkg-ww-b") != null) | .key' 2>/dev/null)
  reason=$(printf '%s' "$json" | jq -r '.conflictEdges[] | select(.packageIds == ["pkg-ww-a","pkg-ww-b"]) | .reason' 2>/dev/null)
  if [ -n "$a_wave" ] && [ -n "$b_wave" ] && [ "$a_wave" != "$b_wave" ] && [ -n "$reason" ]; then
    s1_ok=0
  fi
fi
report "scenario1 write/write conflict separates packages, preserves reason" $s1_ok "expected SCHEDULE_PLAN with pkg-ww-a/pkg-ww-b in different waves and a nonempty reason; got ec=$ec verdict='$verdict' stderr=$(cat "$WORK/s1.err")"

# ============================================================================
# Scenario 2: two unrelated (disjoint, no deps) packages share wave 0.
# ============================================================================
mkdir -p "$WORK/s2"
manifest "$WORK/s2/pkg-a.json" "$(pkg pkg-disjoint-a MenuCatalog app/MenuCatalog/A.php '[]')"
manifest "$WORK/s2/pkg-b.json" "$(pkg pkg-disjoint-b MenuCatalog app/MenuCatalog/B.php '[]')"
run_plan "$WORK/s2" "$WORK/s2.err"
s2_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "SCHEDULE_PLAN" ] && command -v jq >/dev/null 2>&1; then
  wave0=$(printf '%s' "$json" | jq -c '.waves[0] // [] | sort' 2>/dev/null)
  if [ "$wave0" = '["pkg-disjoint-a","pkg-disjoint-b"]' ]; then
    s2_ok=0
  fi
fi
report "scenario2 disjoint packages share wave 0" $s2_ok "expected SCHEDULE_PLAN with wave 0 == [pkg-disjoint-a, pkg-disjoint-b]; got ec=$ec verdict='$verdict' json=$json stderr=$(cat "$WORK/s2.err")"

# ============================================================================
# Scenario 3: path graph A-B-C (A conflicts B, B conflicts C, A/C disjoint)
# must schedule A+C together in one wave and B alone in another -- proving
# non-adjacent-in-component packages may share a wave, i.e. no
# whole-component serialization.
# ============================================================================
mkdir -p "$WORK/s3"
manifest "$WORK/s3/pkg-a.json" "$(pkg pkg-path-a Authorization app/Authorization/Shared1.php '[]')"
manifest "$WORK/s3/pkg-b.json" "{
  \"packageId\": \"pkg-path-b\",
  \"module\": \"Authorization\",
  \"allowedFiles\": [\"app/Authorization/Shared1.php\", \"app/Authorization/Shared2.php\"],
  \"writeSet\": [\"app/Authorization/Shared1.php\", \"app/Authorization/Shared2.php\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
manifest "$WORK/s3/pkg-c.json" "$(pkg pkg-path-c Authorization app/Authorization/Shared2.php '[]')"
run_plan "$WORK/s3" "$WORK/s3.err"
s3_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "SCHEDULE_PLAN" ] && command -v jq >/dev/null 2>&1; then
  a_wave=$(printf '%s' "$json" | jq -r '.waves | to_entries[] | select(.value | index("pkg-path-a") != null) | .key' 2>/dev/null)
  b_wave=$(printf '%s' "$json" | jq -r '.waves | to_entries[] | select(.value | index("pkg-path-b") != null) | .key' 2>/dev/null)
  c_wave=$(printf '%s' "$json" | jq -r '.waves | to_entries[] | select(.value | index("pkg-path-c") != null) | .key' 2>/dev/null)
  if [ -n "$a_wave" ] && [ -n "$b_wave" ] && [ -n "$c_wave" ] \
    && [ "$a_wave" = "$c_wave" ] && [ "$a_wave" != "$b_wave" ]; then
    s3_ok=0
  fi
fi
report "scenario3 path graph A-C share a wave, B separate, not whole-component serialization" $s3_ok "expected SCHEDULE_PLAN with pkg-path-a and pkg-path-c in the same wave, pkg-path-b in a different one; got ec=$ec verdict='$verdict' json=$json stderr=$(cat "$WORK/s3.err")"

# ============================================================================
# Scenario 4: dependency chain A<-B<-C (C depends on B, B depends on A)
# yields three strictly ascending waves even with zero data conflicts.
# ============================================================================
mkdir -p "$WORK/s4"
manifest "$WORK/s4/pkg-a.json" "$(pkg pkg-chain-a MenuCatalog app/MenuCatalog/ChainA.php '[]')"
manifest "$WORK/s4/pkg-b.json" "$(pkg pkg-chain-b MenuCatalog app/MenuCatalog/ChainB.php '["pkg-chain-a"]')"
manifest "$WORK/s4/pkg-c.json" "$(pkg pkg-chain-c MenuCatalog app/MenuCatalog/ChainC.php '["pkg-chain-b"]')"
run_plan "$WORK/s4" "$WORK/s4.err"
s4_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "SCHEDULE_PLAN" ] && command -v jq >/dev/null 2>&1; then
  a_wave=$(printf '%s' "$json" | jq -r '.waves | to_entries[] | select(.value | index("pkg-chain-a") != null) | .key' 2>/dev/null)
  b_wave=$(printf '%s' "$json" | jq -r '.waves | to_entries[] | select(.value | index("pkg-chain-b") != null) | .key' 2>/dev/null)
  c_wave=$(printf '%s' "$json" | jq -r '.waves | to_entries[] | select(.value | index("pkg-chain-c") != null) | .key' 2>/dev/null)
  wave_count=$(printf '%s' "$json" | jq '.waves | length' 2>/dev/null)
  if [ -n "$a_wave" ] && [ -n "$b_wave" ] && [ -n "$c_wave" ] \
    && [ "$a_wave" -lt "$b_wave" ] 2>/dev/null && [ "$b_wave" -lt "$c_wave" ] 2>/dev/null \
    && [ "$wave_count" = "3" ]; then
    s4_ok=0
  fi
fi
report "scenario4 dependency chain yields three strictly ascending waves without data conflicts" $s4_ok "expected SCHEDULE_PLAN with exactly 3 waves and pkg-chain-a < pkg-chain-b < pkg-chain-c by wave index; got ec=$ec verdict='$verdict' json=$json stderr=$(cat "$WORK/s4.err")"

# ============================================================================
# Scenario 5: a dependsOnPackages cycle (A->B->A, in-batch) is SCHEDULE_INVALID
# ============================================================================
mkdir -p "$WORK/s5"
manifest "$WORK/s5/pkg-a.json" "$(pkg pkg-cycle-a MenuCatalog app/MenuCatalog/CycleA.php '["pkg-cycle-b"]')"
manifest "$WORK/s5/pkg-b.json" "$(pkg pkg-cycle-b MenuCatalog app/MenuCatalog/CycleB.php '["pkg-cycle-a"]')"
run_plan "$WORK/s5" "$WORK/s5.err"
s5_ok=$([ "$ec" -ne 0 ] && [ "$verdict" = "SCHEDULE_INVALID" ] && echo 0 || echo 1)
report "scenario5 in-batch dependency cycle SCHEDULE_INVALID" $s5_ok "expected nonzero exit and verdict=SCHEDULE_INVALID; got ec=$ec verdict='$verdict' stderr=$(cat "$WORK/s5.err")"

# ============================================================================
# Scenario 6: duplicate packageId across two manifests is SCHEDULE_INVALID,
# and every scheduler identifier (packageId, and every dependsOnPackages
# entry, in-batch or out-of-batch) must match the canonical grammar
# ^[A-Za-z0-9][A-Za-z0-9._-]*$ before delimiter-based storage -- a comma,
# tab, newline, space, leading hyphen, or shell/glob metacharacter in
# either position is SCHEDULE_INVALID/nonzero.
# ============================================================================
mkdir -p "$WORK/s6"
manifest "$WORK/s6/pkg-a.json" "$(pkg pkg-dup-x MenuCatalog app/MenuCatalog/DupA.php '[]')"
manifest "$WORK/s6/pkg-b.json" "$(pkg pkg-dup-x MenuCatalog app/MenuCatalog/DupB.php '[]')"
run_plan "$WORK/s6" "$WORK/s6.err"
s6_ok=$([ "$ec" -ne 0 ] && [ "$verdict" = "SCHEDULE_INVALID" ] && echo 0 || echo 1)
s6_detail="duplicate packageId: expected SCHEDULE_INVALID; got ec=$ec verdict='$verdict' stderr=$(cat "$WORK/s6.err")"

# jq-built single-package manifest, used so control characters (tab,
# newline) and shell metacharacters land in valid JSON without any
# shell-quoting hazard.
pkg_jq() {
  local id="$1" module="$2" file="$3" deps_json="$4"
  jq -n --arg id "$id" --arg module "$module" --arg file "$file" --argjson deps "$deps_json" \
    '{packageId:$id, module:$module, allowedFiles:[$file], writeSet:[$file], readSet:[], contractsConsumed:[], contractsChanged:[], tablesOrMigrationsTouched:[], sharedSurfacesTouched:[], dependsOnPackages:$deps}'
}

assert_invalid_batch() {
  local label="$1" dir="$2" errfile="$3"
  run_plan "$dir" "$errfile"
  if [ "$ec" -eq 0 ] || [ "$verdict" != "SCHEDULE_INVALID" ]; then
    s6_ok=1
    s6_detail="$s6_detail; $label: expected SCHEDULE_INVALID, got ec=$ec verdict='$verdict' stderr=$(cat "$errfile")"
  fi
}

if command -v jq >/dev/null 2>&1; then
  # packageId identifier-safety subcases: comma, tab, newline, space,
  # leading hyphen, and shell/glob metacharacters must all fail before
  # any delimiter-based storage of the identifier.
  i=0
  for bad_id in 'pkg,a' "$(printf 'pkg\tid')" "$(printf 'pkg\nid')" 'pkg id' \
    '-pkg-leading-hyphen' 'pkg;rm' 'pkg$(id)' 'pkg`id`' 'pkg*glob' \
    'pkg|pipe' 'pkg&bg' 'pkg<in' 'pkg>out'; do
    i=$((i + 1))
    d="$WORK/s6-badid-$i"
    mkdir -p "$d"
    pkg_jq "$bad_id" MenuCatalog "app/MenuCatalog/BadId$i.php" '[]' >"$d/pkg.json"
    assert_invalid_batch "packageId identifier-safety subcase $i" "$d" "$WORK/s6-badid-$i.err"
  done

  # dependsOnPackages identifier-safety subcases: the dependency target
  # is never in-batch here, proving an out-of-batch (already-merged)
  # dependency identifier is still validated, not skipped.
  j=0
  for bad_dep in 'dep,a' "$(printf 'dep\tid')" "$(printf 'dep\nid')" 'dep id' \
    '-dep-leading-hyphen' 'dep;rm' 'dep$(id)' 'dep`id`' 'dep*glob' \
    'dep|pipe' 'dep&bg' 'dep<in' 'dep>out'; do
    j=$((j + 1))
    d="$WORK/s6-baddep-$j"
    mkdir -p "$d"
    deps_json=$(jq -n --arg d "$bad_dep" '[$d]')
    pkg_jq "pkg-baddep-$j" MenuCatalog "app/MenuCatalog/BadDep$j.php" "$deps_json" >"$d/pkg.json"
    assert_invalid_batch "dependsOnPackages identifier-safety subcase $j (out-of-batch target)" "$d" "$WORK/s6-baddep-$j.err"
  done
else
  s6_ok=1
  s6_detail="$s6_detail; jq not available, cannot run identifier-safety subcases"
fi

report "scenario6 duplicate packageId and unsupported identifier characters SCHEDULE_INVALID" $s6_ok "$s6_detail"

# ============================================================================
# Scenario 7: a malformed/invalid sibling manifest fails SCHEDULE_INVALID,
# and a totally empty batch (no *.json manifests) also fails SCHEDULE_INVALID.
# ============================================================================
s7_ok=0

mkdir -p "$WORK/s7-invalid-sibling"
manifest "$WORK/s7-invalid-sibling/pkg-good.json" "$(pkg pkg-sibling-good MenuCatalog app/MenuCatalog/Good.php '[]')"
manifest "$WORK/s7-invalid-sibling/pkg-bad.json" '{
  "packageId": "pkg-sibling-bad",
  "module": "NotAModule"
}'
run_plan "$WORK/s7-invalid-sibling" "$WORK/s7-sibling.err"
if [ "$ec" -eq 0 ] || [ "$verdict" != "SCHEDULE_INVALID" ]; then
  s7_ok=1
fi

mkdir -p "$WORK/s7-empty"
run_plan "$WORK/s7-empty" "$WORK/s7-empty.err"
if [ "$ec" -eq 0 ] || [ "$verdict" != "SCHEDULE_INVALID" ]; then
  s7_ok=1
fi

report "scenario7 malformed sibling and empty batch both SCHEDULE_INVALID" $s7_ok "expected nonzero exit and verdict=SCHEDULE_INVALID for both sub-cases; see $WORK/s7-*.err"

# ============================================================================
# Scenario 8: same inputs, created/listed in a different filesystem order,
# produce a byte-identical plan (including sorted components and waves).
# Two batches with the same three manifests written in reverse creation
# order must yield identical stdout.
# ============================================================================
mkdir -p "$WORK/s8-order-a" "$WORK/s8-order-b"
manifest "$WORK/s8-order-a/1-alpha.json" "$(pkg pkg-order-alpha MenuCatalog app/MenuCatalog/Alpha.php '[]')"
manifest "$WORK/s8-order-a/2-beta.json" "$(pkg pkg-order-beta MenuCatalog app/MenuCatalog/Beta.php '[]')"
manifest "$WORK/s8-order-a/3-gamma.json" "$(pkg pkg-order-gamma MenuCatalog app/MenuCatalog/Gamma.php '[]')"

manifest "$WORK/s8-order-b/3-gamma.json" "$(pkg pkg-order-gamma MenuCatalog app/MenuCatalog/Gamma.php '[]')"
manifest "$WORK/s8-order-b/2-beta.json" "$(pkg pkg-order-beta MenuCatalog app/MenuCatalog/Beta.php '[]')"
manifest "$WORK/s8-order-b/1-alpha.json" "$(pkg pkg-order-alpha MenuCatalog app/MenuCatalog/Alpha.php '[]')"

run_plan "$WORK/s8-order-a" "$WORK/s8a.err"
out_a="$out"; ec_a="$ec"; verdict_a="$verdict"
run_plan "$WORK/s8-order-b" "$WORK/s8b.err"
out_b="$out"; ec_b="$ec"; verdict_b="$verdict"

s8_ok=1
if [ "$ec_a" -eq 0 ] && [ "$ec_b" -eq 0 ] \
  && [ "$verdict_a" = "SCHEDULE_PLAN" ] && [ "$verdict_b" = "SCHEDULE_PLAN" ] \
  && [ "$out_a" = "$out_b" ]; then
  s8_ok=0
fi
report "scenario8 order-independent byte-identical plan" $s8_ok "expected identical SCHEDULE_PLAN stdout regardless of filesystem manifest order; got ec_a=$ec_a ec_b=$ec_b verdict_a='$verdict_a' verdict_b='$verdict_b' stderr_a=$(cat "$WORK/s8a.err") stderr_b=$(cat "$WORK/s8b.err")"

# ============================================================================
printf '%s\n' "-- summary: $pass passed, $fail failed --"
if [ "$fail" -ne 0 ]; then
  exit 1
fi
exit 0
