---
name: zabuno-speeder
description: Route a repo/package through the SP-01 speed gate before RED, before GREEN handoff, and at every checkpoint (cadence defined in config, not here). Use when starting, checkpointing, or closing any implementation package in this repo (fixes, features, docs-only excluded). Reads config/development-speed-budget.json only; never restates its numbers.
---

# Zabuno Speeder

Deterministic, non-LLM gate for package throughput. It does not decide
scope, review, or Git actions — it only tells a worker whether the current
package attempt fits the risk-lane budget in
`config/development-speed-budget.json`.

## When to invoke

- At package start, right after risk-lane classification (`prototype` /
  `microHotfix` / `normal` / `highRisk`).
- At every checkpoint (universal cadence, value owned only by
  `config/development-speed-budget.json#checkpointCadenceMinutesMax` — see
  `.claude/rules/fast-development.md`).
- Before requesting a full local QA run or a reviewer full-suite run.
- Before scanning docs for stray duplicated numeric thresholds.

## How to invoke

Build a package manifest JSON describing this checkpoint attempt (see
`scripts/speed-gate.test.sh` for the field contract: `lane`,
`targetedTestCount`, `testFilesChanged`, `changedPaths`,
`elapsedCheckpointMinutes`, `snapshotHash`, and lane-specific fields such as
`adjacentMicroFixCount`, `semanticRiskClasses`, `fullLocalQaRunsSoFar`,
`requestAnotherFullLocalQa`, `reviewerFullSuiteRunsRequested`). Then:

```
scripts/speed-gate check --manifest <manifest.json> --config config/development-speed-budget.json
```

Read the last stdout line as the verdict:

- `PASS` — proceed.
- `BATCH_REQUIRED` — package is oversized or over-tested for its lane; split
  by journey, batch adjacent micro-fixes, or reclassify.
- `HIGH_RISK` — a path pattern or semantic risk class forces the high-risk
  regime; normal-lane budgets no longer apply, extra test/review is
  justified.
- `CHECKPOINT_BLOCKED` — elapsed time exceeded the universal checkpoint
  cadence; take a safe checkpoint instead of continuing to work the same
  uncertainty.

For docs hygiene:

```
scripts/speed-gate docs-scan --docs-root docs
```

`CLEAN` or `DUPLICATE_THRESHOLDS` (docs must point to
`config/development-speed-budget.json`, not restate its numbers).

## Non-goals

No network, no LLM/MCP call, no Git mutation, no scope decision. This
skill routes to the deterministic script; it never substitutes judgment for
the semantic high-risk classification a worker must still make (fail-closed
to `HIGH_RISK` when uncertain).
