# AGENTS.md — Architecture Agents & Standards

This document defines the architectural agents, components, and mandatory standards for the UNED Activities Finder project.

The key words "MUST", "MUST NOT", "REQUIRED", "SHALL", "SHALL NOT", "SHOULD", "SHOULD NOT", "RECOMMENDED", "MAY", and "OPTIONAL" in this document are to be interpreted as described in RFC 2119.

## Table of Contents

1. [Mandatory Standards](#mandatory-standards)
2. [External Agents](#external-agents)
3. [Bounded Contexts](#bounded-contexts)
4. [Cross-Cutting Concerns](#cross-cutting-concerns)
5. [Technology Stack](#technology-stack)

---

## Mandatory Standards

### File Organization

- All PHP source files MUST be placed in `src/` (production code) or `apps/` (entrypoints)
- PHP directory and file names MUST use PascalCase (PSR-4)
- Frontend and infrastructure files MUST use kebab-case
- NO PHP file MAY exceed 300 lines; if exceeded, it MUST be split
- Routes MUST be placed in `src/Shared/Infrastructure/Routing/`
- Dependency Injection setup MUST be in `src/Shared/Infrastructure/DependencyInjection/`

### Persistence Layer

- The project MUST use native PDO ONLY
- Doctrine DBAL, Doctrine ORM, or similar abstraction layers MUST NOT be used
- Migrations MUST be plain SQL files in `infra/migrations/`
- All database queries MUST use prepared statements
- Repository implementations MUST reside in `{Context}/Infrastructure/Persistence/`

### Logging

- All logs MUST be formatted in Loki JSON format
- Log entries MUST include: timestamp, level, message, context (request_id, user_id, action)
- The log output MUST be configurable (stdout for Docker, file for bare metal)
- Monolog with Loki formatter MUST be used

### Backup Strategy

- All persistent data MUST be stored in `/data` volume
- The `/data` volume structure MUST be:
  - `/data/postgres` - Database files
  - `/data/uploads` - User uploads (if any)
  - `/data/logs` - Application logs (if persisted)
- Docker compose MUST mount this volume to a named volume `uned_data`
- Backups MUST be performable via rsnapshot on the `uned_data` volume

### Testing

- E2E tests MUST be placed in `tests/e2e/`
- Unit tests MUST be placed in `tests/unit/{Context}/`
- Integration tests MUST be placed in `tests/integration/`
- All tests MUST follow TDD: test first, implement after

---

## External Agents

### UNED Website (Source System)

The UNED website is an external HTTP service that hosts the extension activities catalog.

This agent:
- MAY become unavailable without notice
- MAY change HTML structure without warning
- MUST be accessed with rate limiting (minimum 1 second between requests)
- MUST be treated as untrusted input

All harvesting logic MUST be idempotent and fault-tolerant.

### Supabase Auth (Authentication Provider)

This agent provides user authentication and JWT token issuance.

Interactions:
- MUST validate JWT tokens on each authenticated request
- MUST support anonymous sessions for public API access
- MUST enforce ownership: users MAY only access their own resources

### Email Service (Notification Delivery)

This agent delivers notification emails to users.

Configuration:
- MUST be stubbed for local development
- MUST support SMTP for production
- SHOULD support multiple providers (SMTP, API)

---

## Bounded Contexts

### CatalogHarvest

**Purpose:** Build and maintain the catalog dataset.

**Core Entities:**
- `Activity` - Extension course entity
- `ActivitySnapshot` - Historical state
- `PriceSnapshot` - Price history
- `HarvestRun` - Execution record
- `HarvestFailure` - Failure tracking

**Use-Cases (Application Layer):**
- `RunHarvest` - Orchestrate full pipeline
- `DiscoverActivities` - Extract URLs from index pages
- `RefreshActivity` - Fetch and normalize details
- `RecordActivitySnapshot` - Store current state
- `DetectActivityChange` - Compare with previous state
- `RecordPriceSnapshot` - Store price changes
- `ArchiveMissingActivities` - Mark not-found activities

**Ports (Domain Layer):**
- `HtmlFetcher` - Interface for HTTP fetching
- `ActivityRepository` - Persistence interface
- `ActivitySnapshotRepository` - Snapshot persistence
- `PriceSnapshotRepository` - Price history persistence
- `HarvestRunRepository` - Harvest execution records
- `Clock` - Time abstraction for testing

**Triggers:** Cron job or manual CLI execution

### CatalogQuery

**Purpose:** Query API with filtering, pagination, and faceting.

**Core Entities:**
- `ActivityFilter` - Filter criteria
- `ActivitySort` - Sorting specification
- `Facet` - Filter aggregates

**Use-Cases:**
- `ListActivities` - Query with filters/pagination
- `GetActivity` - Single activity by ID
- `GetActivityPriceSnapshots` - Price history
- `GetActivityChanges` - Change history
- `GetActivityFacets` - Filter aggregates

**Ports:**
- `ActivityRepository` - Read operations
- `ActivitySnapshotRepository` - Historical data
- `PriceSnapshotRepository` - Price history
- `ChangeLogRepository` - Activity changes

**Triggers:** HTTP GET requests to `/v1/activities`

### UserPreferences

**Purpose:** Manage user profiles and saved searches.

**Core Entities:**
- `UserProfile` - User preferences
- `SavedSearch` - Named filter configuration

**Use-Cases:**
- `PutUserProfile` - Create/update profile
- `GetUserProfile` - Retrieve profile
- `PatchUserProfile` - Partial update
- `PutSavedSearch` - Create/update search
- `ListSavedSearches` - List user searches
- `PatchSavedSearch` - Partial update
- `DeleteSavedSearch` - Remove search

**Ports:**
- `UserProfileRepository` - Profile persistence
- `SavedSearchRepository` - Search persistence

**Triggers:** HTTP requests to `/v1/users/{user-id}/`

### Notifications

**Purpose:** Compute and deliver notifications.

**Core Entities:**
- `Notification` - User notification
- `NotificationDelivery` - Delivery record
- `NotificationDigest` - Batch notification

**Use-Cases:**
- `RunNotificationDigest` - Evaluate saved searches
- `ListNotifications` - Get user notifications
- `MarkNotificationsRead` - Mark as read
- `DeliverEmailNotification` - Send email
- `DeduplicateNotifications` - Merge duplicates

**Ports:**
- `NotificationRepository` - Notification persistence
- `SavedSearchRepository` - Find searches to evaluate
- `ActivityRepository` - Find matching activities
- `EmailSender` - Email delivery interface

**Triggers:** Cron job or manual CLI execution

### Shared

**Purpose:** Common domain concepts and utilities.

**Shared Value Objects:**
- `ActivityId` - Activity UUID
- `UserId` - User UUID (from Supabase)
- `SavedSearchId` - Saved search UUID
- `NotificationId` - Notification UUID
- `Email` - Email address value object
- `Money` - Monetary amount
- `DateRange` - Date interval
- `Modality` - Enum (online, in-person, hybrid)
- `Language` - Enum (es, en, ca, val, eu, gl)

**Shared Infrastructure:**
- `Routing/` - HTTP route definitions (ALL contexts)
- `DependencyInjection/` - DI container setup
- `Persistence/` - Abstract PDO repository base
- `Logging/` - Loki logger configuration
- `Middleware/` - Shared middleware (rate limit, auth, etc.)

---

## Cross-Cutting Concerns

### Rate Limiting

Rate limiting MUST be implemented to prevent abuse.

Requirements:
- IP-based rate limiting
- Token bucket algorithm (RECOMMENDED)
- 60 req/min for public endpoints
- 300 req/min for authenticated users
- Response: `429 Too Many Requests` with `Retry-After` header

### Authentication & Authorization

All authenticated endpoints MUST:

- Validate Supabase JWT tokens
- Extract user ID from token
- Enforce ownership: users MAY access only their own resources

Public endpoints MUST:

- Require web session token
- Validate Origin/Referer headers as soft signal

### Localization

The application MUST support 6 languages: es, en, ca, val, eu, gl.

Implementation:
- Frontend: react-i18next with JSON dictionaries
- Backend: Accept `Accept-Language` header
- All translatable content MUST be stored with language field
- Default language: `es`

### Error Handling

All errors MUST return `application/problem+json` (RFC 7807).

Required fields:
- `type` - URI reference to error type
- `title` - Short human-readable description
- `status` - HTTP status code
- `detail` - Human-readable explanation
- `instance` - URI to specific occurrence

### File Size Limits

To maintain code quality:

- NO PHP file MAY exceed 300 lines
- If exceeded, file MUST be split by responsibility
- Common reasons to split:
  - Multiple responsibilities in one class
  - Too many methods in one class
  - Large configuration arrays

---

## Technology Stack

### Backend

Mandatory:
- PHP 8.3+
- Slim 4 (HTTP framework)
- PDO (database access - NO Doctrine)
- PostgreSQL (database)
- Guzzle (HTTP client)
- Monolog (logging with Loki formatter)

Prohibited:
- Doctrine DBAL
- Doctrine ORM
- Any ORM abstraction layer

### Frontend

Required:
- React 18+
- TypeScript
- Vite (build tool)
- shadcn/ui (components)
- react-i18next (i18n)
- Vitest (unit tests)
- Playwright (E2E tests)

### Infrastructure

Required:
- Docker (containerization)
- Docker Compose (orchestration)
- Supabase self-host (auth + database)
- Traefik (reverse proxy, via Dokploy)

Volume Structure:
```
/data               # Named volume for backups
  /postgres        # Database data
  /uploads         # User uploads
  /logs            # Application logs
```

### Development Tools

Required:
- PHPStan (static analysis, level 8)
- PHPUnit (testing)
- PHP CS Fixer (code style)
- ESLint (frontend linting)
- TypeScript (type checking)

---

## Dependency Rules

The following dependency directions MUST be respected:

```
Infrastructure → Application → Domain
```

Permissions:
- Domain MAY depend on nothing outside Domain
- Application MAY depend on Domain ONLY
- Infrastructure MAY depend on Application and Domain
- Shared/Infrastructure MAY be used by all contexts

Prohibitions:
- Domain MUST NOT depend on Infrastructure
- Application MUST NOT depend on Infrastructure
- Use-cases MUST NOT contain persistence logic
- Use-cases MUST NOT contain HTTP client logic

---

## File Naming Conventions

| Location | Convention | Example |
|----------|------------|---------|
| src/ (PHP) | PascalCase | `Activity.php`, `DiscoverActivities.php` |
| apps/ (PHP) | PascalCase | `Container.php`, `DiscoverCommand.php` |
| web/ (Frontend) | kebab-case | `activity-list.tsx`, `language-switcher.tsx` |
| infra/ | kebab-case | `compose.yaml`, `local.env.example` |
| API paths | kebab-case | `/v1/price-snapshots` |
| UUIDs | lowercase with hyphens | `123e4567-e89b-12d3-a456-426614174000` |

---

## Project Structure

```
/
├── src/                          # PHP production code (PascalCase)
│   ├── CatalogHarvest/
│   │   ├── Domain/
│   │   │   ├── Entity/
│   │   │   ├── ValueObject/
│   │   │   └── Port/
│   │   ├── Application/          # ONLY use-cases
│   │   └── Infrastructure/
│   │       ├── Http/
│   │       └── Persistence/
│   ├── CatalogQuery/
│   ├── UserPreferences/
│   ├── Notifications/
│   └── Shared/
│       ├── Domain/               # Shared value objects
│       └── Infrastructure/
│           ├── Routing/          # ALL route definitions
│           ├── DependencyInjection/  # DI setup
│           ├── Logging/          # Loki logger
│           └── Persistence/      # PDO base classes
│
├── apps/                         # Entry points (PascalCase)
│   ├── HttpApi/                  # Slim application
│   │   └── public/
│   │       └── index.php
│   └── CliJobs/                  # CLI commands
│       └── bin/
│
├── tests/                        # ALL tests
│   ├── unit/
│   ├── integration/
│   ├── acceptance/
│   └── e2e/                      # E2E tests (moved from root)
│
├── web/                          # Frontend (kebab-case)
│   └── src/
│
├── infra/                        # Infrastructure
│   ├── migrations/               # SQL migrations
│   ├── scripts/
│   └── env/
│
├── container/                    # Containerfiles
├── doc/                          # Documentation
│   ├── api-specs/
│   ├── architecture/
│   └── runbooks/
└── etc/                          # Versioned config
```

---

## Quality Gates

Before committing, ALL of the following MUST pass:

Backend:
- `composer phpstan` - No errors
- `composer cs-check` - No style violations
- `composer phpunit` - All tests pass
- `composer audit` - No critical vulnerabilities

Frontend:
- `npm run lint` - No ESLint errors
- `npm run typecheck` - No TypeScript errors
- `npm run test` - All tests pass

Additional:
- NO PHP file exceeds 300 lines
- NO Doctrine dependencies in composer.json
- All new code has corresponding tests
