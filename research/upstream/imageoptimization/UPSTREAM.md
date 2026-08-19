# UPSTREAM.md — imageoptimization (istoc Media Engine) snapshot provenance

**PLANNING ONLY.** This directory holds a frozen, read-only reference snapshot for
research purposes. Nothing here is executed, imported, or ported verbatim into the
Zabuno plan (this repository, `zabuno/zabuno`) or any runtime code.

## Source

- Repository URL: `https://github.com/karacaismail/imageoptimization`
- Published docs (rendered): `https://karacaismail.github.io/imageoptimization/docs/`
- Resolved via: `git ls-remote https://github.com/karacaismail/imageoptimization.git HEAD refs/heads/main`
- Default branch: `main`
- Exact commit at retrieval: `04e55de8f8f90f5ef1a15e0f842ad9c1b68477ab`
- Retrieval date: **2026-08-19**
- Retrieval method: `codeload.github.com` tarball
  (`https://codeload.github.com/karacaismail/imageoptimization/tar.gz/04e55de8f8f90f5ef1a15e0f842ad9c1b68477ab`),
  extracted, and copied into `./snapshot/` with the nested VCS metadata excluded
  (the tarball itself ships no `.git` directory — confirmed by `find . -name .git`
  returning nothing after extraction).
- Downloaded archive checksum (SHA-256, the raw `.tar.gz` as fetched, before
  extraction): `016b93e084f62e32efd6703d33ec8200d0df45db5e15eca3bccf3fd3ed32d76d`

## What this repository actually is

Despite the repo name "imageoptimization", the upstream content (see its own
`README.md`) describes itself as:

> **istoc Media Engine — Geliştirme Yönergesi**: a waterfall specification and task
> document (15 phases, 102 atomic developer tasks) for a **Frappe app + Vue 3
> headless frontend** enterprise-grade media library and image/video optimization
> engine.

It is a **static documentation site** (HTML/CSS/JS under `docs/`, a self-contained
single-file offline build under `docs/yonerge/`, and sample product images under
`gorseller/` used by an in-browser crop/preview simulator). It is **not** a Laravel
project, not a running service, and contains no PHP.

## License status

No `LICENSE`, `LICENSE.md`, or `COPYING` file was found in the snapshot
(`find . -iname "LICENSE*" -o -iname "COPYING*"` returned nothing). Treat this
repository's content as **all-rights-reserved by the author (karacaismail) by
default** absent an explicit license grant. Confidence: **koşullu (conditional)** —
this reflects the state of the repository at the retrieval commit only; if the
author later adds a license file, this note should be re-verified before any reuse
beyond internal reference reading. No content from this snapshot is copied into
this repository's canonical docs; only *concepts* are referenced by paraphrase,
per the task's port/no-port boundary below.

## Port / no-port boundary

- **No Frappe code is ported.** The upstream stack (Frappe, Vue 3) is architecturally
  irrelevant to the Zabuno Laravel + React plan and must not be treated as a
  dependency or a template to copy from.
- **Concepts referenced (not code) for the PHP/Laravel media plan**
  (see `docs/07-MEDIA-FILE-MANAGER.md` in the canonical tree): a realistic device/slot
  crop-and-preview simulator; deriving image variants ("derivatives") from an
  immutable "original", keyed by a fingerprint of source + crop/focal-point +
  output-recipe + engine version so identical requests are not re-processed
  (idempotence); separating local-primary storage from an optional S3-compatible
  tier; and a metadata-first approach to tracking derivative provenance. These are
  *ideas*, independently re-specified for the Laravel/Spatie Media
  Library/Intervention/Flysystem candidate stack — none of the referenced upstream
  HTML/JS/Python implementation is reused.
- Any future contributor pulling additional detail from the rendered docs
  (`https://karacaismail.github.io/imageoptimization/docs/`) must re-apply this same
  boundary: read for concept, re-specify for PHP, never copy-paste Frappe/Vue code
  or upstream prose verbatim into a canonical document of this repository.

## Snapshot contents (top level)

```
snapshot/
├── README.md
├── index.html
├── docs/            (15-phase spec site: 00-yonetici-ozeti … 72-faz14-test-kabul,
│                      plus a task board, API reference, and a self-contained
│                      offline build under docs/yonerge/)
└── gorseller/        (sample product images used by the crop/preview simulator)
```

## Reference registration

This snapshot and its provenance are also indexed in
[`../../../docs/28-SOURCE-REGISTER.md`](../../../docs/28-SOURCE-REGISTER.md).
