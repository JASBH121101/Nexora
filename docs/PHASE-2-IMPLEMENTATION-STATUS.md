# NEXORA and JB Expert QA — implementation status (2026-11-14)

## Implemented in isolated branches

### NEXORA — `feature/nexora-phase2-approved-upgrades`

- Shared Library Engine 0.2.0 ported into the canonical NEXORA repository, under `app/Core/Library`.
- Setup catalog facades delegate to shared catalogs; setup locale and telephone normalization updated.
- Canonical interface locales `es-419` and `en`; `es` remains a compatibility alias.
- Currency-specific precision checks, ISO currency identifiers in display output, and `DECIMAL(16,4)` product prices.
- Stable unit catalog references and localized unit labels, preserving legacy unit codes.
- Additive migrations for unit catalog references and price precision; down migration refuses to discard meaningful extra precision.
- Basic inventory quantity field and least-privilege inventory assistant role.
- Permission-aware native CSV, JSON and XML exports; conditional ZIP including original photographs; export history.
- Tests added for library, localization, inventory and exports.

### JB Expert QA — `feature/language-qa-engine`

- Deterministic Language QA Engine for PHP/JSON translation resources.
- `es-419` / `en` locale normalization, placeholder checks, glossary, review states and in-memory Translation Memory.
- Read-only audit integrated into the project auditor and a dedicated CLI.
- No paid service, no AI request and no translation resource is executed as PHP.
- Draft PR #36 targets `integration/jb-expert-qa`.

## Not certified yet

Neither feature branch has a verified current-head green test run in the available session. Composer/PHPUnit/Pint could not be executed here. The new tests are committed but must run in a Composer-enabled environment before merge. Node Language QA regression tests also have not returned a recorded GitHub Actions run.

No branch has been merged or deployed. `main` and production remain unchanged.

## Not yet implemented / waiting for owner decisions

- PDF and XLSX export dependencies and PHP/VPS compatibility.
- Export history retention duration and export size policy.
- Backup cadence, retention, destination, encryption key ownership and restore approval.
- MFA enforcement policy (who must enroll and when).
- Audit log retention and immutability policy.
- Update signing keys, release channels, and package trust distribution.
- Automatic application of updates, destructive migrations and rollback policy.
- Shared deployment/package boundaries between Core, Admin and Site Engine.

The Site Engine domain/DNS/TLS improvements are deliberately deferred to the next work session, as requested.

## Safety boundary

All changes are isolated to the branches listed above. No change is committed to `main`, no PR is merged, no deployment is triggered and no paid service or package is enabled.
