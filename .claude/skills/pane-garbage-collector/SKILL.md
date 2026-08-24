---
name: pane-garbage-collector
description: Event-driven, safety-gated Pane cleanup. Run before admitting a new worker, after a worker hands off or exits, at task close, under Guardian/PTY pressure, or on explicit owner request — never on a timer. Archives at most one fully proven safe (stopped, unpinned, no initialized panel, clean/pushed worktree, non-current) Pane per run.
---

# Pane Garbage Collector

Reclaims stale Pane sessions without ever risking live work. Dry-run by
default; `--apply` archives at most one fully proven safe Pane per run,
using exactly `runpane panes archive --pane <ID> --source agent --yes --json`.

## When to invoke (event-driven only)

Trigger this skill only in response to a real event — never poll, schedule,
or loop it:

- before admitting a new worker/Pane (reclaim headroom first)
- after a worker hands off or exits (its Pane may now be idle)
- at task close
- under Guardian/PTX memory or PTY pressure
- on explicit owner request

No crontab, launchd, sleep-loop, or self-rescheduling is ever appropriate
for this skill.

## Procedure

1. Load `claude-memory-guardian` guidance first if memory/PTY pressure is
   the trigger, so cleanup decisions are informed by current system state.
2. Run the script in dry-run mode (no flags) and read its output:
   ```
   .claude/skills/pane-garbage-collector/scripts/pane_gc.sh
   ```
3. Only under the standing owner authorization recorded in this repo's
   `CLAUDE.md`, and only after reviewing the dry-run output, re-run with
   `--apply`:
   ```
   .claude/skills/pane-garbage-collector/scripts/pane_gc.sh --apply
   ```
4. Always pass the caller's own Pane identity so it is never a candidate —
   either export `PANE_GC_CURRENT_PANE_ID` or pass `--current-pane <ID>`.
5. If the script prints `PANE_RESTART_REQUIRED`, do not attempt to restart
   Pane yourself. Report it; the outer Codex Desktop MASTER decides whether
   and when to gracefully quit/reopen Pane, only once every active writer
   is safely handed off.

## Safety guarantees (enforced by the script and its RED test)

- Dry-run unless `--apply` is explicitly passed.
- Protects: the caller/current Pane, any non-`stopped` status, pinned
  Panes, any Pane with an `initialized=true` panel, and any worktree that
  is dirty, has untracked files, has unpushed commits, or is
  missing/unreadable/unverifiable.
- At most one archive per run, with the exact non-force command shape
  above — never `kill`, a raw signal, `--force`, `reset --hard`, `stash`,
  or delete.
- Fails closed (zero archive commands) if `runpane doctor` fails, or on
  any ambiguity.
- PTY pressure reporting only — never restarts Pane itself.

## Files

- `scripts/pane_gc.sh` — the implementation.
- `tests/pane_gc_test.sh` — the RED/GREEN acceptance test; do not modify
  it from this skill's normal usage path.
