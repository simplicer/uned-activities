# UNED Activities Finder v0.10.1-alpha

A monorepo project that scrapes UNED extension activities, stores normalized data in Supabase/Postgres, exposes a Zalando-compliant REST API, and provides a React UI in 6 languages.

## Overview

- **Backend:** PHP 8.3+ with Slim 4, following DDD + Clean Architecture
- **Frontend:** React + Vite + TypeScript + shadcn/ui
- **Database:** PostgreSQL via Supabase self-host
- **Languages:** Spanish, English, Catalan, Valencian, Basque, Galician

## Project Structure

```
/                           # Monorepo root
├── src/                    # PHP production code (PascalCase)
│   ├── CatalogHarvest/     # Bounded contexts
│   ├── CatalogQuery/
│   ├── UserPreferences/
│   ├── Notifications/
│   └── Shared/
├── apps/                   # Entry points
│   ├── HttpApi/            # Slim 4 API
│   ├── CliJobs/            # CLI commands
│   └── Bootstrap/          # DI container
├── tests/                  # Unit, Integration, Acceptance
├── e2e/                    # End-to-end tests (Playwright)
├── web/                    # React + Vite frontend
├── infra/                  # Docker compose
├── container/              # Containerfiles
├── doc/                    # OpenAPI, architecture docs
└── etc/                    # Versioned config
```

## Quick Start

### Prerequisites

- Docker and Docker Compose
- PHP 8.3+
- Node.js 20+
- Composer

### Start Services

```bash
# Start Supabase and services
make infra-up

# Install backend dependencies
make composer-install

# Install frontend dependencies
make web-install

# Start backend dev server
make server

# Start frontend dev server
make web-dev
```

### Environment (important)

Ensure these variables are set (see `.env.example`):

- `JWT_SECRET` (required for auth)
- `SMTP_HOST/SMTP_USER/SMTP_PASSWORD` (magic link emails)
- `OPENROUTER_API_KEY` (AI extraction + embeddings)
- `OPENROUTER_EMBEDDING_MODEL` (default `nomic-ai/nomic-embed-text-v1.5`)
- `CORS_ALLOWED_ORIGINS` (frontend origin)

### Run Tests

```bash
# Backend tests
make test

# Frontend tests
make web-test

# Run harvest job
make harvest

# Run notification digest
make digest
```

## API Documentation

- **OpenAPI Spec:** `doc/api-specs/openapi.yaml`
- **Status endpoint:** `GET /status`
- **Version endpoint:** `GET /version`
- **Activities:** `GET /v1/activities`

## Development

### Backend

```bash
# Run quality gates
make quality

# Format code
make fmt

# Static analysis
make analyze
```

### Frontend

```bash
# Type check
make web-typecheck

# Lint
make web-lint

# Build for production
make web-build
```

## Architecture

See [CLAUDE.md](CLAUDE.md) for quick start with AI agents.

- [AGENTS.md](doc/architecture/AGENTS.md) — Bounded contexts, ports, technology stack
- [DOMAIN.md](doc/architecture/DOMAIN.md) — Domain model, entities, value objects

## License

This project is licensed under the **MIT License** - see [LICENSE](LICENSE) file.
