# Laravel Trust & Safety — Documentation Plan

**Phase:** 12 — Documentation Plan
**Produced by:** Documentation & DX team (T13)
**Approver:** Fable (Project Director)
**Status:** DRAFT — awaiting approval (Gate G12)
**Version:** 1.0.0
**Date:** 2026-07-14
**Upstream:** All design phases G0–G11

Documentation ships **with** the milestone that implements the behavior (Phase 14 rule),
never after. This plan fixes the structure so implementation only fills it in.

---

## 1. Audiences & Their Paths

| Audience | Path | Success test |
|---|---|---|
| Newcomer (persona P1) | README top-to-bottom | M1/M2: first report in ≤5 min, full flow wired from README + guide only |
| Platform engineer (P2) | guide → extending → reference | every X1–X13 extension implemented without reading package source |
| Contributor | CONTRIBUTING → architecture docs (this `docs/` tree) | PR matching ADRs on first review |

The design docs produced in Phases 0–11 remain in `docs/` as the *contributor/architecture*
record. End-user documentation lives in `docs/guide/` (new) and the README.

## 2. Docs Tree (end-user)

```
README.md                          ← front door: pitch, install, quickstart, links
UPGRADE.md                         ← per-major upgrade guides (format §5)
CONTRIBUTING.md                    ← contribution + ADR process
docs/guide/
  installation.md                  ← require, publish, migrate, key-type note (ADR-0010)
  quickstart.md                    ← the newcomer path, end to end
  reporting.md                     ← FR-100/150 features
  cases-and-decisions.md           ← FR-200/250/300
  enforcement.md                   ← FR-400: restrictions, suspensions, warnings, expiry command
  appeals.md                       ← FR-500
  audit.md                         ← FR-700 + pruning
  authorization.md                 ← policies, ScopeResolver, self-moderation
  events.md                        ← catalog tables, after-commit guarantee, notifier hook
  automation.md                    ← intake/triage pipelines, System attribution
  extending.md                     ← end-user version of X1–X13 (links to spec for rationale)
  configuration.md                 ← reference rendition of the Phase 10 spec
  exceptions.md                    ← catchable surfaces per operation (Phase 5 §10)
  workflows.md                     ← the four state diagrams, custom states how-to
  testing-your-integration.md      ← faking events, factories, asserting restrictions
```

## 3. API Surface → Doc Home (M4 completeness map)

| Phase 5 surface | Home |
|---|---|
| Traits + contracts (§1) | quickstart + reporting/enforcement |
| Report builder + reason mgmt (§2) | reporting.md |
| Case ops, notes, evidence (§3) | cases-and-decisions.md |
| Decision builder (§4) | cases-and-decisions.md |
| Enforcement builders + checks + command (§5) | enforcement.md |
| Appeals (§6) | appeals.md |
| Audit queries (§7) | audit.md |
| ScopeResolver + policies (§8) | authorization.md |
| Events (§9) | events.md |
| Exceptions (§10) | exceptions.md |
| Config keys (Phase 10) | configuration.md |
| Extension points (Phase 9) | extending.md |

CI check (Phase 14): a script asserting every public class/facade method name appears in
at least one guide file — mechanical M4 enforcement, mirroring the FR-traceability grep.

## 4. Mandated Content (accumulated obligations)

From T10 sign-offs and ADRs — each MUST appear in the named file:

1. Queued-listener serialization & re-fetch semantics (catalog §security) → events.md
2. Opaque-text PII responsibility (NFR-09/10) → events.md + audit.md
3. Pipeline stages are privileged code (extending spec §4) → automation.md
4. Policy overrides own their consequences → authorization.md
5. Immutability is model-layer, not SQL-layer (ADR-0003) → audit.md
6. Morph-map recommendation (ADR-0001) + key-type tightening (ADR-0010) → installation.md
7. State mutator overrides unsupported (extending spec §4) → extending.md
8. Events cannot veto; guards/stages can (ADR-0015) → events.md + automation.md
9. `CaseFile` naming note (ADR-0008) → quickstart.md + README
10. Expiry command scheduling recipe + real-time rule (Phase 7) → enforcement.md

## 5. Upgrade Guide Format

`UPGRADE.md`, one `## vX → vY` section per major, each change as: **What changed / Why
(ADR link) / Before / After / Estimated effort.** Additive minor-version features get
CHANGELOG entries only (existing `update-changelog.yml` workflow keeps maintaining it).

## 6. README Skeleton (to be filled at Phase 14 M-final)

```markdown
# Laravel Trust & Safety (laravel-casework)
> badges (existing)
One-paragraph pitch: complete moderation platform, UI-agnostic, Laravel-native.
## Features            ← bullet the §2 roadmap capability table
## Installation        ← require, publish, migrate (3 blocks)
## Quickstart          ← trait on model → file report → open case → decide → suspension
                          → check isRestricted() → appeal (compressed; links to guide)
## The CaseFile note   ← one-liner (ADR-0008)
## Documentation       ← docs/guide/ index table
## Testing             ← composer test
## Changelog / Contributing / Security / Credits / License  ← standard sections (existing)
```

Spatie skeleton leftovers to remove at that point: placeholder description, "Support us"
Spatie ad block, `.github/FUNDING.yml` (not ours), placeholder usage snippet.

## 7. Standards

- Every example in README/guide MUST be runnable verbatim against a workbench app;
  Phase 15 review executes them (M2 verification).
- Docblocks: every public class/method — one-line summary + param/return/throws where
  not expressed by types; no redundant restating of signatures (NFR-11).
- Language follows the glossary; banned synonyms (glossary naming rule 4) apply to docs.
- Diagrams: mermaid only (renders on GitHub), sourced from the Phase 7 docs.

## 8. Definition of Done — Phase 12

- [x] Docs tree fixed; every Phase 5 surface has a home (M4 map)
- [x] Newcomer path defined and bounded (README + quickstart)
- [x] All accumulated T10/ADR documentation obligations assigned to files
- [x] Upgrade/changelog formats fixed; README skeleton ready
- [ ] Fable review passed
- [ ] Project owner approval — **Gate G12**

**Next phase upon approval:** Phase 13 — Implementation Planning (ordered milestones
M1–M10 with per-milestone acceptance criteria; Gate G13 authorizes implementation).
