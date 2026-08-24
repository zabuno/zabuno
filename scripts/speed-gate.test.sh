#!/usr/bin/env bash
# Deterministic shell integration test for scripts/speed-gate (SP-01, frozen scenarios 1-8).
#
# CLI contract this test assumes for the not-yet-implemented scripts/speed-gate:
#   scripts/speed-gate check --manifest <package-manifest.json> --config <speed-budget.json> [--now-minutes N]
#     - reads a JSON package manifest describing one checkpoint/package attempt
#     - reads config/development-speed-budget.json-shaped budget config
#     - prints a single verdict token on stdout as the LAST line: PASS, BATCH_REQUIRED,
#       HIGH_RISK, or CHECKPOINT_BLOCKED
#     - exits 0 when verdict is PASS, non-zero otherwise
#   scripts/speed-gate docs-scan --docs-root <dir>
#     - scans docs for stray duplicated numeric speed thresholds instead of a
#       config/development-speed-budget.json pointer
#     - prints CLEAN or DUPLICATE_THRESHOLDS as the last stdout line; exits 0 on CLEAN
#
# No implementation exists yet at scripts/speed-gate: every scenario below must fail (RED).

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GATE="$SCRIPT_DIR/speed-gate"
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

write_config() {
  cat >"$WORK/development-speed-budget.json" <<'JSON'
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
}

manifest() {
  local file="$1" body="$2"
  printf '%s' "$body" >"$file"
}

# --- Scenario 1: normal lane 3-8 targeted tests, <=2 files passes; 9th rejected BATCH_REQUIRED ---
write_config
manifest "$WORK/s1-pass.json" '{
  "lane": "normal",
  "targetedTestCount": 6,
  "testFilesChanged": 2,
  "changedPaths": ["resources/js/components/Foo.tsx"],
  "elapsedCheckpointMinutes": 10,
  "snapshotHash": "s1a"
}'
out=$("$GATE" check --manifest "$WORK/s1-pass.json" --config "$WORK/development-speed-budget.json" 2>"$WORK/s1-pass.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
report "scenario1 normal 6/2 passes" $([ "$verdict" = "PASS" ] && echo 0 || echo 1) "got verdict='$verdict' stderr=$(cat "$WORK/s1-pass.err")"

manifest "$WORK/s1-reject.json" '{
  "lane": "normal",
  "targetedTestCount": 9,
  "testFilesChanged": 2,
  "changedPaths": ["resources/js/components/Foo.tsx"],
  "elapsedCheckpointMinutes": 10,
  "snapshotHash": "s1b"
}'
out=$("$GATE" check --manifest "$WORK/s1-reject.json" --config "$WORK/development-speed-budget.json" 2>"$WORK/s1-reject.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
report "scenario1 normal 9 tests rejected BATCH_REQUIRED" $([ "$verdict" = "BATCH_REQUIRED" ] && echo 0 || echo 1) "got verdict='$verdict' stderr=$(cat "$WORK/s1-reject.err")"

# --- Scenario 1 regression: malformed numeric manifest fields must fail closed (exit 2, never PASS) ---
malformed_ok=0
for field in elapsedCheckpointMinutes targetedTestCount testFilesChanged adjacentMicroFixCount reviewerFullSuiteRunsRequested fullLocalQaRunsSoFar; do
  manifest "$WORK/s1-malformed-$field.json" "{
    \"lane\": \"normal\",
    \"targetedTestCount\": 4,
    \"testFilesChanged\": 1,
    \"changedPaths\": [\"resources/js/components/Foo.tsx\"],
    \"elapsedCheckpointMinutes\": 5,
    \"snapshotHash\": \"s1-malformed-$field\",
    \"$field\": \"not-a-number\"
  }"
  out=$("$GATE" check --manifest "$WORK/s1-malformed-$field.json" --config "$WORK/development-speed-budget.json" 2>"$WORK/s1-malformed-$field.err")
  status=$?
  verdict=$(printf '%s\n' "$out" | tail -n1)
  if [ "$status" -ne 2 ] || [ "$verdict" = "PASS" ]; then
    malformed_ok=1
    printf '  -- malformed field %s: exit=%s verdict=%s\n' "$field" "$status" "$verdict" >>"$WORK/s1-malformed.detail"
  fi
done
report "scenario1 malformed numeric manifest fields fail closed (exit 2, never PASS)" $malformed_ok "one or more malformed fields did not fail closed, see $WORK/s1-malformed.detail and $WORK/s1-malformed-*.err"

# --- Scenario 2: Billing/Tenancy path forces highRisk ---
manifest "$WORK/s2.json" '{
  "lane": "normal",
  "targetedTestCount": 4,
  "testFilesChanged": 1,
  "changedPaths": ["app/Application/Billing/UseCase/ChargeInvoice.php"],
  "elapsedCheckpointMinutes": 5,
  "snapshotHash": "s2"
}'
out=$("$GATE" check --manifest "$WORK/s2.json" --config "$WORK/development-speed-budget.json" 2>"$WORK/s2.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
report "scenario2 billing path forces HIGH_RISK" $([ "$verdict" = "HIGH_RISK" ] && echo 0 || echo 1) "got verdict='$verdict' stderr=$(cat "$WORK/s2.err")"

# --- Scenario 3: semantic risk override escalates without a path match ---
manifest "$WORK/s3.json" '{
  "lane": "normal",
  "targetedTestCount": 4,
  "testFilesChanged": 1,
  "changedPaths": ["resources/js/components/TeamMemberList.tsx"],
  "semanticRiskClasses": ["billing-or-payment-calculation"],
  "elapsedCheckpointMinutes": 5,
  "snapshotHash": "s3"
}'
out=$("$GATE" check --manifest "$WORK/s3.json" --config "$WORK/development-speed-budget.json" 2>"$WORK/s3.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
report "scenario3 semantic override escalates to HIGH_RISK" $([ "$verdict" = "HIGH_RISK" ] && echo 0 || echo 1) "got verdict='$verdict' stderr=$(cat "$WORK/s3.err")"

# --- Scenario 4: unchanged snapshot rerun short-circuits, does not spend another QA execution ---
manifest "$WORK/s4a.json" '{
  "lane": "normal",
  "targetedTestCount": 4,
  "testFilesChanged": 1,
  "changedPaths": ["resources/js/components/Foo.tsx"],
  "elapsedCheckpointMinutes": 5,
  "snapshotHash": "same-hash",
  "fullLocalQaRunsSoFar": 1
}'
"$GATE" check --manifest "$WORK/s4a.json" --config "$WORK/development-speed-budget.json" >/dev/null 2>"$WORK/s4a.err"
manifest "$WORK/s4b.json" '{
  "lane": "normal",
  "targetedTestCount": 4,
  "testFilesChanged": 1,
  "changedPaths": ["resources/js/components/Foo.tsx"],
  "elapsedCheckpointMinutes": 5,
  "snapshotHash": "same-hash",
  "fullLocalQaRunsSoFar": 1,
  "requestAnotherFullLocalQa": true
}'
out=$("$GATE" check --manifest "$WORK/s4b.json" --config "$WORK/development-speed-budget.json" 2>"$WORK/s4b.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
qa_spent=$(printf '%s\n' "$out" | grep -o '"fullLocalQaRunsSpent":[0-9]*' | grep -o '[0-9]*$')
report "scenario4 unchanged snapshot short-circuits, no extra QA spend" $([ "$verdict" = "PASS" ] && [ "${qa_spent:-1}" = "0" ] && echo 0 || echo 1) "got verdict='$verdict' qa_spent='${qa_spent:-}' stderr=$(cat "$WORK/s4b.err")"

# --- Scenario 5: three adjacent micro-fixes may batch, a fourth requires split/reject ---
manifest "$WORK/s5-ok.json" '{
  "lane": "microHotfix",
  "adjacentMicroFixCount": 3,
  "sameComponentOrJourney": true,
  "targetedTestCount": 3,
  "testFilesChanged": 1,
  "changedPaths": ["resources/js/components/ConfirmDialog.tsx"],
  "elapsedCheckpointMinutes": 8,
  "snapshotHash": "s5a"
}'
out=$("$GATE" check --manifest "$WORK/s5-ok.json" --config "$WORK/development-speed-budget.json" 2>"$WORK/s5-ok.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
report "scenario5 three adjacent micro-fixes batch and pass" $([ "$verdict" = "PASS" ] && echo 0 || echo 1) "got verdict='$verdict' stderr=$(cat "$WORK/s5-ok.err")"

manifest "$WORK/s5-reject.json" '{
  "lane": "microHotfix",
  "adjacentMicroFixCount": 4,
  "sameComponentOrJourney": true,
  "targetedTestCount": 3,
  "testFilesChanged": 1,
  "changedPaths": ["resources/js/components/ConfirmDialog.tsx"],
  "elapsedCheckpointMinutes": 8,
  "snapshotHash": "s5b"
}'
out=$("$GATE" check --manifest "$WORK/s5-reject.json" --config "$WORK/development-speed-budget.json" 2>"$WORK/s5-reject.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
report "scenario5 fourth adjacent micro-fix requires BATCH_REQUIRED split" $([ "$verdict" = "BATCH_REQUIRED" ] && echo 0 || echo 1) "got verdict='$verdict' stderr=$(cat "$WORK/s5-reject.err")"

# --- Scenario 6: reviewer full-suite execution budget is always zero ---
manifest "$WORK/s6.json" '{
  "lane": "normal",
  "targetedTestCount": 4,
  "testFilesChanged": 1,
  "changedPaths": ["resources/js/components/Foo.tsx"],
  "elapsedCheckpointMinutes": 5,
  "snapshotHash": "s6",
  "reviewerFullSuiteRunsRequested": 1
}'
out=$("$GATE" check --manifest "$WORK/s6.json" --config "$WORK/development-speed-budget.json" 2>"$WORK/s6.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
report "scenario6 reviewer full-suite budget always zero rejects request" $([ "$verdict" != "PASS" ] && echo 0 || echo 1) "got verdict='$verdict' stderr=$(cat "$WORK/s6.err")"

# --- Scenario 7: checkpoint over 20 minutes blocks the next phase for all four lanes ---
overtime_ok=0
for lane in prototype microHotfix normal highRisk; do
  manifest "$WORK/s7-$lane.json" "{
    \"lane\": \"$lane\",
    \"targetedTestCount\": 2,
    \"testFilesChanged\": 1,
    \"changedPaths\": [\"resources/js/components/Foo.tsx\"],
    \"elapsedCheckpointMinutes\": 21,
    \"snapshotHash\": \"s7-$lane\"
  }"
  out=$("$GATE" check --manifest "$WORK/s7-$lane.json" --config "$WORK/development-speed-budget.json" 2>"$WORK/s7-$lane.err")
  verdict=$(printf '%s\n' "$out" | tail -n1)
  [ "$verdict" = "CHECKPOINT_BLOCKED" ] || overtime_ok=1
done
report "scenario7 >20min checkpoint blocks next phase in all four lanes" $overtime_ok "one or more lanes did not return CHECKPOINT_BLOCKED, see $WORK/s7-*.err"

# --- Scenario 8: canonical docs contain zero stray duplicated numeric speed thresholds ---
mkdir -p "$WORK/docs-clean"
cat >"$WORK/docs-clean/27-QA-ACCEPTANCE-VIBECODING.md" <<'MD'
See config/development-speed-budget.json for checkpoint cadence and test budgets.
MD
out=$("$GATE" docs-scan --docs-root "$WORK/docs-clean" 2>"$WORK/s8-clean.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
report "scenario8 clean docs report CLEAN" $([ "$verdict" = "CLEAN" ] && echo 0 || echo 1) "got verdict='$verdict' stderr=$(cat "$WORK/s8-clean.err")"

mkdir -p "$WORK/docs-dirty"
cat >"$WORK/docs-dirty/27-QA-ACCEPTANCE-VIBECODING.md" <<'MD'
Checkpoints must not exceed 20 minutes. Normal lane allows up to 8 targeted tests.
MD
out=$("$GATE" docs-scan --docs-root "$WORK/docs-dirty" 2>"$WORK/s8-dirty.err")
verdict=$(printf '%s\n' "$out" | tail -n1)
report "scenario8 duplicated numeric thresholds reported DUPLICATE_THRESHOLDS" $([ "$verdict" = "DUPLICATE_THRESHOLDS" ] && echo 0 || echo 1) "got verdict='$verdict' stderr=$(cat "$WORK/s8-dirty.err")"

printf '\n%d passed, %d failed\n' "$pass" "$fail"
[ "$fail" -eq 0 ]
