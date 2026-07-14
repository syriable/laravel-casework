# Laravel Trust & Safety — Domain Glossary

**Phase:** 2 — Domain Discovery
**Produced by:** Domain Analysis team (T2)
**Approver:** Fable (Project Director)
**Status:** DRAFT — awaiting approval (Gate G2)
**Version:** 1.0.0
**Date:** 2026-07-14
**Upstream:** [Requirements](../requirements.md) (Gate G1 approved 2026-07-14)

This glossary is the **ubiquitous language** of the package. Once approved, these terms are
binding for all code, documentation, database identifiers, events, and APIs. Terms are
chosen to read naturally to Laravel developers; no invented jargon.

---

## Core Actors

| Term | Definition |
|---|---|
| **Actor** | Any entity that performs a domain action: a user-like Eloquent model, or the **System**. Stored polymorphically. Every state change is attributed to exactly one actor. |
| **System** | The reserved non-model actor representing automated behavior (automation hooks, scheduled expiry). Attributed explicitly in audit history (FR-805). |
| **Reporter** | The actor who files a report. One of: an Eloquent model, the System, or **Anonymous** (no identity recorded) (FR-103). |
| **Moderator** | An actor authorized (via gates/policies and scopes) to work cases: investigate, decide, enforce. The package models moderators as actors; it ships no moderator role system (FR-603). |
| **Subject** | The Eloquent model a report or enforcement action is about — a post, comment, listing, user, etc. Always polymorphic. |
| **Appellant** | The actor submitting an appeal on behalf of an affected subject (FR-501). |

## Reporting Context

| Term | Definition |
|---|---|
| **Report** | A single claim by a reporter that a subject violates a rule, carrying a reason, optional comment, optional metadata, and a state (FR-102). |
| **Reportable** | The capability, added by trait + contract, that lets an Eloquent model be the subject of reports (FR-101). |
| **Reason** | A configured classification for why something is reported (spam, harassment, …), with machine key, label, and active flag (FR-153). |
| **Reason Category** | Optional one-level grouping of reasons (FR-154). |
| **Duplicate Report** | An open report by the same reporter, on the same subject, for the same reason. Rejected by default (FR-105). |

## Case Management Context

| Term | Definition |
|---|---|
| **Case** | The unit of moderation work: one or more reports about a primary subject, moving through investigation to a decision (FR-201). The package's namesake concept. |
| **Case Strategy** | The configurable rule for when reports become or join cases: always, per-subject threshold, or manual (FR-205). |
| **Assignment** | The link between a case (or appeal) and the moderator responsible for it (FR-203). |
| **Priority** | The configurable urgency level of a case (FR-204). |
| **Note** | A timestamped, authored, immutable investigation remark on a case (FR-251, FR-254). |
| **Evidence** | An immutable record attached to a case referencing a model or structured data supporting the investigation (FR-252). |

## Decision & Enforcement Context

| Term | Definition |
|---|---|
| **Decision** | The immutable record resolving a case: deciding actor, outcome, optional rationale, and any enforcement actions applied with it (FR-301–304). |
| **Outcome** | The classified result of a decision. Shipped defaults: **dismiss**, **uphold**, **escalate**; application-extensible (FR-302). |
| **Rationale** | The decider's free-text justification, stored opaquely (NFR-09). |
| **Enforcement Action** | Umbrella term for consequences a decision applies: warnings, restrictions, suspensions (FR-303). |
| **Restriction** | A typed, scoped limitation placed on a subject, with lifecycle active → expired/lifted/superseded (FR-402). **Temporary** (has `expires_at`) or **permanent** (FR-403). |
| **Restriction Type** | The application-extensible classification of what a restriction limits (e.g. posting, messaging). **Suspension** is the shipped first-class type (FR-407). |
| **Restrictable** | The capability, added by trait + contract, that lets an Eloquent model receive restrictions (FR-401). |
| **Scope** | The area/category a restriction or moderator ability applies to (e.g. only "listings"), resolved via an application contract (FR-602). |
| **Warning** | A formal, recorded caution issued to a subject; counts toward history, optionally expiring (FR-406). |
| **Suspension** | A restriction of type suspension: time-boxed or indefinite removal of a subject's standing (FR-407). |
| **Lift** | Ending a restriction early by an actor, with recorded reason (FR-408). Distinct from **Expiry** (automatic, time-based) and **Supersede** (replaced by a newer restriction). |

## Appeals Context

| Term | Definition |
|---|---|
| **Appeal** | A request by an appellant to re-examine a decision or restriction, with its own lifecycle (FR-501–502). |
| **Appeal Window** | The configurable period after a decision/restriction during which an appeal may be submitted (FR-506). |
| **Uphold (appeal)** | Appeal resolved by confirming the original decision/restriction. |
| **Overturn** | Appeal resolved by reversing the original: lifts associated restrictions and records a superseding decision (FR-504). |
| **Reject (appeal)** | Appeal refused without full review (e.g. out of window, exhausted attempts) (FR-503, FR-506). |

## Audit & Lifecycle Vocabulary

| Term | Definition |
|---|---|
| **Audit Entry** | One append-only record of a domain action: actor, action, auditable, timestamp, structured payload (FR-701–703). |
| **Audit Trail** | The queryable sequence of audit entries for a subject, actor, or period (FR-704). |
| **State** | A named position in a lifecycle (report, case, restriction, appeal). Core states are non-removable; apps may add states within limits (FR-903). |
| **Transition** | The only permitted way state changes: guarded, evented, audited, actor-attributed (FR-104, FR-802). |
| **Guard** | A condition (authorization, invariant) that must pass for a transition to run. |
| **Workflow** | The complete configured state machine for one lifecycle. |

## Integration Vocabulary

| Term | Definition |
|---|---|
| **Domain Event** | The Laravel event dispatched for every meaningful occurrence, carrying models and, for transitions, from/to states and actor (FR-801–802). |
| **Notification Hook** | The contract point where applications register their own notifiers; the package never sends anything (FR-803). |
| **Automation Hook** | The contract point intercepting report intake / case triage, able to act as the System actor (FR-804–805). |

---

## Naming Rules

1. These nouns are used **verbatim** in class names, table names, relationships, events,
   and docs (`Report`, `ReportCase`/`cases` naming finalized in Phase 5/6 to avoid the SQL
   reserved-word issue — the *domain term* is **Case**).
2. States are adjectives/participles (`pending`, `underReview`, `active`, `lifted`);
   transitions are verbs (`assign`, `escalate`, `decide`, `lift`, `overturn`).
3. Events are past-tense facts (`ReportFiled`, `CaseDecided`, `RestrictionLifted`).
4. No synonyms: "flag" (for report), "ban" (for suspension), "ticket" (for case), and
   "punishment/penalty" (for enforcement action) are **not** used anywhere.

## Definition of Done — Phase 2 (glossary part)

- [x] Every FR-referenced concept defined once, unambiguously
- [x] Laravel-community-friendly naming; no invented jargon; synonyms banned explicitly
- [ ] Fable review passed · Project owner approval — **Gate G2** (with domain map)
