#!/usr/bin/env bash
# Deterministic shell integration test for scripts/affected-tests
# (fast-delivery genome item 5, frozen scenarios 1-8).
#
# CLI contract this test assumes for the not-yet-implemented
# scripts/affected-tests:
#
#   scripts/affected-tests select --manifest <package-manifest.json> --config <development-speed-budget.json>
#     - --manifest is the SAME composite package-manifest shape consumed by
#       scripts/module-graph-gate (packageId, module, allowedFiles, writeSet,
#       readSet, contractsConsumed, contractsChanged,
#       tablesOrMigrationsTouched, sharedSurfacesTouched, dependsOnPackages);
#       select MUST first accept only manifests already valid under
#       `scripts/module-graph-gate validate` before doing anything else.
#     - --config ALWAYS receives config/development-speed-budget.json
#       (highRiskPathPatterns, highRiskSemanticClasses, stewardLanes live
#       there), exactly like module-graph-gate and speed-gate.
#     - the canonical config/module-dependency-dag.json (reverse-DAG closure)
#       and the future canonical config/affected-test-map.json (module ->
#       declared PHPUnit/Vitest test targets) are BOTH self-discovered
#       relative to repo root; neither is ever passed on the CLI.
#     - a changed module (derived from manifest.module / writeSet paths)
#       includes its transitive REVERSE-DAG consumers (i.e. every module
#       whose source imports the changed module, transitively) in the
#       targeted set -- not the module's own forward dependencies.
#     - any configured high-risk path (highRiskPathPatterns), any steward/
#       shared surface (sharedSurfacesTouched / stewardLanes.coversFiles),
#       any writeSet/allowedFiles path with no mapping in
#       config/affected-test-map.json (missing or drifted mapping), or any
#       path that cannot be classified into a known module at all, forces
#       FULL_TEST_PLAN -- never a silently empty/partial targeted set.
#     - stdout is exactly one deterministic COMPACT single-line JSON plan
#       object as the second-to-last line, immediately followed by exactly
#       one of TARGETED_TEST_PLAN or FULL_TEST_PLAN as the LAST line; the
#       plan JSON carries at least a sorted, de-duplicated "targets" array
#       and a "modules" array of the affected-module closure.
#     - a malformed manifest, a manifest that module-graph-gate itself would
#       reject, or a manifest whose writeSet (after allowedFiles/writeSet
#       set-equality per module-graph-gate) is empty, all print
#       MANIFEST_INVALID as the LAST line and exit non-zero -- no plan JSON
#       is printed in that case.
#     - existing mandatory full CI QA (ciFullQaMax in
#       config/development-speed-budget.json) is untouched by this tool;
#       affected-tests only narrows what independent/targeted runs execute
#       ahead of that CI gate.
#
# No implementation exists yet at scripts/affected-tests, and no canonical
# config/affected-test-map.json exists yet either: every scenario below must
# fail (RED) for a real, checkable reason (missing selector binary and/or
# missing canonical test-target map) -- not merely crash on a shell syntax
# error.

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SELECT_BIN="$SCRIPT_DIR/affected-tests"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
GATE="$SCRIPT_DIR/module-graph-gate"
CANONICAL_DAG="$REPO_ROOT/config/module-dependency-dag.json"
CANONICAL_TEST_MAP="$REPO_ROOT/config/affected-test-map.json"
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

run_select() {
  local manifest_file="$1" out_file="$2" err_file="$3"
  "$SELECT_BIN" select --manifest "$manifest_file" --config "$SPEED_BUDGET_CONFIG" >"$out_file" 2>"$err_file"
  echo $?
}

# ============================================================================
# Scenario 1: canonical config/affected-test-map.json exists, maps every DAG
# node to at least one declared, actually-existing PHPUnit/Vitest test target,
# and every declared target path exists on disk. Also requires the map cover
# MenuCatalog, Taxonomy, Publication, and Billing (the modules the remaining
# scenarios depend on).
# ============================================================================
map_shape_ok=1
map_detail="config/affected-test-map.json missing, unreadable, or missing/dangling declared test targets"
if [ -f "$CANONICAL_TEST_MAP" ] && [ -f "$CANONICAL_DAG" ] && command -v jq >/dev/null 2>&1; then
  nodes_json=$(jq -c '.nodes // []' "$CANONICAL_DAG" 2>/dev/null)
  required_modules='["MenuCatalog","Taxonomy","Publication","Billing"]'
  all_nodes_mapped=$(jq -r --argjson nodes "$nodes_json" '
    ($nodes | length) as $n
    | ([$nodes[] | . as $m | if (($ARGS.named.map[$m] // []) | length) > 0 then 1 else empty end] | length) as $mapped
    | if $mapped == $n then "yes" else "no" end
  ' --slurpfile map_arr "$CANONICAL_TEST_MAP" <<<'null' 2>/dev/null)
  # simpler direct check avoiding slurpfile arg complexity above
  all_nodes_mapped=$(jq -n -r --argjson nodes "$nodes_json" --slurpfile mapdoc "$CANONICAL_TEST_MAP" '
    ($mapdoc[0] // {}) as $map
    | ($nodes | length) as $n
    | ([$nodes[] | select((($map[.]? // []) | length) > 0)] | length) as $mapped
    | if $n > 0 and $mapped == $n then "yes" else "no" end
  ' 2>/dev/null)
  required_present=$(jq -n -r --argjson req "$required_modules" --slurpfile mapdoc "$CANONICAL_TEST_MAP" '
    ($mapdoc[0] // {}) as $map
    | ($req | length) as $n
    | ([$req[] | select((($map[.]? // []) | length) > 0)] | length) as $have
    | if $have == $n then "yes" else "no" end
  ' 2>/dev/null)
  targets_exist="yes"
  all_targets=$(jq -r '[.[]?[]?] | unique | .[]' "$CANONICAL_TEST_MAP" 2>/dev/null)
  if [ -n "$all_targets" ]; then
    while IFS= read -r t; do
      [ -n "$t" ] || continue
      if [ ! -f "$REPO_ROOT/$t" ]; then
        targets_exist="no"
      fi
    done <<<"$all_targets"
  else
    targets_exist="no"
  fi
  if [ "$all_nodes_mapped" = "yes" ] && [ "$required_present" = "yes" ] && [ "$targets_exist" = "yes" ]; then
    map_shape_ok=0
  else
    map_detail="all_nodes_mapped=${all_nodes_mapped:-no} required_present=${required_present:-no} targets_exist=$targets_exist"
  fi
fi
report "scenario1 canonical affected-test-map covers every DAG node with existing test targets" $map_shape_ok "$map_detail"

# ============================================================================
# Scenario 2: malformed JSON, module-graph-invalid, and empty-writeSet
# manifests all fail MANIFEST_INVALID (no plan JSON, non-zero exit)
# ============================================================================
s2_ok=0

printf '{not valid json' >"$WORK/s2-malformed.json"
ec=$(run_select "$WORK/s2-malformed.json" "$WORK/s2-malformed.out" "$WORK/s2-malformed.err")
verdict=$(tail -n1 "$WORK/s2-malformed.out" 2>/dev/null)
{ [ "$verdict" = "MANIFEST_INVALID" ] && [ "$ec" -ne 0 ]; } || s2_ok=1

manifest "$WORK/s2-graphinvalid.json" "{
  \"packageId\": \"pkg-unknownmod-001\",
  \"module\": \"NotAModule\",
  \"allowedFiles\": [\"app/NotAModule/Foo.php\"],
  \"writeSet\": [\"app/NotAModule/Foo.php\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
if [ -x "$GATE" ]; then
  gate_out=$("$GATE" validate --manifest "$WORK/s2-graphinvalid.json" --config "$SPEED_BUDGET_CONFIG" 2>/dev/null)
  gate_verdict=$(printf '%s\n' "$gate_out" | tail -n1)
  [ "$gate_verdict" = "MANIFEST_INVALID" ] || s2_ok=1
fi
ec=$(run_select "$WORK/s2-graphinvalid.json" "$WORK/s2-graphinvalid.out" "$WORK/s2-graphinvalid.err")
verdict=$(tail -n1 "$WORK/s2-graphinvalid.out" 2>/dev/null)
{ [ "$verdict" = "MANIFEST_INVALID" ] && [ "$ec" -ne 0 ]; } || s2_ok=1

manifest "$WORK/s2-emptywriteset.json" "{
  \"packageId\": \"pkg-emptyws-001\",
  \"module\": \"MenuCatalog\",
  \"allowedFiles\": [],
  \"writeSet\": [],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
ec=$(run_select "$WORK/s2-emptywriteset.json" "$WORK/s2-emptywriteset.out" "$WORK/s2-emptywriteset.err")
verdict=$(tail -n1 "$WORK/s2-emptywriteset.out" 2>/dev/null)
{ [ "$verdict" = "MANIFEST_INVALID" ] && [ "$ec" -ne 0 ]; } || s2_ok=1

# correction: missing highRiskPathPatterns key in --config must never narrow
# selection -- an otherwise valid nonempty manifest must still be refused as
# MANIFEST_INVALID (missing risk policy is a config-invalid condition, not a
# license to skip high-risk classification).
manifest "$WORK/s2-noriskconfig-manifest.json" "{
  \"packageId\": \"pkg-noriskconfig-001\",
  \"module\": \"MenuCatalog\",
  \"allowedFiles\": [\"app/Domain/MenuCatalog/Product.php\"],
  \"writeSet\": [\"app/Domain/MenuCatalog/Product.php\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
if command -v jq >/dev/null 2>&1; then
  jq 'del(.highRiskPathPatterns)' "$SPEED_BUDGET_CONFIG" >"$WORK/s2-noriskpolicy-config.json" 2>/dev/null
else
  s2_ok=1
fi
if [ -s "$WORK/s2-noriskpolicy-config.json" ]; then
  "$SELECT_BIN" select --manifest "$WORK/s2-noriskconfig-manifest.json" --config "$WORK/s2-noriskpolicy-config.json" \
    >"$WORK/s2-noriskconfig.out" 2>"$WORK/s2-noriskconfig.err"
  ec=$?
  verdict=$(tail -n1 "$WORK/s2-noriskconfig.out" 2>/dev/null)
  { [ "$verdict" = "MANIFEST_INVALID" ] && [ "$ec" -ne 0 ]; } || s2_ok=1
else
  s2_ok=1
fi

# correction: an unknown CLI flag on an otherwise valid invocation must also
# fail MANIFEST_INVALID/nonzero -- unknown flags must never be silently
# ignored.
"$SELECT_BIN" select --manifest "$WORK/s2-noriskconfig-manifest.json" --config "$SPEED_BUDGET_CONFIG" --bogus-flag \
  >"$WORK/s2-unknownflag.out" 2>"$WORK/s2-unknownflag.err"
ec=$?
verdict=$(tail -n1 "$WORK/s2-unknownflag.out" 2>/dev/null)
{ [ "$verdict" = "MANIFEST_INVALID" ] && [ "$ec" -ne 0 ]; } || s2_ok=1

report "scenario2 malformed / module-graph-invalid / empty-writeSet manifests fail MANIFEST_INVALID" $s2_ok "see $WORK/s2-*.err"

# ============================================================================
# Scenario 3: MenuCatalog source change -> targeted MenuCatalog+Publication
# reverse closure (Publication imports MenuCatalog per the canonical DAG),
# sorted de-duplicated PHPUnit/Vitest targets, TARGETED_TEST_PLAN
# ============================================================================
manifest "$WORK/s3-menucatalog.json" "{
  \"packageId\": \"pkg-menucatalog-001\",
  \"module\": \"MenuCatalog\",
  \"allowedFiles\": [\"app/Domain/MenuCatalog/Product.php\"],
  \"writeSet\": [\"app/Domain/MenuCatalog/Product.php\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
ec=$(run_select "$WORK/s3-menucatalog.json" "$WORK/s3-menucatalog.out" "$WORK/s3-menucatalog.err")
verdict=$(tail -n1 "$WORK/s3-menucatalog.out" 2>/dev/null)
plan_line=$(tail -n2 "$WORK/s3-menucatalog.out" 2>/dev/null | head -n1)
s3_ok=0
[ "$verdict" = "TARGETED_TEST_PLAN" ] || s3_ok=1
[ "$ec" -eq 0 ] || s3_ok=1
if command -v jq >/dev/null 2>&1 && [ -n "$plan_line" ]; then
  modules_ok=$(printf '%s' "$plan_line" | jq -r '
    (.modules // []) as $m
    | if (($m | index("MenuCatalog")) != null) and (($m | index("Publication")) != null) then "yes" else "no" end
  ' 2>/dev/null)
  sorted_ok=$(printf '%s' "$plan_line" | jq -r '
    (.targets // []) as $t
    | if $t == ($t | unique | sort) and ($t | length) > 0 then "yes" else "no" end
  ' 2>/dev/null)
  [ "$modules_ok" = "yes" ] || s3_ok=1
  [ "$sorted_ok" = "yes" ] || s3_ok=1
else
  s3_ok=1
fi
report "scenario3 MenuCatalog change yields targeted MenuCatalog+Publication closure, sorted targets" $s3_ok "verdict='$verdict' exit=$ec plan='$plan_line' see $WORK/s3-menucatalog.err"

# ============================================================================
# Scenario 4: Taxonomy change -> Taxonomy+MenuCatalog+Publication reverse
# closure (MenuCatalog imports Taxonomy; Publication imports MenuCatalog)
# ============================================================================
manifest "$WORK/s4-taxonomy.json" "{
  \"packageId\": \"pkg-taxonomy-001\",
  \"module\": \"Taxonomy\",
  \"allowedFiles\": [\"app/Domain/Taxonomy/TaxonomyTerm.php\"],
  \"writeSet\": [\"app/Domain/Taxonomy/TaxonomyTerm.php\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
ec=$(run_select "$WORK/s4-taxonomy.json" "$WORK/s4-taxonomy.out" "$WORK/s4-taxonomy.err")
verdict=$(tail -n1 "$WORK/s4-taxonomy.out" 2>/dev/null)
plan_line=$(tail -n2 "$WORK/s4-taxonomy.out" 2>/dev/null | head -n1)
s4_ok=0
[ "$verdict" = "TARGETED_TEST_PLAN" ] || s4_ok=1
[ "$ec" -eq 0 ] || s4_ok=1
if command -v jq >/dev/null 2>&1 && [ -n "$plan_line" ]; then
  modules_ok=$(printf '%s' "$plan_line" | jq -r '
    (.modules // []) as $m
    | if (($m | index("Taxonomy")) != null) and (($m | index("MenuCatalog")) != null) and (($m | index("Publication")) != null)
      then "yes" else "no" end
  ' 2>/dev/null)
  [ "$modules_ok" = "yes" ] || s4_ok=1
else
  s4_ok=1
fi
report "scenario4 Taxonomy change yields Taxonomy+MenuCatalog+Publication reverse closure" $s4_ok "verdict='$verdict' exit=$ec plan='$plan_line' see $WORK/s4-taxonomy.err"

# ============================================================================
# Scenario 5: high-risk Billing path -> FULL_TEST_PLAN, never targeted
# ============================================================================
manifest "$WORK/s5-billing.json" "{
  \"packageId\": \"pkg-billing-001\",
  \"module\": \"Billing\",
  \"allowedFiles\": [\"app/Domain/Billing/PricingCalculator.php\"],
  \"writeSet\": [\"app/Domain/Billing/PricingCalculator.php\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
ec=$(run_select "$WORK/s5-billing.json" "$WORK/s5-billing.out" "$WORK/s5-billing.err")
verdict=$(tail -n1 "$WORK/s5-billing.out" 2>/dev/null)
report "scenario5 high-risk Billing path yields FULL_TEST_PLAN" $([ "$verdict" = "FULL_TEST_PLAN" ] && [ "$ec" -eq 0 ] && echo 0 || echo 1) "verdict='$verdict' exit=$ec see $WORK/s5-billing.err"

# ============================================================================
# Scenario 6: routes/api.php, migrations, WorkspaceApp shell, and CI shared
# surfaces (steward lanes) all yield FULL_TEST_PLAN
# ============================================================================
s6_ok=0

manifest "$WORK/s6-routes.json" "{
  \"packageId\": \"pkg-routes-001\",
  \"module\": \"MenuCatalog\",
  \"allowedFiles\": [\"routes/api.php\"],
  \"writeSet\": [\"routes/api.php\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [\"routes\"],
  \"dependsOnPackages\": []
}"
ec=$(run_select "$WORK/s6-routes.json" "$WORK/s6-routes.out" "$WORK/s6-routes.err")
verdict=$(tail -n1 "$WORK/s6-routes.out" 2>/dev/null)
{ [ "$verdict" = "FULL_TEST_PLAN" ] && [ "$ec" -eq 0 ]; } || s6_ok=1

manifest "$WORK/s6-migrations.json" "{
  \"packageId\": \"pkg-migrations-001\",
  \"module\": \"MenuCatalog\",
  \"allowedFiles\": [\"database/migrations/2026_01_01_000000_add_menu_index.php\"],
  \"writeSet\": [\"database/migrations/2026_01_01_000000_add_menu_index.php\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [\"menu_items\"],
  \"sharedSurfacesTouched\": [\"migrationsSchema\"],
  \"dependsOnPackages\": []
}"
ec=$(run_select "$WORK/s6-migrations.json" "$WORK/s6-migrations.out" "$WORK/s6-migrations.err")
verdict=$(tail -n1 "$WORK/s6-migrations.out" 2>/dev/null)
{ [ "$verdict" = "FULL_TEST_PLAN" ] && [ "$ec" -eq 0 ]; } || s6_ok=1

manifest "$WORK/s6-shell.json" "{
  \"packageId\": \"pkg-shell-001\",
  \"module\": \"MenuCatalog\",
  \"allowedFiles\": [\"resources/js/components/workspace/WorkspaceApp.tsx\"],
  \"writeSet\": [\"resources/js/components/workspace/WorkspaceApp.tsx\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [\"frontendShell\"],
  \"dependsOnPackages\": []
}"
ec=$(run_select "$WORK/s6-shell.json" "$WORK/s6-shell.out" "$WORK/s6-shell.err")
verdict=$(tail -n1 "$WORK/s6-shell.out" 2>/dev/null)
{ [ "$verdict" = "FULL_TEST_PLAN" ] && [ "$ec" -eq 0 ]; } || s6_ok=1

manifest "$WORK/s6-ci.json" "{
  \"packageId\": \"pkg-ci-001\",
  \"module\": \"MenuCatalog\",
  \"allowedFiles\": [\".github/workflows/ci.yml\"],
  \"writeSet\": [\".github/workflows/ci.yml\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [\"ci\"],
  \"dependsOnPackages\": []
}"
ec=$(run_select "$WORK/s6-ci.json" "$WORK/s6-ci.out" "$WORK/s6-ci.err")
verdict=$(tail -n1 "$WORK/s6-ci.out" 2>/dev/null)
{ [ "$verdict" = "FULL_TEST_PLAN" ] && [ "$ec" -eq 0 ]; } || s6_ok=1

report "scenario6 routes/migrations/WorkspaceApp-shell/CI shared surfaces yield FULL_TEST_PLAN" $s6_ok "see $WORK/s6-*.err"

# ============================================================================
# Scenario 7: unclassified path (no module, not a declared shared surface)
# yields FULL_TEST_PLAN, never a silent empty target set
# ============================================================================
manifest "$WORK/s7-unclassified.json" "{
  \"packageId\": \"pkg-unclassified-001\",
  \"module\": \"MenuCatalog\",
  \"allowedFiles\": [\"scripts/foo.sh\"],
  \"writeSet\": [\"scripts/foo.sh\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
ec=$(run_select "$WORK/s7-unclassified.json" "$WORK/s7-unclassified.out" "$WORK/s7-unclassified.err")
verdict=$(tail -n1 "$WORK/s7-unclassified.out" 2>/dev/null)
report "scenario7 unclassified path yields FULL_TEST_PLAN, never silent empty targets" $([ "$verdict" = "FULL_TEST_PLAN" ] && [ "$ec" -eq 0 ] && echo 0 || echo 1) "verdict='$verdict' exit=$ec see $WORK/s7-unclassified.err"

# ============================================================================
# Scenario 8: two independent known changes in one writeSet -- a MenuCatalog
# source file (pulling in the MenuCatalog+Publication closure) and a direct
# Publication test-file edit (already inside that same closure) -- must yield
# one deterministic de-duplicated targeted union, not a doubled Publication
# entry, and must remain TARGETED_TEST_PLAN (neither path is high-risk or a
# shared surface).
# ============================================================================
manifest "$WORK/s8-union.json" "{
  \"packageId\": \"pkg-union-001\",
  \"module\": \"MenuCatalog\",
  \"allowedFiles\": [
    \"app/Domain/MenuCatalog/Product.php\",
    \"tests/Feature/Publication/PublicationJourneyTest.php\"
  ],
  \"writeSet\": [
    \"app/Domain/MenuCatalog/Product.php\",
    \"tests/Feature/Publication/PublicationJourneyTest.php\"
  ],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
ec=$(run_select "$WORK/s8-union.json" "$WORK/s8-union.out" "$WORK/s8-union.err")
verdict=$(tail -n1 "$WORK/s8-union.out" 2>/dev/null)
plan_line=$(tail -n2 "$WORK/s8-union.out" 2>/dev/null | head -n1)
s8_ok=0
[ "$verdict" = "TARGETED_TEST_PLAN" ] || s8_ok=1
[ "$ec" -eq 0 ] || s8_ok=1
if command -v jq >/dev/null 2>&1 && [ -n "$plan_line" ]; then
  union_ok=$(printf '%s' "$plan_line" | jq -r '
    (.targets // []) as $t
    | if $t == ($t | unique)
        and (($t | index("tests/Feature/Publication/PublicationJourneyTest.php")) != null)
      then "yes" else "no" end
  ' 2>/dev/null)
  [ "$union_ok" = "yes" ] || s8_ok=1
else
  s8_ok=1
fi

# correction: a writeSet path that is the exact test-target
# tests/Feature/MenuCatalog/MenuCatalogTenantEscapeTest.php, which is
# deliberately mapped to BOTH MenuCatalog and Taxonomy in
# config/affected-test-map.json, must pull in every module that maps that
# exact target -- not just the first owner encountered. modules must contain
# MenuCatalog, Taxonomy, and Publication (Publication is in the closure via
# MenuCatalog and/or Taxonomy) and stay unique/sorted.
manifest "$WORK/s8-multiowner.json" "{
  \"packageId\": \"pkg-multiowner-001\",
  \"module\": \"MenuCatalog\",
  \"allowedFiles\": [
    \"tests/Feature/MenuCatalog/MenuCatalogTenantEscapeTest.php\"
  ],
  \"writeSet\": [
    \"tests/Feature/MenuCatalog/MenuCatalogTenantEscapeTest.php\"
  ],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
ec=$(run_select "$WORK/s8-multiowner.json" "$WORK/s8-multiowner.out" "$WORK/s8-multiowner.err")
verdict=$(tail -n1 "$WORK/s8-multiowner.out" 2>/dev/null)
plan_line=$(tail -n2 "$WORK/s8-multiowner.out" 2>/dev/null | head -n1)
[ "$verdict" = "TARGETED_TEST_PLAN" ] || s8_ok=1
[ "$ec" -eq 0 ] || s8_ok=1
if command -v jq >/dev/null 2>&1 && [ -n "$plan_line" ]; then
  multiowner_ok=$(printf '%s' "$plan_line" | jq -r '
    (.modules // []) as $m
    | if (($m | index("MenuCatalog")) != null)
        and (($m | index("Taxonomy")) != null)
        and (($m | index("Publication")) != null)
        and ($m == ($m | unique | sort))
      then "yes" else "no" end
  ' 2>/dev/null)
  [ "$multiowner_ok" = "yes" ] || s8_ok=1
else
  s8_ok=1
fi
report "scenario8 two independent module/direct-test changes yield deterministic de-duplicated targeted union" $s8_ok "verdict='$verdict' exit=$ec plan='$plan_line' see $WORK/s8-multiowner.err"

printf '\n%d passed, %d failed\n' "$pass" "$fail"
[ "$fail" -eq 0 ]
