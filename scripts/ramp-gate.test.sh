#!/usr/bin/env bash
# Deterministic shell integration test for scripts/ramp-gate
# (fast-delivery genome overlay item 8A, frozen scenarios 1-8).
#
# CLI contract this test assumes for the not-yet-implemented
# scripts/ramp-gate:
#
#   scripts/ramp-gate evaluate --ledger <ledger.json> --guardian <guardian.json> \
#     --config <development-speed-budget.json> --now-epoch-day <integer>
#
#     - config owns every numeric threshold for ramp promotion (per-stage
#       nextStage, minimum observed days, minimum ready backlog, minimum
#       trailing qualifying waves, and stage-specific evidence guardrails).
#       This test never injects a synthesized policy object into the config
#       file; it only ever copies the real repository config
#       (config/development-speed-budget.json) and, where a scenario
#       requires it, changes moduleFramework.rampStage (a field that already
#       exists in that file) or -- for the single broken-adjacency subcase --
#       moduleFramework.rampStages (also already present). The fixture
#       ledgers below are built to the already-recorded canonical evidence
#       boundaries so that, once the real policy is implemented against
#       those same recorded boundaries, these fixtures exercise it honestly;
#       the script under test must read every threshold number from
#       whatever --config file it is given, never hardcode them
#     - ledger.currentStage must equal config moduleFramework.rampStage;
#       only the immediate next stage in the sequence 0,4,12,25,50,100 may
#       ever be recommended/promoted to
#     - stage 0 is a single-worker bootstrap ceiling; effective admission is
#       always min(Guardian recommendation, remaining current-stage
#       capacity), and remaining capacity while currentStage=="0" is
#       max(0, 1 - activeWorkers)
#     - Guardian allowNewWorker=false or recommendedNewWorkers=0 forces HOLD
#       and effectiveNewWorkersMax=0 regardless of otherwise-eligible
#       promotion evidence
#     - PROMOTE requires the immediate next stage's configured observed-day
#       window, ready backlog, and trailing consecutive qualifying waves (a
#       qualifying wave has mergeMetricsStable, ciStable, allModulesGreen all
#       true, rollbackTriggeredPackages and crossPackageFileConflicts
#       empty/zero, highRiskProductionRegressions==0,
#       conflictFalseNegatives==0, reviewerTestWriteViolations==0,
#       checkpointP95Minutes <= checkpointCadenceMinutesMax, and
#       stewardLaneP95Ratio within the configured ceiling for that
#       transition). Beyond stage 4, promotion additionally requires the
#       genuine 30-day aggregate window evidence: A/B pair minimum, CI
#       pass-through minimum, and escaped-defect/rework non-regression
#       against the window's own baseline fields
#     - throughput multiplier = window.acceptedVerticalSlices /
#       window.baselineAcceptedVerticalSlices, always reported when the
#       window is valid; claimEligible is a separate boolean (multiplier >=
#       the configured programThroughputObjective.targetThroughputMultiplier.min
#       AND the promotion evidence guardrails above hold) and is never
#       fabricated/assumed true
#     - ROLLBACK takes precedence over PROMOTE (checked first): any wave in
#       the trailing set for currentStage with highRiskProductionRegressions
#       > 0 or conflictFalseNegatives > 0, or window.escapedDefectRate >
#       window.escapedDefectBaseline, or window.reworkRate >
#       window.reworkBaseline, forces ROLLBACK with rollbackTarget equal to
#       the stage immediately before currentStage in the sequence (stage 0
#       can never roll back and instead HOLDs)
#     - stage 100 is terminal: no nextStage exists, so it can only ever HOLD
#       or ROLLBACK, never PROMOTE
#     - pure evaluator: never creates workers, never mutates the ledger,
#       Guardian file, config, or Git
#     - fails closed with no decision JSON on stdout, last line exactly one
#       of LEDGER_INVALID / GUARDIAN_INVALID / CONFIG_ERROR, nonzero exit,
#       for: malformed JSON, wrong field types, negative/non-finite metrics,
#       duplicate waveId, a wave with endedAtEpochDay in the future relative
#       to --now-epoch-day or endedAtEpochDay < startedAtEpochDay, ledger
#       currentStage != config rampStage, a config whose stage-adjacency
#       field is broken, or a malformed Guardian file
#     - on success, prints exactly one compact deterministic JSON object on
#       stdout, then exactly one of HOLD / PROMOTE / ROLLBACK on the last
#       line, exit 0
#     - identical logical ledger input with JSON keys in a different order
#       produces a byte-identical decision JSON
#
# No implementation exists yet at scripts/ramp-gate: every scenario below
# asserts the REAL target behavior (verdict token, exit code, and specific
# jq-extracted decision keys), so every scenario must currently fail (RED)
# because the ramp-gate binary itself is absent -- not because of a shell
# syntax error in this test, and not because this test pre-reads a
# not-yet-implemented config policy field. This test does NOT assert
# brittle whole-object snapshots (except the explicit byte-identical
# determinism subcase); it asserts exact token/exit/key invariants.

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GATE="$SCRIPT_DIR/ramp-gate"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
BASE_CONFIG="$REPO_ROOT/config/development-speed-budget.json"
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

HASH_OK="1111111111111111111111111111111111111111111111111111111111111111"
HASH_OK="${HASH_OK:0:64}"
if [ "${#HASH_OK}" -ne 64 ] || ! printf '%s' "$HASH_OK" | grep -Eq '^[0-9a-f]{64}$'; then
  printf 'not ok - fixture HASH_OK is not exactly 64 lowercase hex chars (len=%s)\n' "${#HASH_OK}" >&2
  exit 1
fi

# make_config <stage> -- copies BASE_CONFIG unmodified except
# moduleFramework.rampStage. No policy object is injected: numeric
# thresholds are whatever the real repository config carries once
# implemented.
make_config() {
  local stage="$1"
  local out="$WORK/config-stage$stage.json"
  jq --arg stage "$stage" '.moduleFramework.rampStage = $stage' "$BASE_CONFIG" >"$out"
  printf '%s' "$out"
}

# guardian <allow-bool> <recommended-int> <file>
guardian() {
  local allow="$1" rec="$2" file="$3"
  printf '{"allowNewWorker":%s,"recommendedNewWorkers":%s,"evaluatedAtEpochDay":0}\n' "$allow" "$rec" >"$file"
}

# wave <waveId> <stage> <start> <end> <p95min> <lanep95> <confFN> <reviewerViol> <mergeStable> <ciStable> <allGreen> <rollbackPkgs-json> <crossConflicts-json> <highRiskRegr> <flowEff> <flowBaseline> <unstableLanes-json>
wave() {
  cat <<JSON
{
  "waveId": "$1",
  "stage": "$2",
  "startedAtEpochDay": $3,
  "endedAtEpochDay": $4,
  "checkpointP95Minutes": $5,
  "stewardLaneP95Ratio": $6,
  "conflictFalseNegatives": $7,
  "reviewerTestWriteViolations": $8,
  "mergeMetricsStable": $9,
  "ciStable": ${10},
  "allModulesGreen": ${11},
  "rollbackTriggeredPackages": ${12},
  "crossPackageFileConflicts": ${13},
  "highRiskProductionRegressions": ${14},
  "flowEfficiency": ${15},
  "flowEfficiencyBaseline": ${16},
  "unstableStewardLanes": ${17}
}
JSON
}

# good_wave satisfies every stage's recorded canonical evidence boundary at
# once: checkpoint p95 <=20 (cadence ceiling), lane ratio 0.10 (<= the
# tightest configured 0.20 ceiling), zero conflict false negatives and
# reviewer test-write violations, merge/CI stable, all modules green, no
# rollback-triggered packages or cross-package conflicts, no high-risk
# production regressions, flow efficiency at/above baseline, no unstable
# steward lanes.
good_wave() {
  local id="$1" stage="$2" start="$3" end="$4"
  wave "$id" "$stage" "$start" "$end" 20 0.10 0 0 true true true '[]' 0 0 0.9 0.8 '[]'
}

# ledger_file <stage> <backlog> <window-json> <waves-json-array> <file> [<activeWorkers>]
# activeWorkers defaults to 1; stage-0 scenarios that expect the bootstrap
# ceiling to still admit one worker this evaluation pass activeWorkers=0
# explicitly.
ledger_file() {
  local stage="$1" backlog="$2" window="$3" waves="$4" file="$5"
  local activeWorkers="${6:-1}"
  cat <<JSON >"$file"
{
  "schemaVersion": 1,
  "currentStage": "$stage",
  "activeWorkers": $activeWorkers,
  "readyBacklogCount": $backlog,
  "window": $window,
  "waves": $waves
}
JSON
}

# window <start> <end> <ab> <accepted> <baselineAccepted> <escapedRate> <escapedBaseline> <reworkRate> <reworkBaseline> <ci>
win() {
  cat <<JSON
{
  "startEpochDay": $1,
  "endEpochDay": $2,
  "abPairCount": $3,
  "acceptedVerticalSlices": $4,
  "baselineAcceptedVerticalSlices": $5,
  "escapedDefectRate": $6,
  "escapedDefectBaseline": $7,
  "reworkRate": $8,
  "reworkBaseline": $9,
  "ciPassThroughRate": ${10}
}
JSON
}

# run_gate <ledger> <guardian> <config> <now>
run_gate() {
  local ledger="$1" guard="$2" cfg="$3" now="$4"
  local errfile="$WORK/last.err"
  out=$("$GATE" evaluate --ledger "$ledger" --guardian "$guard" --config "$cfg" --now-epoch-day "$now" 2>"$errfile")
  ec=$?
  verdict=$(printf '%s\n' "$out" | tail -n1)
  json=$(printf '%s\n' "$out" | sed '$d')
  errtext=$(cat "$errfile")
}

# ============================================================================
# Scenario 1: stage 0 bootstrap. Evidence at stage 0 meets the recorded
# canonical 0->4 boundary (>=7 observed days, ready backlog >=8, 2 trailing
# qualifying waves) and is sufficient to advisory-recommend stage 4, yet
# effective admission this evaluation stays capped at the stage-0 bootstrap
# ceiling of 1 (activeWorkers=0, so remaining capacity is 1) even though
# Guardian recommends more workers than that.
# ============================================================================
S1_CFG=$(make_config 0)
S1_WAVES='['"$(good_wave w0-1 0 0 5)"','"$(good_wave w0-2 0 5 10)"']'
S1_WINDOW=$(win 0 10 0 0 0 0 0 0 0 1)
ledger_file 0 8 "$S1_WINDOW" "$S1_WAVES" "$WORK/s1-ledger.json" 0
guardian true 3 "$WORK/s1-guardian.json"
run_gate "$WORK/s1-ledger.json" "$WORK/s1-guardian.json" "$S1_CFG" 10
s1_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "PROMOTE" ] && command -v jq >/dev/null 2>&1; then
  admit=$(printf '%s' "$json" | jq -r '.effectiveNewWorkersMax // .admittedWorkers // -1' 2>/dev/null)
  recstage=$(printf '%s' "$json" | jq -r '.recommendedStage // .promoteToStage // empty' 2>/dev/null)
  if [ "$admit" = "1" ] && [ "$recstage" = "4" ]; then
    s1_ok=0
  fi
fi
report "scenario1 stage0 eligible advisory evidence recommends stage 4 but admission stays bootstrap-capped at 1" $s1_ok "expected PROMOTE with effectiveNewWorkersMax==1 and recommendedStage==4; got ec=$ec verdict='$verdict' json=$json stderr=$errtext"

# ============================================================================
# Scenario 2: insufficient observed days, insufficient ready backlog, and
# insufficient trailing qualifying waves each independently yield HOLD
# against the same recorded 0->4 boundary (>=7 days, backlog >=8, 2 waves).
# ============================================================================
S2_CFG=$(make_config 0)

# 2a: too few observed days (window spans 5 days, boundary requires 7)
S2_WAVES_A='['"$(good_wave w2a-1 0 0 2)"','"$(good_wave w2a-2 0 2 5)"']'
S2_WINDOW_A=$(win 0 5 0 0 0 0 0 0 0 1)
ledger_file 0 8 "$S2_WINDOW_A" "$S2_WAVES_A" "$WORK/s2a-ledger.json"
guardian true 3 "$WORK/s2a-guardian.json"
run_gate "$WORK/s2a-ledger.json" "$WORK/s2a-guardian.json" "$S2_CFG" 5
s2a_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "HOLD" ]; then s2a_ok=0; fi

# 2b: too little ready backlog (boundary requires 8)
S2_WAVES_B='['"$(good_wave w2b-1 0 0 5)"','"$(good_wave w2b-2 0 5 10)"']'
S2_WINDOW_B=$(win 0 10 0 0 0 0 0 0 0 1)
ledger_file 0 3 "$S2_WINDOW_B" "$S2_WAVES_B" "$WORK/s2b-ledger.json"
guardian true 3 "$WORK/s2b-guardian.json"
run_gate "$WORK/s2b-ledger.json" "$WORK/s2b-guardian.json" "$S2_CFG" 10
s2b_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "HOLD" ]; then s2b_ok=0; fi

# 2c: too few trailing qualifying waves (only 1 qualifying wave; boundary
# requires 2 -- the second wave is disqualified by a conflict false negative)
S2_WAVES_C='['"$(good_wave w2c-1 0 0 5)"','"$(wave w2c-2 0 5 10 20 0.10 1 0 true true true '[]' 0 0 0.9 0.8 '[]')"']'
S2_WINDOW_C=$(win 0 10 0 0 0 0 0 0 0 1)
ledger_file 0 8 "$S2_WINDOW_C" "$S2_WAVES_C" "$WORK/s2c-ledger.json"
guardian true 3 "$WORK/s2c-guardian.json"
run_gate "$WORK/s2c-ledger.json" "$WORK/s2c-guardian.json" "$S2_CFG" 10
s2c_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "HOLD" ]; then s2c_ok=0; fi

s2_ok=$((s2a_ok + s2b_ok + s2c_ok))
[ "$s2_ok" -gt 0 ] && s2_ok=1
report "scenario2 insufficient observed days/backlog/trailing qualifying waves each yield HOLD" $s2_ok "expected HOLD (exit 0) in all three subcases; got days=$s2a_ok backlog=$s2b_ok waves=$s2c_ok"

# ============================================================================
# Scenario 3: Guardian allowNewWorker=false and Guardian
# recommendedNewWorkers=0 subcases each force HOLD and zero effective
# admission, even though promotion-worthy 0->4 evidence is otherwise present.
# ============================================================================
S3_CFG=$(make_config 0)
S3_WAVES='['"$(good_wave w3-1 0 0 5)"','"$(good_wave w3-2 0 5 10)"']'
S3_WINDOW=$(win 0 10 0 0 0 0 0 0 0 1)
ledger_file 0 8 "$S3_WINDOW" "$S3_WAVES" "$WORK/s3-ledger.json"

guardian false 3 "$WORK/s3a-guardian.json"
run_gate "$WORK/s3-ledger.json" "$WORK/s3a-guardian.json" "$S3_CFG" 10
s3a_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "HOLD" ] && command -v jq >/dev/null 2>&1; then
  admit=$(printf '%s' "$json" | jq -r '.effectiveNewWorkersMax // -1' 2>/dev/null)
  [ "$admit" = "0" ] && s3a_ok=0
fi

guardian true 0 "$WORK/s3b-guardian.json"
run_gate "$WORK/s3-ledger.json" "$WORK/s3b-guardian.json" "$S3_CFG" 10
s3b_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "HOLD" ] && command -v jq >/dev/null 2>&1; then
  admit=$(printf '%s' "$json" | jq -r '.effectiveNewWorkersMax // -1' 2>/dev/null)
  [ "$admit" = "0" ] && s3b_ok=0
fi

s3_ok=$((s3a_ok + s3b_ok))
[ "$s3_ok" -gt 0 ] && s3_ok=1
report "scenario3 Guardian deny or zero recommendation forces HOLD and zero effective admission" $s3_ok "expected HOLD with effectiveNewWorkersMax==0 in both subcases; got deny=$s3a_ok zerorec=$s3b_ok"

# ============================================================================
# Scenario 4: valid stage-4 evidence against the recorded canonical 4->12
# boundary (>=30 days, backlog >=20, 3 waves, lane ratio <=0.20, A/B pairs
# >=5, CI pass-through >=0.90, escaped-defect/rework non-regression)
# recommends stage 12; the throughput multiplier and claimEligible are
# computed honestly from the aggregate window, with a below-target and an
# at/above-target subcase against the configured
# programThroughputObjective.targetThroughputMultiplier.min (already 10 in
# the real config; not re-declared here).
# ============================================================================
S4_CFG=$(make_config 4)
S4_WAVES='['"$(good_wave w4-1 4 0 10)"','"$(good_wave w4-2 4 10 20)"','"$(good_wave w4-3 4 20 30)"']'

# 4a: below-target multiplier (accepted 50 / baseline 10 == 5x, target 10x)
S4_WINDOW_A=$(win 0 30 5 50 10 0.01 0.01 0.02 0.02 0.96)
ledger_file 4 20 "$S4_WINDOW_A" "$S4_WAVES" "$WORK/s4a-ledger.json"
guardian true 8 "$WORK/s4a-guardian.json"
run_gate "$WORK/s4a-ledger.json" "$WORK/s4a-guardian.json" "$S4_CFG" 30
s4a_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "PROMOTE" ] && command -v jq >/dev/null 2>&1; then
  recstage=$(printf '%s' "$json" | jq -r '.recommendedStage // .promoteToStage // empty' 2>/dev/null)
  mult=$(printf '%s' "$json" | jq -r '.throughputMultiplier // empty' 2>/dev/null)
  claim=$(printf '%s' "$json" | jq -r 'if .claimEligible == null then "" elif .claimEligible then "true" else "false" end' 2>/dev/null)
  if [ "$recstage" = "12" ] && [ "$mult" = "5" ] && [ "$claim" = "false" ]; then
    s4a_ok=0
  fi
fi

# 4b: at/above-target multiplier (accepted 100 / baseline 10 == 10x)
S4_WINDOW_B=$(win 0 30 5 100 10 0.01 0.01 0.02 0.02 0.96)
ledger_file 4 20 "$S4_WINDOW_B" "$S4_WAVES" "$WORK/s4b-ledger.json"
guardian true 8 "$WORK/s4b-guardian.json"
run_gate "$WORK/s4b-ledger.json" "$WORK/s4b-guardian.json" "$S4_CFG" 30
s4b_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "PROMOTE" ] && command -v jq >/dev/null 2>&1; then
  recstage=$(printf '%s' "$json" | jq -r '.recommendedStage // .promoteToStage // empty' 2>/dev/null)
  mult=$(printf '%s' "$json" | jq -r '.throughputMultiplier // empty' 2>/dev/null)
  claim=$(printf '%s' "$json" | jq -r 'if .claimEligible == null then "" elif .claimEligible then "true" else "false" end' 2>/dev/null)
  if [ "$recstage" = "12" ] && [ "$mult" = "10" ] && [ "$claim" = "true" ]; then
    s4b_ok=0
  fi
fi

s4_ok=$((s4a_ok + s4b_ok))
[ "$s4_ok" -gt 0 ] && s4_ok=1
report "scenario4 valid stage4 thirty-day evidence promotes to 12 with honestly computed multiplier/claimEligible" $s4_ok "expected PROMOTE to stage 12 in both subcases, multiplier 5/claimEligible=false then multiplier 10/claimEligible=true; got below=$s4a_ok atTarget=$s4b_ok"

# ============================================================================
# Scenario 5: stage 12 -> 25 (recorded boundary: >=30 days, backlog >=40, 3
# waves, all modules green, zero rollback-triggered packages and conflict
# false negatives) and stage 25 -> 50 (recorded boundary: >=30 days, backlog
# >=75, 5 waves, zero cross-package file conflicts, rework non-regression)
# immediate promotions each satisfy their own distinct configured criteria,
# never skipping a stage.
# ============================================================================
S5_CFG_12=$(make_config 12)
S5_WAVES_12='['"$(good_wave w5a-1 12 0 10)"','"$(good_wave w5a-2 12 10 20)"','"$(good_wave w5a-3 12 20 30)"']'
S5_WINDOW_12=$(win 0 30 5 60 10 0.01 0.01 0.02 0.02 0.96)
ledger_file 12 40 "$S5_WINDOW_12" "$S5_WAVES_12" "$WORK/s5a-ledger.json"
guardian true 13 "$WORK/s5a-guardian.json"
run_gate "$WORK/s5a-ledger.json" "$WORK/s5a-guardian.json" "$S5_CFG_12" 30
s5a_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "PROMOTE" ] && command -v jq >/dev/null 2>&1; then
  recstage=$(printf '%s' "$json" | jq -r '.recommendedStage // .promoteToStage // empty' 2>/dev/null)
  [ "$recstage" = "25" ] && s5a_ok=0
fi

S5_CFG_25=$(make_config 25)
S5_WAVES_25='['"$(good_wave w5b-1 25 0 8)"','"$(good_wave w5b-2 25 8 16)"','"$(good_wave w5b-3 25 16 24)"','"$(good_wave w5b-4 25 24 32)"','"$(good_wave w5b-5 25 32 40)"']'
S5_WINDOW_25=$(win 0 40 8 90 10 0.01 0.01 0.02 0.02 0.98)
ledger_file 25 75 "$S5_WINDOW_25" "$S5_WAVES_25" "$WORK/s5b-ledger.json"
guardian true 25 "$WORK/s5b-guardian.json"
run_gate "$WORK/s5b-ledger.json" "$WORK/s5b-guardian.json" "$S5_CFG_25" 40
s5b_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "PROMOTE" ] && command -v jq >/dev/null 2>&1; then
  recstage=$(printf '%s' "$json" | jq -r '.recommendedStage // .promoteToStage // empty' 2>/dev/null)
  [ "$recstage" = "50" ] && s5b_ok=0
fi

s5_ok=$((s5a_ok + s5b_ok))
[ "$s5_ok" -gt 0 ] && s5_ok=1
report "scenario5 stage12->25 and stage25->50 promote per their own criteria without skipping" $s5_ok "expected PROMOTE recommendedStage==25 then ==50; got 12to25=$s5a_ok 25to50=$s5b_ok"

# ============================================================================
# Scenario 6: stage 50 -> 100 promotion (recorded boundary: >=30 days,
# backlog >=100, 10 waves, flow efficiency not below baseline, escaped
# defects non-regressing, zero unstable steward lanes and conflict false
# negatives) works; stage 100 is a terminal HOLD (no nextStage exists, so
# PROMOTE can never be the verdict at stage 100).
# ============================================================================
S6_CFG_50=$(make_config 50)
S6_WAVES_50='['"$(good_wave w6a-1 50 0 4)"','"$(good_wave w6a-2 50 4 8)"','"$(good_wave w6a-3 50 8 12)"','"$(good_wave w6a-4 50 12 16)"','"$(good_wave w6a-5 50 16 20)"','"$(good_wave w6a-6 50 20 24)"','"$(good_wave w6a-7 50 24 28)"','"$(good_wave w6a-8 50 28 32)"','"$(good_wave w6a-9 50 32 36)"','"$(good_wave w6a-10 50 36 40)"']'
S6_WINDOW_50=$(win 0 40 10 110 10 0.01 0.01 0.02 0.02 0.98)
ledger_file 50 100 "$S6_WINDOW_50" "$S6_WAVES_50" "$WORK/s6a-ledger.json"
guardian true 50 "$WORK/s6a-guardian.json"
run_gate "$WORK/s6a-ledger.json" "$WORK/s6a-guardian.json" "$S6_CFG_50" 40
s6a_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "PROMOTE" ] && command -v jq >/dev/null 2>&1; then
  recstage=$(printf '%s' "$json" | jq -r '.recommendedStage // .promoteToStage // empty' 2>/dev/null)
  [ "$recstage" = "100" ] && s6a_ok=0
fi

S6_CFG_100=$(make_config 100)
S6_WAVES_100='['"$(good_wave w6b-1 100 0 10)"','"$(good_wave w6b-2 100 10 20)"','"$(good_wave w6b-3 100 20 30)"','"$(good_wave w6b-4 100 30 40)"']'
S6_WINDOW_100=$(win 0 40 20 200 10 0.01 0.01 0.02 0.02 0.99)
ledger_file 100 200 "$S6_WINDOW_100" "$S6_WAVES_100" "$WORK/s6b-ledger.json"
guardian true 999 "$WORK/s6b-guardian.json"
run_gate "$WORK/s6b-ledger.json" "$WORK/s6b-guardian.json" "$S6_CFG_100" 40
s6b_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "HOLD" ]; then s6b_ok=0; fi

s6_ok=$((s6a_ok + s6b_ok))
[ "$s6_ok" -gt 0 ] && s6_ok=1
report "scenario6 stage50->100 promotes; stage100 is a terminal HOLD" $s6_ok "expected PROMOTE recommendedStage==100 then HOLD at terminal stage100; got 50to100=$s6a_ok stage100terminal=$s6b_ok"

# ============================================================================
# Scenario 7: escaped-defect-rate increase, a high-risk production
# regression, and a conflict false negative each independently cause
# ROLLBACK even when promotion metrics otherwise pass the 12->25 boundary;
# rollbackTarget names only the previous stage (here, stage 4, since
# currentStage is 12). A fourth subcase (7d) reuses otherwise
# promotion-eligible stage-0 bootstrap evidence (as in scenario1) together
# with a window escaped-defect-rate regression: stage 0 can never roll
# back, so the rollback trigger is suppressed and the verdict stays HOLD
# with rollbackTarget and recommendedStage both null and reasons carrying
# the exact token rollback-trigger-suppressed-at-bootstrap.
# ============================================================================
S7_CFG=$(make_config 12)

# 7a: escaped defect rate increased over baseline
S7_WAVES_A='['"$(good_wave w7a-1 12 0 10)"','"$(good_wave w7a-2 12 10 20)"','"$(good_wave w7a-3 12 20 30)"']'
S7_WINDOW_A=$(win 0 30 5 60 10 0.05 0.01 0.02 0.02 0.96)
ledger_file 12 40 "$S7_WINDOW_A" "$S7_WAVES_A" "$WORK/s7a-ledger.json"
guardian true 13 "$WORK/s7a-guardian.json"
run_gate "$WORK/s7a-ledger.json" "$WORK/s7a-guardian.json" "$S7_CFG" 30
s7a_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "ROLLBACK" ] && command -v jq >/dev/null 2>&1; then
  tgt=$(printf '%s' "$json" | jq -r '.rollbackTarget // empty' 2>/dev/null)
  [ "$tgt" = "4" ] && s7a_ok=0
fi

# 7b: a high-risk production regression in one trailing wave
S7_WAVES_B='['"$(good_wave w7b-1 12 0 10)"','"$(wave w7b-2 12 10 20 20 0.10 0 0 true true true '[]' 0 1 0.9 0.8 '[]')"','"$(good_wave w7b-3 12 20 30)"']'
S7_WINDOW_B=$(win 0 30 5 60 10 0.01 0.01 0.02 0.02 0.96)
ledger_file 12 40 "$S7_WINDOW_B" "$S7_WAVES_B" "$WORK/s7b-ledger.json"
guardian true 13 "$WORK/s7b-guardian.json"
run_gate "$WORK/s7b-ledger.json" "$WORK/s7b-guardian.json" "$S7_CFG" 30
s7b_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "ROLLBACK" ] && command -v jq >/dev/null 2>&1; then
  tgt=$(printf '%s' "$json" | jq -r '.rollbackTarget // empty' 2>/dev/null)
  [ "$tgt" = "4" ] && s7b_ok=0
fi

# 7c: a conflict false negative in one trailing wave
S7_WAVES_C='['"$(good_wave w7c-1 12 0 10)"','"$(wave w7c-2 12 10 20 20 0.10 1 0 true true true '[]' 0 0 0.9 0.8 '[]')"','"$(good_wave w7c-3 12 20 30)"']'
S7_WINDOW_C=$(win 0 30 5 60 10 0.01 0.01 0.02 0.02 0.96)
ledger_file 12 40 "$S7_WINDOW_C" "$S7_WAVES_C" "$WORK/s7c-ledger.json"
guardian true 13 "$WORK/s7c-guardian.json"
run_gate "$WORK/s7c-ledger.json" "$WORK/s7c-guardian.json" "$S7_CFG" 30
s7c_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "ROLLBACK" ] && command -v jq >/dev/null 2>&1; then
  tgt=$(printf '%s' "$json" | jq -r '.rollbackTarget // empty' 2>/dev/null)
  [ "$tgt" = "4" ] && s7c_ok=0
fi

# 7d: otherwise promotion-eligible stage-0 bootstrap evidence (as in
# scenario1) plus a window escaped-defect-rate regression; stage 0 cannot
# roll back, so the rollback trigger is suppressed and the verdict stays
# HOLD with rollbackTarget and recommendedStage both null.
S7_CFG_D=$(make_config 0)
S7_WAVES_D='['"$(good_wave w7d-1 0 0 5)"','"$(good_wave w7d-2 0 5 10)"']'
S7_WINDOW_D=$(win 0 10 0 0 0 0.05 0.01 0 0 1)
ledger_file 0 8 "$S7_WINDOW_D" "$S7_WAVES_D" "$WORK/s7d-ledger.json" 0
guardian true 3 "$WORK/s7d-guardian.json"
run_gate "$WORK/s7d-ledger.json" "$WORK/s7d-guardian.json" "$S7_CFG_D" 10
s7d_ok=1
if [ "$ec" -eq 0 ] && [ "$verdict" = "HOLD" ] && command -v jq >/dev/null 2>&1; then
  rtgt=$(printf '%s' "$json" | jq -r '.rollbackTarget' 2>/dev/null)
  rstage=$(printf '%s' "$json" | jq -r '.recommendedStage' 2>/dev/null)
  hastoken=$(printf '%s' "$json" | jq -r '((.reasons // []) | index("rollback-trigger-suppressed-at-bootstrap")) != null' 2>/dev/null)
  if [ "$rtgt" = "null" ] && [ "$rstage" = "null" ] && [ "$hastoken" = "true" ]; then s7d_ok=0; fi
fi

s7_ok=$((s7a_ok + s7b_ok + s7c_ok + s7d_ok))
[ "$s7_ok" -gt 0 ] && s7_ok=1
report "scenario7 escaped-defect increase, high-risk regression, or conflict false-negative force ROLLBACK to previous stage; stage-0 bootstrap suppresses the rollback trigger into HOLD" $s7_ok "expected ROLLBACK with rollbackTarget==4 in the first three subcases and HOLD with null rollbackTarget/recommendedStage plus the suppression reason token at bootstrap; got escapedDefect=$s7a_ok highRiskRegr=$s7b_ok conflictFN=$s7c_ok bootstrapSuppressed=$s7d_ok"

# ============================================================================
# Scenario 8: fail-closed bundle -- malformed JSON, wrong types, negative
# metrics, duplicate wave IDs, a future window end, a reversed/nonmonotonic
# window, ledger/config stage mismatch, broken config stage-adjacency field,
# and a malformed Guardian file all fail closed; also a JSON-key-order
# variant of one valid logical ledger yields a byte-identical decision JSON.
# ============================================================================
S8_CFG=$(make_config 0)
S8_GOOD_GUARDIAN="$WORK/s8-guardian.json"
guardian true 3 "$S8_GOOD_GUARDIAN"

printf 'not json at all {' >"$WORK/s8-malformed.json"
run_gate "$WORK/s8-malformed.json" "$S8_GOOD_GUARDIAN" "$S8_CFG" 10
s8a_ok=$([ "$ec" -ne 0 ] && [ "$verdict" = "LEDGER_INVALID" ] && echo 0 || echo 1)

cat <<JSON >"$WORK/s8-wrongtype.json"
{"schemaVersion":1,"currentStage":0,"activeWorkers":0,"readyBacklogCount":"eight","window":$(win 0 10 0 0 0 0 0 0 0 1),"waves":[$(good_wave w8t-1 0 0 10)]}
JSON
run_gate "$WORK/s8-wrongtype.json" "$S8_GOOD_GUARDIAN" "$S8_CFG" 10
s8b_ok=$([ "$ec" -ne 0 ] && [ "$verdict" = "LEDGER_INVALID" ] && echo 0 || echo 1)

NEG_WINDOW=$(win 0 10 0 -5 10 0.01 0.01 0.02 0.02 0.96)
ledger_file 0 8 "$NEG_WINDOW" "$S1_WAVES" "$WORK/s8-negative.json" 0
run_gate "$WORK/s8-negative.json" "$S8_GOOD_GUARDIAN" "$S8_CFG" 10
s8c_ok=$([ "$ec" -ne 0 ] && [ "$verdict" = "LEDGER_INVALID" ] && echo 0 || echo 1)

DUP_WAVES='['"$(good_wave w8dup 0 0 5)"','"$(good_wave w8dup 0 5 10)"']'
ledger_file 0 8 "$S1_WINDOW" "$DUP_WAVES" "$WORK/s8-dupwave.json" 0
run_gate "$WORK/s8-dupwave.json" "$S8_GOOD_GUARDIAN" "$S8_CFG" 10
s8d_ok=$([ "$ec" -ne 0 ] && [ "$verdict" = "LEDGER_INVALID" ] && echo 0 || echo 1)

FUTURE_WAVES='['"$(good_wave w8fut-1 0 0 5)"','"$(good_wave w8fut-2 0 5 10)"']'
ledger_file 0 8 "$S1_WINDOW" "$FUTURE_WAVES" "$WORK/s8-future.json" 0
run_gate "$WORK/s8-future.json" "$S8_GOOD_GUARDIAN" "$S8_CFG" 3
s8e_ok=$([ "$ec" -ne 0 ] && [ "$verdict" = "LEDGER_INVALID" ] && echo 0 || echo 1)

REVERSED_WAVES='['"$(good_wave w8rev-1 0 8 3)"']'
ledger_file 0 8 "$S1_WINDOW" "$REVERSED_WAVES" "$WORK/s8-reversed.json" 0
run_gate "$WORK/s8-reversed.json" "$S8_GOOD_GUARDIAN" "$S8_CFG" 10
s8f_ok=$([ "$ec" -ne 0 ] && [ "$verdict" = "LEDGER_INVALID" ] && echo 0 || echo 1)

ledger_file 4 8 "$S1_WINDOW" "$S1_WAVES" "$WORK/s8-stagemismatch.json" 0
run_gate "$WORK/s8-stagemismatch.json" "$S8_GOOD_GUARDIAN" "$S8_CFG" 10
s8g_ok=$([ "$ec" -ne 0 ] && [ "$verdict" = "LEDGER_INVALID" ] && echo 0 || echo 1)

# broken config stage-adjacency: copy BASE_CONFIG, set rampStage="0", and
# mutate only moduleFramework.rampStages (the existing canonical
# post-bootstrap stage-sequence field) so stage 0's immediate next stage is
# no longer first in the sequence -- no policy object is injected.
BROKEN_CFG="$WORK/s8-broken-config.json"
jq '.moduleFramework.rampStage = "0" | .moduleFramework.rampStages = ["12","4","25","50","100"]' \
  "$BASE_CONFIG" >"$BROKEN_CFG"
run_gate "$WORK/s1-ledger.json" "$S8_GOOD_GUARDIAN" "$BROKEN_CFG" 10
s8h_ok=$([ "$ec" -ne 0 ] && [ "$verdict" = "CONFIG_ERROR" ] && echo 0 || echo 1)

printf 'not guardian json {' >"$WORK/s8-badguardian.json"
run_gate "$WORK/s1-ledger.json" "$WORK/s8-badguardian.json" "$S8_CFG" 10
s8i_ok=$([ "$ec" -ne 0 ] && [ "$verdict" = "GUARDIAN_INVALID" ] && echo 0 || echo 1)

S8_BADTYPE_CONFLICTFN="$WORK/s8-badtype-conflictfn.json"
jq '.moduleFramework.rampPromotionPolicy.waveQualityThresholds.conflictFalseNegativesMaxPerWave = "0"' "$BASE_CONFIG" >"$S8_BADTYPE_CONFLICTFN"
run_gate "$WORK/s1-ledger.json" "$S8_GOOD_GUARDIAN" "$S8_BADTYPE_CONFLICTFN" 10
s8j_ok=$([ "$ec" -ne 0 ] && [ "$verdict" = "CONFIG_ERROR" ] && echo 0 || echo 1)

S8_NULL_MINOBSERVEDDAYS="$WORK/s8-null-minobserveddays.json"
jq '.moduleFramework.rampPromotionPolicy.claimEligibilityPolicy.minObservedDays = null' "$BASE_CONFIG" >"$S8_NULL_MINOBSERVEDDAYS"
run_gate "$WORK/s1-ledger.json" "$S8_GOOD_GUARDIAN" "$S8_NULL_MINOBSERVEDDAYS" 10
s8k_ok=$([ "$ec" -ne 0 ] && [ "$verdict" = "CONFIG_ERROR" ] && echo 0 || echo 1)

S8_BADTYPE_MINREADYBACKLOG="$WORK/s8-badtype-minreadybacklog.json"
jq '.moduleFramework.rampStage = "4" | .moduleFramework.rampPromotionPolicy.transitions["4"].minReadyBacklog = "20"' "$BASE_CONFIG" >"$S8_BADTYPE_MINREADYBACKLOG"
ledger_file 4 20 "$S4_WINDOW_B" "$S4_WAVES" "$WORK/s8-stage4-otherwisevalid.json"
run_gate "$WORK/s8-stage4-otherwisevalid.json" "$S8_GOOD_GUARDIAN" "$S8_BADTYPE_MINREADYBACKLOG" 30
s8l_ok=$([ "$ec" -ne 0 ] && [ "$verdict" = "CONFIG_ERROR" ] && echo 0 || echo 1)

s8_fail_closed_ok=$((s8a_ok + s8b_ok + s8c_ok + s8d_ok + s8e_ok + s8f_ok + s8g_ok + s8h_ok + s8i_ok + s8j_ok + s8k_ok + s8l_ok))
[ "$s8_fail_closed_ok" -gt 0 ] && s8_fail_closed_ok=1

# key-order determinism: rebuild the same logical scenario-1 ledger and
# guardian with JSON keys in a different order and require a byte-identical
# decision JSON.
cat <<JSON >"$WORK/s8-reordered-ledger.json"
{
  "waves": $S1_WAVES,
  "window": $S1_WINDOW,
  "readyBacklogCount": 8,
  "activeWorkers": 0,
  "currentStage": "0",
  "schemaVersion": 1
}
JSON
cat <<JSON >"$WORK/s8-reordered-guardian.json"
{"evaluatedAtEpochDay":0,"recommendedNewWorkers":3,"allowNewWorker":true}
JSON
run_gate "$WORK/s1-ledger.json" "$WORK/s1-guardian.json" "$S1_CFG" 10
json_orig="$json"; ec_orig="$ec"; verdict_orig="$verdict"
run_gate "$WORK/s8-reordered-ledger.json" "$WORK/s8-reordered-guardian.json" "$S1_CFG" 10
json_reord="$json"; ec_reord="$ec"; verdict_reord="$verdict"
s8_determinism_ok=1
if [ "$ec_orig" -eq 0 ] && [ "$ec_reord" -eq 0 ] && [ -n "$json_orig" ] && [ "$json_orig" = "$json_reord" ] \
  && [ "$verdict_orig" = "$verdict_reord" ]; then
  s8_determinism_ok=0
fi

s8_ok=$((s8_fail_closed_ok + s8_determinism_ok))
[ "$s8_ok" -gt 0 ] && s8_ok=1
report "scenario8 fail-closed bundle rejects malformed/negative/duplicate/future/reversed/mismatched/config-policy-corrupted input and stays byte-deterministic under key reordering" $s8_ok "expected all twelve fail-closed subcases invalid and the key-order variant byte-identical to the original; got malformed=$s8a_ok wrongtype=$s8b_ok negative=$s8c_ok dupwave=$s8d_ok future=$s8e_ok reversed=$s8f_ok stagemismatch=$s8g_ok brokenconfig=$s8h_ok badguardian=$s8i_ok conflictFNbadtype=$s8j_ok minObservedDaysNull=$s8k_ok minReadyBacklogBadtype=$s8l_ok determinism=$s8_determinism_ok"

# ============================================================================
printf -- '--- ramp-gate.test.sh: %d passed, %d failed ---\n' "$pass" "$fail"
[ "$fail" -eq 0 ]
