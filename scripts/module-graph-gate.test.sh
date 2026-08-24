#!/usr/bin/env bash
# Deterministic shell integration test for scripts/module-graph-gate
# (fast-delivery genome item 4, frozen scenarios 1-8).
#
# CLI contract this test assumes for the not-yet-implemented
# scripts/module-graph-gate:
#
#   scripts/module-graph-gate validate --manifest <package-manifest.json> --config <development-speed-budget.json>
#     - reads one composite package manifest (packageId, module, allowedFiles,
#       writeSet, readSet, contractsConsumed, contractsChanged,
#       tablesOrMigrationsTouched, sharedSurfacesTouched, dependsOnPackages)
#     - --config ALWAYS receives config/development-speed-budget.json, because
#       moduleFramework.packageManifestSchema and the canonical steward lane
#       names live there; the gate never receives the DAG via --config
#     - the gate itself discovers the sibling canonical
#       config/module-dependency-dag.json from repo root (relative to its own
#       location / the repo root it resolves), it is never passed on the CLI
#     - validates manifest shape/types, known module membership, contract ref
#       format (portName@64-lowercase-hex), and writeSet/allowedFiles set-equality
#       over safe repo-relative unique paths
#     - prints a single verdict token on stdout as the LAST line: PASS or
#       MANIFEST_INVALID; exits 0 on PASS, non-zero otherwise
#
#   scripts/module-graph-gate conflicts --manifests <dir> --config <development-speed-budget.json>
#     - reads every *.json manifest in <dir> as one candidate batch
#     - same --config contract as validate (development-speed-budget.json,
#       never the DAG); the DAG is still self-discovered from repo root
#     - prints a single verdict token on stdout as the LAST line: NO_CONFLICTS
#       or CONFLICT_EDGES_FOUND; exits 0 on NO_CONFLICTS, non-zero otherwise
#     - when CONFLICT_EDGES_FOUND, also prints a deterministic JSON array of
#       conflict edges (pair of packageIds + reason) somewhere in stdout
#       before the final verdict line
#
# No implementation exists yet at scripts/module-graph-gate, and no canonical
# config/module-dependency-dag.json exists yet either: every scenario below
# must fail (RED) for a real, checkable reason (missing gate binary and/or
# missing canonical DAG config) -- not merely crash on a shell syntax error.

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GATE="$SCRIPT_DIR/module-graph-gate"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
CANONICAL_DAG="$REPO_ROOT/config/module-dependency-dag.json"
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
HASH_C="00000000000000000000000000000000000000000000000000000000000000cc"
HASH_D="00000000000000000000000000000000000000000000000000000000000000dd"

for _h_name in HASH_A HASH_B HASH_C HASH_D; do
  _h_val="${!_h_name}"
  if [ "${#_h_val}" -ne 64 ] || ! printf '%s' "$_h_val" | grep -Eq '^[0-9a-f]{64}$'; then
    printf 'not ok - fixture %s is not exactly 64 lowercase hex chars (len=%s)\n' "$_h_name" "${#_h_val}" >&2
    exit 1
  fi
done

# ============================================================================
# Scenario 1: canonical DAG shape -- unique nodes, PROVEN acyclic edges via a
# deterministic jq graph traversal (not merely "no self-check field"), and
# every verified import-grep edge carries an evidence path plus a literal
# import pattern that actually exists in that path. Also requires that the
# import-grep-verifiable edges Authorization->Tenancy, MenuCatalog->Taxonomy,
# Publication->MenuCatalog, and Team->Identity are all present and marked
# verified in the canonical config/module-dependency-dag.json.
# ============================================================================
dag_shape_ok=1
dag_detail="config/module-dependency-dag.json missing, unreadable, or missing/unverified required edges"
if [ -f "$CANONICAL_DAG" ] && command -v jq >/dev/null 2>&1; then
  node_count=$(jq -r '.nodes | length' "$CANONICAL_DAG" 2>/dev/null)
  unique_node_count=$(jq -r '.nodes | unique | length' "$CANONICAL_DAG" 2>/dev/null)
  required_edges='[
    {"from":"Authorization","to":"Tenancy"},
    {"from":"MenuCatalog","to":"Taxonomy"},
    {"from":"Publication","to":"MenuCatalog"},
    {"from":"Team","to":"Identity"}
  ]'
  edges_present=$(jq -r --argjson req "$required_edges" '
    ($req | length) as $n
    | ([.edges[]?] as $have
       | ($req | map(. as $r
           | (($have[]? | select(.from == $r.from and .to == $r.to and (.verified // false) == true)) | length) > 0
         ) | map(select(. == true)) | length)
      ) as $matched
    | if $matched == $n then "yes" else "no" end
  ' "$CANONICAL_DAG" 2>/dev/null)

  # Deterministic acyclicity proof: iterative topological (Kahn) elimination
  # over the declared node/edge set. If every node can be eliminated the
  # graph is acyclic; if elimination stalls with nodes remaining, a cycle
  # exists among them.
  acyclic=$(jq -r '
    (.nodes // []) as $nodes
    | ([.edges[]? | {from, to}]) as $edges
    | reduce range(0; ($nodes | length) + 1) as $i
        ({remaining: $nodes, removedAny: true};
          if .removedAny and (.remaining | length) > 0 then
            (.remaining) as $rem
            | ($rem | map(. as $n
                | ([$edges[] | select(.to == $n and (.from as $f | $rem | index($f) != null))] | length) == 0
              )) as $noIncoming
            | ($rem | to_entries | map(select($noIncoming[.key])) | map(.value)) as $free
            | if ($free | length) > 0
              then {remaining: ($rem - $free), removedAny: true}
              else {remaining: $rem, removedAny: false}
              end
          else .
          end)
    | if (.remaining | length) == 0 then "acyclic" else "cycle" end
  ' "$CANONICAL_DAG" 2>/dev/null)

  # Every verified edge must carry evidence.path + evidence.pattern, the path
  # must exist under repo root, and the literal pattern must be found there.
  evidence_ok="yes"
  verified_edges_json=$(jq -c '[.edges[]? | select((.verified // false) == true)]' "$CANONICAL_DAG" 2>/dev/null)
  if [ -z "$verified_edges_json" ] || [ "$verified_edges_json" = "null" ]; then
    evidence_ok="no"
  else
    verified_count=$(printf '%s' "$verified_edges_json" | jq 'length' 2>/dev/null)
    if [ -z "$verified_count" ] || [ "$verified_count" -eq 0 ]; then
      evidence_ok="no"
    else
      idx=0
      while [ "$idx" -lt "$verified_count" ]; do
        ev_path=$(printf '%s' "$verified_edges_json" | jq -r ".[$idx].evidence.path // empty" 2>/dev/null)
        ev_pattern=$(printf '%s' "$verified_edges_json" | jq -r ".[$idx].evidence.pattern // empty" 2>/dev/null)
        if [ -z "$ev_path" ] || [ -z "$ev_pattern" ]; then
          evidence_ok="no"
        elif [ ! -f "$REPO_ROOT/$ev_path" ]; then
          evidence_ok="no"
        elif ! grep -F -q -- "$ev_pattern" "$REPO_ROOT/$ev_path" 2>/dev/null; then
          evidence_ok="no"
        fi
        idx=$((idx + 1))
      done
    fi
  fi

  if [ "$node_count" = "$unique_node_count" ] && [ -n "$node_count" ] \
    && [ "$edges_present" = "yes" ] && [ "$acyclic" = "acyclic" ] \
    && [ "$evidence_ok" = "yes" ]; then
    dag_shape_ok=0
  else
    dag_detail="unique_nodes=$([ "$node_count" = "$unique_node_count" ] && echo yes || echo no) required_edges_verified=${edges_present:-no} acyclic=${acyclic:-no} evidence_ok=$evidence_ok"
  fi
fi
report "scenario1 canonical DAG has unique nodes, proven-acyclic verified edges with evidence" $dag_shape_ok "$dag_detail"

# ============================================================================
# Scenario 2: a fully valid manifest passes validate -> PASS
# ============================================================================
manifest "$WORK/s2-valid.json" "{
  \"packageId\": \"pkg-authz-001\",
  \"module\": \"Authorization\",
  \"allowedFiles\": [\"app/Authorization/PolicyResolver.php\"],
  \"writeSet\": [\"app/Authorization/PolicyResolver.php\"],
  \"readSet\": [\"app/Tenancy/TenantContext.php\"],
  \"contractsConsumed\": [\"tenancyContext@$HASH_A\"],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
out=$("$GATE" validate --manifest "$WORK/s2-valid.json" --config "$SPEED_BUDGET_CONFIG" 2>"$WORK/s2.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
report "scenario2 valid manifest validates PASS" $([ "$verdict" = "PASS" ] && echo 0 || echo 1) "got verdict='$verdict' stderr=$(cat "$WORK/s2.err")"

# ============================================================================
# Scenario 3: missing/wrong-typed fields, unknown module, unsafe/duplicate
# paths all fail MANIFEST_INVALID
# ============================================================================
s3_ok=0

manifest "$WORK/s3-missing.json" '{
  "packageId": "pkg-missing-001",
  "module": "Authorization"
}'
out=$("$GATE" validate --manifest "$WORK/s3-missing.json" --config "$SPEED_BUDGET_CONFIG" 2>"$WORK/s3-missing.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
[ "$verdict" = "MANIFEST_INVALID" ] || s3_ok=1

manifest "$WORK/s3-wrongtype.json" "{
  \"packageId\": \"pkg-wrongtype-001\",
  \"module\": \"Authorization\",
  \"allowedFiles\": \"app/Authorization/PolicyResolver.php\",
  \"writeSet\": [\"app/Authorization/PolicyResolver.php\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
out=$("$GATE" validate --manifest "$WORK/s3-wrongtype.json" --config "$SPEED_BUDGET_CONFIG" 2>"$WORK/s3-wrongtype.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
[ "$verdict" = "MANIFEST_INVALID" ] || s3_ok=1

manifest "$WORK/s3-unknownmodule.json" "{
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
out=$("$GATE" validate --manifest "$WORK/s3-unknownmodule.json" --config "$SPEED_BUDGET_CONFIG" 2>"$WORK/s3-unknownmodule.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
[ "$verdict" = "MANIFEST_INVALID" ] || s3_ok=1

manifest "$WORK/s3-unsafepath.json" "{
  \"packageId\": \"pkg-unsafepath-001\",
  \"module\": \"Authorization\",
  \"allowedFiles\": [\"../../etc/passwd\"],
  \"writeSet\": [\"../../etc/passwd\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
out=$("$GATE" validate --manifest "$WORK/s3-unsafepath.json" --config "$SPEED_BUDGET_CONFIG" 2>"$WORK/s3-unsafepath.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
[ "$verdict" = "MANIFEST_INVALID" ] || s3_ok=1

manifest "$WORK/s3-duppath.json" "{
  \"packageId\": \"pkg-duppath-001\",
  \"module\": \"Authorization\",
  \"allowedFiles\": [\"app/Authorization/PolicyResolver.php\", \"app/Authorization/PolicyResolver.php\"],
  \"writeSet\": [\"app/Authorization/PolicyResolver.php\", \"app/Authorization/PolicyResolver.php\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
out=$("$GATE" validate --manifest "$WORK/s3-duppath.json" --config "$SPEED_BUDGET_CONFIG" 2>"$WORK/s3-duppath.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
[ "$verdict" = "MANIFEST_INVALID" ] || s3_ok=1

manifest "$WORK/s3-unsafereadset.json" "{
  \"packageId\": \"pkg-unsafereadset-001\",
  \"module\": \"Authorization\",
  \"allowedFiles\": [\"app/Authorization/PolicyResolver.php\"],
  \"writeSet\": [\"app/Authorization/PolicyResolver.php\"],
  \"readSet\": [\"../../etc/passwd\"],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
out=$("$GATE" validate --manifest "$WORK/s3-unsafereadset.json" --config "$SPEED_BUDGET_CONFIG" 2>"$WORK/s3-unsafereadset.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
[ "$verdict" = "MANIFEST_INVALID" ] || s3_ok=1

manifest "$WORK/s3-dupreadset.json" "{
  \"packageId\": \"pkg-dupreadset-001\",
  \"module\": \"Authorization\",
  \"allowedFiles\": [\"app/Authorization/PolicyResolver.php\"],
  \"writeSet\": [\"app/Authorization/PolicyResolver.php\"],
  \"readSet\": [\"app/Tenancy/TenantContext.php\", \"app/Tenancy/TenantContext.php\"],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
out=$("$GATE" validate --manifest "$WORK/s3-dupreadset.json" --config "$SPEED_BUDGET_CONFIG" 2>"$WORK/s3-dupreadset.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
[ "$verdict" = "MANIFEST_INVALID" ] || s3_ok=1

manifest "$WORK/s3-emptyid.json" "{
  \"packageId\": \"\",
  \"module\": \"Authorization\",
  \"allowedFiles\": [\"app/Authorization/PolicyResolver.php\"],
  \"writeSet\": [\"app/Authorization/PolicyResolver.php\"],
  \"readSet\": [\"app/Tenancy/TenantContext.php\"],
  \"contractsConsumed\": [\"tenancyContext@$HASH_A\"],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
out=$("$GATE" validate --manifest "$WORK/s3-emptyid.json" --config "$SPEED_BUDGET_CONFIG" 2>"$WORK/s3-emptyid.err")
ec=$?
verdict=$(printf '%s\n' "$out" | tail -n1)
{ [ "$verdict" = "MANIFEST_INVALID" ] && [ "$ec" -ne 0 ]; } || s3_ok=1

if command -v jq >/dev/null 2>&1; then
  jq 'del(.moduleFramework.packageManifestSchema)' "$SPEED_BUDGET_CONFIG" >"$WORK/s3-config-noschema.json"
  out=$("$GATE" validate --manifest "$WORK/s2-valid.json" --config "$WORK/s3-config-noschema.json" 2>"$WORK/s3-noschema.err")
  ec=$?
  verdict=$(printf '%s\n' "$out" | tail -n1)
  { [ "$verdict" = "MANIFEST_INVALID" ] || [ "$ec" -ne 0 ]; } && [ "$verdict" != "PASS" ] || s3_ok=1

  jq 'del(.moduleFramework.stewardLanes.lanes)' "$SPEED_BUDGET_CONFIG" >"$WORK/s3-config-nolanes.json"
  out=$("$GATE" validate --manifest "$WORK/s2-valid.json" --config "$WORK/s3-config-nolanes.json" 2>"$WORK/s3-nolanes.err")
  ec=$?
  verdict=$(printf '%s\n' "$out" | tail -n1)
  { [ "$verdict" = "MANIFEST_INVALID" ] || [ "$ec" -ne 0 ]; } && [ "$verdict" != "PASS" ] || s3_ok=1
else
  s3_ok=1
fi

report "scenario3 missing/wrong-type/unknown-module/unsafe-or-dup-path/unsafe-or-dup-readSet/missing-canonical-schema-or-lanes all fail closed" $s3_ok "one or more sub-cases did not fail closed, see $WORK/s3-*.err"

# ============================================================================
# Scenario 4: allowedFiles/writeSet mismatch and malformed contract refs fail
# ============================================================================
s4_ok=0

manifest "$WORK/s4-mismatch.json" "{
  \"packageId\": \"pkg-mismatch-001\",
  \"module\": \"Authorization\",
  \"allowedFiles\": [\"app/Authorization/PolicyResolver.php\"],
  \"writeSet\": [\"app/Authorization/OtherFile.php\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
out=$("$GATE" validate --manifest "$WORK/s4-mismatch.json" --config "$SPEED_BUDGET_CONFIG" 2>"$WORK/s4-mismatch.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
[ "$verdict" = "MANIFEST_INVALID" ] || s4_ok=1

manifest "$WORK/s4-badcontract.json" "{
  \"packageId\": \"pkg-badcontract-001\",
  \"module\": \"Authorization\",
  \"allowedFiles\": [\"app/Authorization/PolicyResolver.php\"],
  \"writeSet\": [\"app/Authorization/PolicyResolver.php\"],
  \"readSet\": [],
  \"contractsConsumed\": [\"tenancyContext@notahexhash\"],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
out=$("$GATE" validate --manifest "$WORK/s4-badcontract.json" --config "$SPEED_BUDGET_CONFIG" 2>"$WORK/s4-badcontract.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
[ "$verdict" = "MANIFEST_INVALID" ] || s4_ok=1

manifest "$WORK/s4-shorthash.json" "{
  \"packageId\": \"pkg-shorthash-001\",
  \"module\": \"Authorization\",
  \"allowedFiles\": [\"app/Authorization/PolicyResolver.php\"],
  \"writeSet\": [\"app/Authorization/PolicyResolver.php\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [\"authzDecision@aa\"],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
out=$("$GATE" validate --manifest "$WORK/s4-shorthash.json" --config "$SPEED_BUDGET_CONFIG" 2>"$WORK/s4-shorthash.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
[ "$verdict" = "MANIFEST_INVALID" ] || s4_ok=1

report "scenario4 writeSet/allowedFiles mismatch and malformed contract refs MANIFEST_INVALID" $s4_ok "one or more sub-cases did not return MANIFEST_INVALID, see $WORK/s4-*.err"

# ============================================================================
# Scenario 5: two same-module, fully disjoint manifests produce NO_CONFLICTS
# -- proving module boundary alone never serializes
# ============================================================================
mkdir -p "$WORK/s5-batch"
manifest "$WORK/s5-batch/pkg-a.json" "{
  \"packageId\": \"pkg-menucat-a\",
  \"module\": \"MenuCatalog\",
  \"allowedFiles\": [\"app/MenuCatalog/ItemA.php\"],
  \"writeSet\": [\"app/MenuCatalog/ItemA.php\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
manifest "$WORK/s5-batch/pkg-b.json" "{
  \"packageId\": \"pkg-menucat-b\",
  \"module\": \"MenuCatalog\",
  \"allowedFiles\": [\"app/MenuCatalog/ItemB.php\"],
  \"writeSet\": [\"app/MenuCatalog/ItemB.php\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
out=$("$GATE" conflicts --manifests "$WORK/s5-batch" --config "$SPEED_BUDGET_CONFIG" 2>"$WORK/s5.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
report "scenario5 same-module fully disjoint manifests NO_CONFLICTS" $([ "$verdict" = "NO_CONFLICTS" ] && echo 0 || echo 1) "got verdict='$verdict' stderr=$(cat "$WORK/s5.err")"

# ============================================================================
# Scenario 6: deterministic pair-local write/write and write/read conflict
# reasons, without contaminating a disjoint third package
# ============================================================================
mkdir -p "$WORK/s6-batch"
manifest "$WORK/s6-batch/pkg-ww1.json" "{
  \"packageId\": \"pkg-ww-1\",
  \"module\": \"Taxonomy\",
  \"allowedFiles\": [\"app/Taxonomy/Term.php\"],
  \"writeSet\": [\"app/Taxonomy/Term.php\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
manifest "$WORK/s6-batch/pkg-ww2.json" "{
  \"packageId\": \"pkg-ww-2\",
  \"module\": \"Taxonomy\",
  \"allowedFiles\": [\"app/Taxonomy/Term.php\"],
  \"writeSet\": [\"app/Taxonomy/Term.php\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
manifest "$WORK/s6-batch/pkg-wr1.json" "{
  \"packageId\": \"pkg-wr-writer\",
  \"module\": \"Identity\",
  \"allowedFiles\": [\"app/Identity/User.php\"],
  \"writeSet\": [\"app/Identity/User.php\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
manifest "$WORK/s6-batch/pkg-wr2.json" "{
  \"packageId\": \"pkg-wr-reader\",
  \"module\": \"Identity\",
  \"allowedFiles\": [\"app/Identity/Reader.php\"],
  \"writeSet\": [\"app/Identity/Reader.php\"],
  \"readSet\": [\"app/Identity/User.php\"],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
manifest "$WORK/s6-batch/pkg-disjoint.json" "{
  \"packageId\": \"pkg-disjoint\",
  \"module\": \"Team\",
  \"allowedFiles\": [\"app/Team/Member.php\"],
  \"writeSet\": [\"app/Team/Member.php\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
out=$("$GATE" conflicts --manifests "$WORK/s6-batch" --config "$SPEED_BUDGET_CONFIG" 2>"$WORK/s6.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
s6_ok=1
if [ "$verdict" = "CONFLICT_EDGES_FOUND" ]; then
  if command -v jq >/dev/null 2>&1; then
    edges_json=$(printf '%s\n' "$out" | sed '$d')
    ww_present=$(printf '%s' "$edges_json" | jq -r '
      [.[] | select(((.packageIds // [.from,.to]) | sort) == ["pkg-ww-1","pkg-ww-2"] and (.reason // "" | test("write/write|writeSet")))] | length' 2>/dev/null)
    wr_present=$(printf '%s' "$edges_json" | jq -r '
      [.[] | select(((.packageIds // [.from,.to]) | sort) == ["pkg-wr-reader","pkg-wr-writer"] and (.reason // "" | test("write/read|readSet")))] | length' 2>/dev/null)
    disjoint_absent=$(printf '%s' "$edges_json" | jq -r '
      [.[] | select(((.packageIds // [.from,.to]) | index("pkg-disjoint")) != null)] | length' 2>/dev/null)
    if [ "${ww_present:-0}" -ge 1 ] && [ "${wr_present:-0}" -ge 1 ] && [ "${disjoint_absent:-0}" -eq 0 ]; then
      s6_ok=0
    fi
  fi
fi
report "scenario6 deterministic pair-local write/write and write/read conflicts, disjoint third package clean" $s6_ok "got verdict='$verdict' stderr=$(cat "$WORK/s6.err") stdout=$out"

# ============================================================================
# Scenario 7: contract-change/consume, table/migration, and shared-surface
# intersections report exact reasons
# ============================================================================
mkdir -p "$WORK/s7-batch"
manifest "$WORK/s7-batch/pkg-contract-changer.json" "{
  \"packageId\": \"pkg-contract-changer\",
  \"module\": \"Publication\",
  \"allowedFiles\": [\"app/Publication/Publisher.php\"],
  \"writeSet\": [\"app/Publication/Publisher.php\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [\"publicationEvent@$HASH_A\"],
  \"tablesOrMigrationsTouched\": [\"publications\"],
  \"sharedSurfacesTouched\": [\"routes\"],
  \"dependsOnPackages\": []
}"
manifest "$WORK/s7-batch/pkg-contract-consumer.json" "{
  \"packageId\": \"pkg-contract-consumer\",
  \"module\": \"MenuCatalog\",
  \"allowedFiles\": [\"app/MenuCatalog/Listener.php\"],
  \"writeSet\": [\"app/MenuCatalog/Listener.php\"],
  \"readSet\": [],
  \"contractsConsumed\": [\"publicationEvent@$HASH_A\"],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [\"publications\"],
  \"sharedSurfacesTouched\": [\"routes\"],
  \"dependsOnPackages\": []
}"
out=$("$GATE" conflicts --manifests "$WORK/s7-batch" --config "$SPEED_BUDGET_CONFIG" 2>"$WORK/s7.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
s7_ok=1
if [ "$verdict" = "CONFLICT_EDGES_FOUND" ]; then
  if command -v jq >/dev/null 2>&1; then
    edges_json=$(printf '%s\n' "$out" | sed '$d')
    contract_reason=$(printf '%s' "$edges_json" | jq -r '[.[] | select(.reason // "" | test("contract"))] | length' 2>/dev/null)
    table_reason=$(printf '%s' "$edges_json" | jq -r '[.[] | select(.reason // "" | test("table|migration"))] | length' 2>/dev/null)
    surface_reason=$(printf '%s' "$edges_json" | jq -r '[.[] | select(.reason // "" | test("sharedSurface|shared surface|routes"))] | length' 2>/dev/null)
    if [ "${contract_reason:-0}" -ge 1 ] && [ "${table_reason:-0}" -ge 1 ] && [ "${surface_reason:-0}" -ge 1 ]; then
      s7_ok=0
    fi
  fi
fi
report "scenario7 contract-change/consume, table/migration, shared-surface intersections report exact reasons" $s7_ok "got verdict='$verdict' stderr=$(cat "$WORK/s7.err") stdout=$out"

# ============================================================================
# Scenario 8: active manifest dependsOnPackages target present in the same
# input directory reports unmet-dependency conflict; a target absent from the
# batch is treated as already merged. Also runs the module-graph-gate
# self-check under speed-gate with targetedTestCount 8 and one test file.
# ============================================================================
mkdir -p "$WORK/s8-batch-present"
manifest "$WORK/s8-batch-present/pkg-base.json" "{
  \"packageId\": \"pkg-base\",
  \"module\": \"Tenancy\",
  \"allowedFiles\": [\"app/Tenancy/Base.php\"],
  \"writeSet\": [\"app/Tenancy/Base.php\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": []
}"
manifest "$WORK/s8-batch-present/pkg-dependent.json" "{
  \"packageId\": \"pkg-dependent\",
  \"module\": \"Tenancy\",
  \"allowedFiles\": [\"app/Tenancy/Dependent.php\"],
  \"writeSet\": [\"app/Tenancy/Dependent.php\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": [\"pkg-base\"]
}"
out=$("$GATE" conflicts --manifests "$WORK/s8-batch-present" --config "$SPEED_BUDGET_CONFIG" 2>"$WORK/s8-present.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
s8_present_ok=1
if [ "$verdict" = "CONFLICT_EDGES_FOUND" ]; then
  if command -v jq >/dev/null 2>&1; then
    edges_json=$(printf '%s\n' "$out" | sed '$d')
    unmet_dep=$(printf '%s' "$edges_json" | jq -r '[.[] | select(.reason // "" | test("depend"))] | length' 2>/dev/null)
    [ "${unmet_dep:-0}" -ge 1 ] && s8_present_ok=0
  fi
fi
report "scenario8a in-batch unmet dependsOnPackages target reports conflict" $s8_present_ok "got verdict='$verdict' stderr=$(cat "$WORK/s8-present.err") stdout=$out"

mkdir -p "$WORK/s8-batch-absent"
manifest "$WORK/s8-batch-absent/pkg-dependent-only.json" "{
  \"packageId\": \"pkg-dependent-only\",
  \"module\": \"Tenancy\",
  \"allowedFiles\": [\"app/Tenancy/DependentOnly.php\"],
  \"writeSet\": [\"app/Tenancy/DependentOnly.php\"],
  \"readSet\": [],
  \"contractsConsumed\": [],
  \"contractsChanged\": [],
  \"tablesOrMigrationsTouched\": [],
  \"sharedSurfacesTouched\": [],
  \"dependsOnPackages\": [\"pkg-not-in-this-batch\"]
}"
out=$("$GATE" conflicts --manifests "$WORK/s8-batch-absent" --config "$SPEED_BUDGET_CONFIG" 2>"$WORK/s8-absent.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
report "scenario8b out-of-batch dependsOnPackages target treated already merged NO_CONFLICTS" $([ "$verdict" = "NO_CONFLICTS" ] && echo 0 || echo 1) "got verdict='$verdict' stderr=$(cat "$WORK/s8-absent.err")"

mkdir -p "$WORK/s8-batch-malformed"
manifest "$WORK/s8-batch-malformed/pkg-broken.json" '{ this is not valid json'
out=$("$GATE" conflicts --manifests "$WORK/s8-batch-malformed" --config "$SPEED_BUDGET_CONFIG" 2>"$WORK/s8-malformed.err")
ec=$?
verdict=$(printf '%s\n' "$out" | tail -n1)
report "scenario8d single malformed manifest in batch reports CONFLICT_EDGES_FOUND nonzero, never NO_CONFLICTS" $([ "$verdict" = "CONFLICT_EDGES_FOUND" ] && [ "$ec" -ne 0 ] && echo 0 || echo 1) "got verdict='$verdict' exit=$ec stderr=$(cat "$WORK/s8-malformed.err")"

mkdir -p "$WORK/s8-batch-empty"
out=$("$GATE" conflicts --manifests "$WORK/s8-batch-empty" --config "$WORK/s3-config-noschema.json" 2>"$WORK/s8-emptybadconfig.err")
ec=$?
verdict=$(printf '%s\n' "$out" | tail -n1)
report "scenario8e empty batch with malformed/missing canonical config fails closed, never NO_CONFLICTS" $([ "$verdict" != "NO_CONFLICTS" ] && [ "$ec" -ne 0 ] && echo 0 || echo 1) "got verdict='$verdict' exit=$ec stderr=$(cat "$WORK/s8-emptybadconfig.err")"

# --- Scenario 8c: speed-gate self-check, targetedTestCount 8, one test file ---
SPEED_GATE="$SCRIPT_DIR/speed-gate"
s8_selfcheck_ok=1
if [ -x "$SPEED_GATE" ]; then
  cat >"$WORK/s8-selfcheck-config.json" <<'JSON'
{
  "checkpointCadenceMinutesMax": 20,
  "lanes": {
    "normal": { "targetedTestMax": 8, "targetedTestMin": 3, "testFilesMax": 2, "reviewerFullSuiteRunsMax": 0 },
    "microHotfix": { "targetedTestMax": 3, "testFilesMax": 1, "adjacentMicroFixesMax": 3 },
    "highRisk": { "reviewerFullSuiteRunsMax": 0 }
  },
  "highRiskPathPatterns": ["app/**/Billing/**", "app/**/Tenancy/**"],
  "highRiskSemanticClasses": ["billing-or-payment-calculation"]
}
JSON
  manifest "$WORK/s8-selfcheck-manifest.json" '{
    "lane": "normal",
    "targetedTestCount": 8,
    "testFilesChanged": 1,
    "changedPaths": ["scripts/module-graph-gate.test.sh"],
    "elapsedCheckpointMinutes": 5,
    "snapshotHash": "module-graph-gate-red"
  }'
  out=$("$SPEED_GATE" check --manifest "$WORK/s8-selfcheck-manifest.json" --config "$WORK/s8-selfcheck-config.json" 2>"$WORK/s8-selfcheck.err")
  verdict=$(printf '%s\n' "$out" | tail -n1)
  [ "$verdict" = "PASS" ] && s8_selfcheck_ok=0
fi
report "scenario8c speed-gate self-check targetedTestCount=8 one test file PASS" $s8_selfcheck_ok "speed-gate missing/non-executable or verdict != PASS, see $WORK/s8-selfcheck.err"

printf '\n%d passed, %d failed\n' "$pass" "$fail"
[ "$fail" -eq 0 ]
