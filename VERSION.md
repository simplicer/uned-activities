# Version Management

## Current Version: 1.5.0

### Semantic Versioning Strategy
- MAJOR: Breaking changes
- MINOR: New backwards-compatible features
- PATCH: Backwards-compatible bug fixes

### Release Notes
- v1.0.0 is the first stable release.
- v1.1.0 adds the database dump restore script; 1.1.x patches harden migrations, scheduling and restore on Alpine/Swarm (version artifacts reconciled to the derived history count).
- Docker build/push/deploy flow aligned for production.
- Containerfiles hardened and optimized for production builds.

### Version History
| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-02-08 | Stable release, production registry integration, container build optimization |
| 1.1.0 | 2026-02-08 | Add dump restore script (feat) |
| 1.1.3 | 2026-09-20 | Reconcile version artifacts with derived history (2 refactors, 5 fixes since 1.1.0) |
| 1.1.12 | 2026-09-20 | Security audit remediation: harvest scheduling visibility, catalog URL-drift persistence, magic-link single-use and forced-login, scraped-URL allowlists, email escaping, rate-limit hardening, digest dedupe and scheduling, Gemini key header, build-context secrets |
| 1.4.1 | 2026-09-23 | Proximity-to-today ordering with sort selector, enrollment-open filter, date-range calendar presets, automatic closing of past activities, full SMTP environment and observable send failures |
| 0.21.0-alpha | 2026-02-06 | Database backup/restore scripts, improved E2E tests (20 tests) |
| 0.20.23-alpha | 2026-02-06 | PHPStan 0 errors, type safety, PSR-12 compliance |
| 0.19.0-alpha | 2026-02-04 | Post-audit remediation (screaming architecture, PHP-FPM, migrations) |
| 0.10.1-alpha | 2025-01-15 | Initial alpha release |
