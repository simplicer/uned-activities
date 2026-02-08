# CLAUDE.md — UNED Activities Finder

**Quick Start Guide for Claude and other AI agents working on this project.**

## Project Overview

UNED Activities Finder is a monorepo project that:
1. Scrapes UNED extension activities from their website
2. Stores normalized data in PostgreSQL
3. Exposes a Zalando-compliant REST API (`/v1`)
4. Provides a React+Vite+shadcn UI in 6 languages

## Essential Documentation

**Read these first, in order:**

1. **[AGENTS.md](doc/architecture/AGENTS.md)** — Bounded contexts, ports, technology stack
2. **[DOMAIN.md](doc/architecture/DOMAIN.md)** — Domain model, entities, value objects, rules
3. **[PROJECT_MANUAL.md](doc/architecture/PROJECT_MANUAL.md)** — Full project manual (if exists)

## Quick Reference

### Repository Structure

```
/                        # Monorepo root
├── src/                 # PHP production code ONLY (PascalCase dirs/files)
│   ├── CatalogHarvest/  # Bounded contexts
│   ├── CatalogQuery/
│   ├── UserPreferences/
│   ├── Notifications/
│   └── Shared/
│
├── apps/                # Entrypoints (Slim HTTP API, CLI jobs)
├── tests/               # Unit/Integration/Acceptance tests
├── e2e/                 # End-to-end tests (Playwright)
├── web/                 # React+Vite+TS frontend
├── infra/               # Docker compose, deployment scripts
├── container/           # Containerfiles for builds
├── doc/                 # OpenAPI spec, architecture docs, runbooks
└── etc/                 # Versioned config (no secrets)
```

### Naming Rules (Strict)

| Context | Convention | Examples |
|---------|-----------|----------|
| Backend PHP directories | PascalCase | `src/CatalogHarvest/Domain/` |
| Backend PHP files | PascalCase | `DiscoverActivities.php` |
| Frontend files | kebab-case | `activity-list.tsx` |
| API paths | kebab-case | `/v1/price-snapshots` |
| UUIDs | lowercase with hyphens | `123e4567-e89b-12d3-a456-426614174000` |

### API Standards

- **Version:** `/v1` (even during development)
- **Paths:** kebab-case, Zalando style
- **Methods:** `GET` (safe), `PUT` (idempotent create), `PATCH` (partial update), `DELETE`
- **Errors:** `application/problem+json` (RFC7807 + Zalando profile)
- **Required endpoints:** `GET /status`, `GET /version`
- **OpenAPI spec:** `doc/api-specs/openapi.yaml`

### Architecture Rules

```
Dependency direction (strict):
Infrastructure → Application → Domain
```

**Application Layer** (`src/*/Application/`):
- **ONLY** contains use-cases
- Allowed: use-case classes, input/output DTOs
- Forbidden: repositories, HTTP clients, helpers, domain logic

**Domain Layer** (`src/*/Domain/`):
- Entities, value objects, domain services
- Ports/interfaces (repository interfaces)

**Infrastructure Layer** (`src/*/Infrastructure/`):
- Repository implementations
- HTTP clients, adapters
- External service integrations

### Testing Strategy

| Type | Location | Scope |
|------|----------|-------|
| Unit | `tests/unit/` | One test suite per use-case |
| Integration | `tests/integration/` | Real DB, real adapters |
| Acceptance | `tests/acceptance/` | API scenarios |
| E2E | `e2e/playwright/` | Full stack + UI |

### Development Commands

```bash
# Backend
make composer-install    # Install PHP dependencies
make test               # Run all tests
make test-unit          # Unit tests only
make lint               # PHP CS Fixer (dry-run)
make lint-fix           # PHP CS Fixer (fix)
make analyze            # PHPStan static analysis
make fmt                # Format code

# Frontend
make web-install        # Install Node dependencies
make web-dev            # Start dev server
make web-build          # Production build
make web-test           # Run frontend tests
make web-lint           # ESLint + TypeScript check

# Infrastructure
make infra-up           # Start database + services
make infra-down         # Stop services
make infra-logs         # View logs

# Harvest & Notifications
make harvest            # Run activity harvest
make digest             # Run notification digest
```

### Bounded Contexts Quick Ref

| Context | Purpose | Key Use-Cases |
|---------|---------|---------------|
| **CatalogHarvest** | Scrape & store activities | DiscoverActivities, RefreshActivity, RecordActivitySnapshot |
| **CatalogQuery** | Public query API | ListActivities, GetActivity, GetActivityFacets |
| **UserPreferences** | Profiles & saved searches | PutUserProfile, PutSavedSearch |
| **Notifications** | Match & notify | RunNotificationDigest, DeliverEmailNotification |

### Localization (i18n)

**Supported languages:** `es`, `en`, `ca`, `val`, `eu`, `gl`

**Frontend:** `react-i18next` with JSON dictionaries in `web/src/i18n/locales/`

**Keys:** All keys in English; no inline literals in components.

### Important Constraints

1. **No kebab-case in PHP code** — directories, files, classes must be PascalCase
2. **No reverse proxy in repo** — Dokploy provides Traefik + SSL
3. **Rate limiting mandatory** — public endpoints require web token
4. **All English** — docs, commits, comments must be in English
5. **TDD mandatory** — write tests first for each use-case

### When Working on This Project

1. **Before coding:** Read AGENTS.md and DOMAIN.md
2. **Before API changes:** Update `doc/api-specs/openapi.yaml`
3. **When adding use-cases:** Create unit test suite in `tests/unit/<Context>/`
4. **Before committing:** Run all quality gates (lint, analyze, test)
5. **Commit format:** Conventional Commits in English

### Getting Help

- **Architecture questions:** See AGENTS.md
- **Domain questions:** See DOMAIN.md
- **API contract:** See `doc/api-specs/openapi.yaml`
- **Local development:** See `doc/runbooks/`

---

**Last updated:** Phase 0 — Foundations
