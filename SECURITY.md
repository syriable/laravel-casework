# Security Policy

## Supported versions

| Version | Supported |
|---|---|
| 1.x | ✅ |

Security fixes land on the latest minor of the current major.

## Reporting a vulnerability

Please report security vulnerabilities **privately**, not through
public issues or pull requests.

- Use GitHub's **[Report a vulnerability](https://github.com/syriable/laravel-casework/security/advisories/new)**
  (Security → Advisories) to open a private advisory, or
- email **info@syriable.com** with details and reproduction steps.

You'll get an acknowledgement, and we'll work with you on a fix and a
coordinated disclosure. Please give us reasonable time to release a fix
before any public disclosure.

## Scope notes

A few boundaries are by design, not vulnerabilities — see the guides:

- **Audit immutability is model-layer, not SQL-layer.** `PreventsMutation`
  guards the Eloquent path; direct database access is outside the
  package's control (see `docs/guide/audit.md`). Treat DB credentials
  as the real trust boundary.
- **Policy overrides own their consequences.** Registering a permissive
  policy removes package protections deliberately
  (`docs/guide/authorization.md`).
- **Automation stages are privileged code.** Intake/triage stages and
  rebound actions run with System authority; the package only ever
  instantiates them from config/container, never from request input
  (`docs/guide/automation.md`).

Reports that these documented boundaries exist are not treated as
vulnerabilities; reports that the package fails to hold a boundary it
claims (e.g. an unaudited domain operation, a forgeable audit entry, a
transition bypassing its guards) are.
