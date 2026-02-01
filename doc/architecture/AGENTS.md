# AGENTS.md — Architecture Agents & Responsibilities

This document describes the architectural agents (components, services, and systems) that participate in the UNED Activities Finder domain.

## Table of Contents

1. [External Agents](#external-agents)
2. [Bounded Contexts](#bounded-contexts)
3. [Cross-Cutting Concerns](#cross-cutting-concerns)

---

## External Agents

### UNED Website (Source System)

**Type:** External HTTP Service

**Responsibilities:**
- Hosts the extension activities catalog
- Provides activity index pages
- Provides activity detail pages

**Interaction Protocol:**
- HTTP GET requests
- HTML response parsing
- Rate-limited to avoid abuse

**Reliability:**
- External dependency; may be unavailable or change structure without notice
- All harvesting must be idempotent and fault-tolerant

### Supabase Auth (Authentication Provider)

**Type:** External Authentication Service (self-hosted)

**Responsibilities:**
- User registration and authentication
- JWT token issuance and validation
- Anonymous session tokens for public web access

**Interaction Protocol:**
- JWT validation
- GoTrue API for session management

### Email Service (Notification Delivery)

**Type:** External Service (SMTP or API)

**Responsibilities:**
- Deliver notification digests to users

**Interaction Protocol:**
- SMTP or REST API
- Configurable per environment (stubbed for local)

---

## Bounded Contexts

### CatalogHarvest

**Purpose:** Build and maintain the catalog dataset by discovering and harvesting activities from UNED.

**Core Domain Entities:**
- `Activity`: The core entity representing an extension course
- `ActivitySnapshot`: Historical state of an activity at a point in time
- `PriceSnapshot`: Historical pricing data for an activity
- `HarvestRun`: A single execution of the harvest process
- `HarvestFailure`: Record of activities that failed to harvest

**Use-Cases:**
- `RunHarvest`: Orchestrate the full harvest pipeline
- `DiscoverActivities`: Find activity URLs from index pages
- `RefreshActivity`: Fetch and normalize activity detail
- `RecordActivitySnapshot`: Store current activity state
- `DetectActivityChange`: Compare current state with previous snapshot
- `RecordPriceSnapshot`: Store price changes over time
- `ArchiveMissingActivities`: Mark activities not found in latest harvest

**Ports (Interfaces):**
- `HtmlFetcher`: Fetch HTML content from URLs
- `ActivityRepository`: Persist and retrieve activities
- `ActivitySnapshotRepository`: Persist and retrieve snapshots
- `PriceSnapshotRepository`: Persist and retrieve price history
- `HarvestRunRepository`: Record harvest execution metadata
- `Clock`: Get current time (for testing)

**Triggers:**
- Scheduled cron job (CLI)
- Manual command execution

---

### CatalogQuery

**Purpose:** Provide query capabilities for the catalog API with filtering, pagination, and faceting.

**Core Domain Entities:**
- `ActivityFilter`: Criteria for filtering activities
- `ActivitySort`: Sorting specification
- `Facet`: Aggregate information about available filter values

**Use-Cases:**
- `ListActivities`: Query activities with filters, pagination, and sorting
- `GetActivity`: Retrieve single activity by ID
- `GetActivityPriceSnapshots`: Get price history for an activity
- `GetActivityChanges`: Get change history for an activity
- `GetActivityFacets`: Compute filter facet aggregates

**Ports (Interfaces):**
- `ActivityRepository`: Read activities
- `ActivitySnapshotRepository`: Read historical data
- `PriceSnapshotRepository`: Read price history
- `ChangeLogRepository`: Read activity changes

**Triggers:**
- HTTP GET requests to /v1/activities

---

### UserPreferences

**Purpose:** Manage user profiles and saved search configurations.

**Core Domain Entities:**
- `UserProfile`: User preferences and settings
- `SavedSearch`: Named filter configuration for notifications

**Use-Cases:**
- `PutUserProfile`: Create or update user profile
- `GetUserProfile`: Retrieve user profile
- `PatchUserProfile`: Partially update user profile
- `PutSavedSearch`: Create or update a saved search
- `ListSavedSearches`: Get all saved searches for a user
- `PatchSavedSearch`: Partially update a saved search
- `DeleteSavedSearch`: Remove a saved search

**Ports (Interfaces):**
- `UserProfileRepository`: Persist and retrieve profiles
- `SavedSearchRepository`: Persist and retrieve saved searches

**Triggers:**
- HTTP requests to /v1/users/{user-id}/profiles and saved-searches

---

### Notifications

**Purpose:** Compute and deliver notifications based on activity changes and user saved searches.

**Core Domain Entities:**
- `Notification`: A message about matching activities
- `NotificationDelivery`: Record of delivery attempt
- `NotificationDigest`: Batch of notifications for a user

**Use-Cases:**
- `RunNotificationDigest`: Evaluate saved searches against new/changed activities
- `ListNotifications`: Get notifications for a user
- `MarkNotificationsRead`: Mark notifications as read
- `DeliverEmailNotification`: Send notification via email
- `DeduplicateNotifications`: Merge duplicate notifications

**Ports (Interfaces):**
- `NotificationRepository`: Persist and retrieve notifications
- `SavedSearchRepository`: Find saved searches to evaluate
- `ActivityRepository`: Find matching activities
- `EmailSender`: Send email notifications

**Triggers:**
- Scheduled cron job (CLI)
- Manual command execution

---

### Shared

**Purpose:** Provide common domain concepts and utilities used across bounded contexts.

**Shared Domain Concepts:**
- `ActivityId`: Value object for activity UUID
- `UserId`: Value object for user UUID
- `SavedSearchId`: Value object for saved search UUID
- `NotificationId`: Value object for notification UUID
- `Email`: Value object for email address
- `Money`: Value object for monetary amounts
- `DateRange`: Value object for date intervals
- `Modality`: Enum for activity modality (online, in-person, hybrid)
- `Language`: Enum for supported languages

**Shared Application Concepts:**
- Problem details error response (RFC7807)
- Pagination parameters
- Sorting specifications

---

## Cross-Cutting Concerns

### Rate Limiting

**Purpose:** Prevent abuse and ensure fair API usage.

**Implementation:**
- IP-based rate limiting using token bucket or leaky bucket
- Different limits for public vs authenticated endpoints
- Stored in Redis or in-memory

### Authentication & Authorization

**Purpose:** Verify user identity and enforce access control.

**Implementation:**
- Supabase JWT validation
- Ownership enforcement: users can only access their own resources
- Public endpoints require web session token

### Localization

**Purpose:** Support 6 languages (es, en, ca, val, eu, gl).

**Implementation:**
- Frontend: react-i18next with JSON dictionaries
- Backend: Store translatable content; accept `Accept-Language` header

### Logging

**Purpose:** Observability and debugging.

**Implementation:**
- Structured logging (JSON)
- Log levels: DEBUG, INFO, WARNING, ERROR
- Contextual information: request ID, user ID, action

### Error Handling

**Purpose:** Consistent error responses across the API.

**Implementation:**
- All errors return `application/problem+json`
- Fields: type, title, status, detail, instance
- Standardized error types for common scenarios

---

## Technology Stack

### Backend
- **Framework:** Slim 4 (PHP 8.3+)
- **Database:** PostgreSQL (via Supabase self-host)
- **Authentication:** Supabase Auth (GoTrue)
- **HTTP Client:** Guzzle
- **Testing:** PHPUnit
- **Static Analysis:** PHPStan

### Frontend
- **Framework:** React 18+ with TypeScript
- **Build:** Vite
- **UI Components:** shadcn/ui
- **Internationalization:** react-i18next
- **Testing:** Vitest + Playwright

### Infrastructure
- **Container:** Docker
- **Orchestration:** Docker Compose
- **Deployment:** Dokploy
- **Reverse Proxy:** Traefik (provided by Dokploy)
