# DOMAIN.md — Domain Model

This document describes the core domain model for the UNED Activities Finder.

## Table of Contents

1. [Core Entities](#core-entities)
2. [Value Objects](#value-objects)
3. [Enums](#enums)
4. [Aggregates](#aggregates)
5. [Domain Rules](#domain-rules)

---

## Core Entities

### Activity

The core entity representing a UNED extension course/activity.

**Attributes:**

| Name | Type | Description |
|------|------|-------------|
| `id` | `ActivityId` | Unique identifier (UUID) |
| `unedId` | `string` | Original ID from UNED system |
| `title` | `string` | Activity title (multilingual) |
| `description` | `string|null` | Full description (HTML) |
| `url` | `string` | Canonical URL on UNED website |
| `startDate` | `DateTimeImmutable|null` | Course start date |
| `endDate` | `DateTimeImmutable|null` | Course end date |
| `modality` | `Modality` | Delivery method (online, in-person, hybrid) |
| `center` | `string|null` | Associated center/branch |
| `typology` | `string|null` | Course typology/category |
| `area` | `string|null` | Knowledge area |
| `price` | `Money|null` | Current price |
| `enrollmentOpen` | `bool` | Whether enrollment is open |
| `enrollmentStartDate` | `DateTimeImmutable|null` | Enrollment start date |
| `enrollmentEndDate` | `DateTimeImmutable|null` | Enrollment end date |
| `createdAt` | `DateTimeImmutable` | When first discovered |
| `updatedAt` | `DateTimeImmutable` | When last updated |
| `hash` | `string` | Content hash for change detection |
| `status` | `ActivityStatus` | Current status (active, archived) |

**Behavior:**
- Can be refreshed from UNED source data
- Generates hash for change detection
- Tracks snapshot history

**Invariants:**
- `id` is immutable
- `unedId` + `url` uniquely identify an activity
- `startDate` <= `endDate` (when both present)
- Archived activities cannot be reactivated by harvest (must be explicit)

---

### ActivitySnapshot

Historical state of an Activity at a point in time.

**Attributes:**

| Name | Type | Description |
|------|------|-------------|
| `id` | `ActivitySnapshotId` | Unique identifier (UUID) |
| `activityId` | `ActivityId` | Reference to activity |
| `capturedAt` | `DateTimeImmutable` | When snapshot was taken |
| `data` | `array` | Snapshot of activity state (JSON) |
| `hash` | `string` | Content hash of this snapshot |
| `changeType` | `ChangeType|null` | Type of change from previous snapshot |

**Invariants:**
- Immutable once created
- `capturedAt` is monotonically increasing for an activity

---

### PriceSnapshot

Historical pricing data for an Activity.

**Attributes:**

| Name | Type | Description |
|------|------|-------------|
| `id` | `PriceSnapshotId` | Unique identifier (UUID) |
| `activityId` | `ActivityId` | Reference to activity |
| `capturedAt` | `DateTimeImmutable` | When price was observed |
| `price` | `Money` | Price at this point in time |
| `currency` | `string` | Currency code (EUR) |

**Invariants:**
- Immutable once created
- `capturedAt` is monotonically increasing for an activity

---

### HarvestRun

Represents a single execution of the harvest process.

**Attributes:**

| Name | Type | Description |
|------|------|-------------|
| `id` | `HarvestRunId` | Unique identifier (UUID) |
| `startedAt` | `DateTimeImmutable` | When harvest started |
| `completedAt` | `DateTimeImmutable|null` | When harvest completed |
| `status` | `HarvestStatus` | running, completed, failed |
| `discoveredCount` | `int` | Number of activities discovered |
| `refreshedCount` | `int` | Number of activities refreshed |
| `failedCount` | `int` | Number of activities that failed |
| `error` | `string|null` | Error message if failed |

**Invariants:**
- `startedAt` is immutable
- Only transitions: running → completed or running → failed

---

### HarvestFailure

Record of an activity that failed to harvest.

**Attributes:**

| Name | Type | Description |
|------|------|-------------|
| `id` | `HarvestFailureId` | Unique identifier (UUID) |
| `harvestRunId` | `HarvestRunId` | Reference to harvest run |
| `activityId` | `ActivityId|null` | Reference to activity (if known) |
| `url` | `string` | URL that failed to harvest |
| `errorType` | `string` | Type of error (http, parse, timeout) |
| `errorMessage` | `string` | Error message |
| `failedAt` | `DateTimeImmutable` | When failure occurred |

---

### UserProfile

User preferences and settings.

**Attributes:**

| Name | Type | Description |
|------|------|-------------|
| `id` | `UserProfileId` | Unique identifier (UUID) |
| `userId` | `UserId` | Reference to user (from the auth provider) |
| `preferredLanguage` | `Language` | Default language (es, en, ca, val, eu, gl) |
| `emailNotificationsEnabled` | `bool` | Whether to send email notifications |
| `createdAt` | `DateTimeImmutable` | When profile was created |
| `updatedAt` | `DateTimeImmutable` | When profile was last updated |

---

### SavedSearch

A named filter configuration for notification matching.

**Attributes:**

| Name | Type | Description |
|------|------|-------------|
| `id` | `SavedSearchId` | Unique identifier (UUID) |
| `userId` | `UserId` | Owner of the saved search |
| `name` | `string` | User-defined name |
| `filters` | `ActivityFilter` | Filter criteria |
| `createdAt` | `DateTimeImmutable` | When created |
| `updatedAt` | `DateTimeImmutable` | When last updated |

**Invariants:**
- Users can only access their own saved searches
- `name` must be unique per user

---

### Notification

A message about activities matching a saved search.

**Attributes:**

| Name | Type | Description |
|------|------|-------------|
| `id` | `NotificationId` | Unique identifier (UUID) |
| `userId` | `UserId` | Recipient user |
| `savedSearchId` | `SavedSearchId|null` | Triggering saved search |
| `title` | `string` | Notification title |
| `message` | `string` | Notification message |
| `activityIds` | `array` | Matching activity IDs |
| `readAt` | `DateTimeImmutable|null` | When marked as read |
| `deliveredAt` | `DateTimeImmutable|null` | When email was delivered |
| `createdAt` | `DateTimeImmutable` | When created |

---

## Value Objects

### ActivityId

Wraps a UUID string.

```php
final class ActivityId
{
    private function __construct(
        public readonly string $value
    ) {
        Assert::uuid($value);
    }

    public static function generate(): self
    public static function fromString(string $value): self
    public function equals(ActivityId $other): bool
}
```

### UserId

Wraps a UUID string from the auth provider.

### Money

Represents a monetary amount.

```php
final class Money
{
    private function __construct(
        public readonly int $amount,  // in cents
        public readonly string $currency  // ISO 4217
    ) {}

    public static function fromDecimal(float $amount, string $currency): self
    public function toDecimal(): float
    public function format(string $locale): string
}
```

### ActivityFilter

Criteria for filtering activities.

```php
final class ActivityFilter
{
    private function __construct(
        public readonly ?string $query,
        public readonly ?DateRange $dateRange,
        public readonly ?Modality $modality,
        public readonly ?MoneyRange $priceRange,
        public readonly ?array $centers,
        public readonly ?array $areas,
        public readonly ?array $typologies,
    ) {}
}
```

### DateRange

Represents a date interval.

```php
final class DateRange
{
    private function __construct(
        public readonly DateTimeImmutable $start,
        public readonly DateTimeImmutable $end
    ) {
        Assert::true($start <= $end);
    }
}
```

---

## Enums

### Modality

Course delivery method.

```php
enum Modality: string
{
    case ONLINE = 'online';
    case IN_PERSON = 'in-person';
    case HYBRID = 'hybrid';
}
```

### ActivityStatus

Current state of an activity.

```php
enum ActivityStatus: string
{
    case ACTIVE = 'active';
    case ARCHIVED = 'archived';
}
```

### HarvestStatus

State of a harvest run.

```php
enum HarvestStatus: string
{
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
```

### ChangeType

Type of change detected between snapshots.

```php
enum ChangeType: string
{
    case CREATED = 'created';
    case UPDATED = 'updated';
    case PRICE_CHANGED = 'price-changed';
    case ARCHIVED = 'archived';
}
```

### Language

Supported languages for localization.

```php
enum Language: string
{
    case SPANISH = 'es';
    case ENGLISH = 'en';
    case CATALAN = 'ca';
    case VALENCIAN = 'val';
    case BASQUE = 'eu';
    case GALICIAN = 'gl';
}
```

---

## Aggregates

### Catalog Aggregate

**Root:** Activity
**Entities:** Activity, ActivitySnapshot, PriceSnapshot
**Value Objects:** ActivityId, Money, DateRange, Modality

**Invariant:** All snapshots and price snapshots must belong to the same activity.

---

### Harvest Aggregate

**Root:** HarvestRun
**Entities:** HarvestRun, HarvestFailure
**Value Objects:** HarvestRunId, HarvestFailureId

**Invariant:** All failures belong to a single harvest run.

---

### UserPreferences Aggregate

**Root:** UserProfile
**Entities:** UserProfile, SavedSearch
**Value Objects:** UserProfileId, SavedSearchId, ActivityFilter, Language

**Invariant:** All saved searches belong to the same user.

---

## Domain Rules

### Activity Discovery

1. Activities are discovered by parsing UNED index pages
2. Each activity has a unique `unedId` and `url`
3. Discovering an existing activity is idempotent

### Activity Refresh

1. Refreshing an activity updates all mutable fields
2. Content hash is computed from relevant fields
3. Hash change triggers snapshot creation
4. Price change triggers price snapshot creation

### Change Detection

1. A change is detected when hash differs from previous snapshot
2. Change type is determined by comparing fields:
   - `CREATED`: First snapshot
   - `UPDATED`: Any field changed (except price)
   - `PRICE_CHANGED`: Price changed
   - `ARCHIVED`: Activity no longer found

### Notification Matching

1. Activities match a saved search if they satisfy all filters
2. Only new or changed activities since last digest trigger notifications
3. Notifications are deduplicated by activity ID per digest cycle

### Access Control

1. Users can only access their own profiles and saved searches
2. Users can only access their own notifications
3. Public endpoints require web session token

---

## State Transitions

### Activity Lifecycle

```
[Discovered] → [Active] → [Archived]
     ↓            ↓           ↓
   (create)    (update)    (not found in harvest)
```

### Harvest Lifecycle

```
[Running] → [Completed] → (can run again)
     ↓
[Failed] → (can retry)
```

### Notification Lifecycle

```
[Created] → [Delivered] → [Read]
    ↓
(can be marked read at any time)
```
