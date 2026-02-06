# Version Management

## Current Version: 0.21.0-alpha

### Semantic Versioning Strategy
- MAJOR: Breaking changes (not incremented in alpha)
- MINOR: New features (feat: commits) → Currently 20
- PATCH: Bug fixes (fix: commits) → Currently 23
- Status: -alpha (pre-production)

### Calculation
From git history since v0.19.0-alpha:
- 2 feat (PHPCS, backup/restore scripts) → 0.21.0
- 23 fixes (type safety, tests, security) → reset to 0

### Version History
| Version | Date | Changes |
|---------|------|---------|
| 0.21.0-alpha | 2026-02-06 | Database backup/restore scripts, improved E2E tests (20 tests) |
| 0.20.23-alpha | 2026-02-06 | PHPStan 0 errors, type safety, PSR-12 compliance |
| 0.19.0-alpha | 2026-02-04 | Post-audit remediation (screaming architecture, PHP-FPM, migrations) |
| 0.10.1-alpha | 2025-01-15 | Initial alpha release |
