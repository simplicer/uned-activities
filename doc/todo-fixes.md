# TODO Fixes (Backlog)

## Harvest: URL Changes for Existing `uned_id`

### Problem
If UNED changes the activity URL but keeps the same `uned_id`, the current harvesting flow can fail to update the existing row.

### Current Behavior (What Happens Today)
- Discovery uses URL idempotency:
  - It deduplicates by URL in-memory (`$seenUrls`).
  - It treats an activity as existing if `existsByUrl($url)` is true.
  - For a new URL (even with an existing `uned_id`), `existsByUrl($url)` returns false and the code creates a new `Activity` with a new `ActivityId`.
  - It then calls `$repository->save($activity)`.
  - File: `src/CatalogHarvest/Application/DiscoverActivities/DiscoverActivities.php`

- Persistence `save()` branches on `existsByUnedId($unedId)`:
  - If `uned_id` exists, it calls `update($activity)`.
  - `update()` uses `WHERE id = :id` with the in-memory activity's id (newly generated).
  - That `id` does not match the existing row id, so the UPDATE affects 0 rows.
  - No new row is inserted (because `save()` chose update), so the URL is not updated either.
  - Files: `src/CatalogHarvest/Infrastructure/Persistence/PdoActivityRepository.php`

### Why It Matters
- Activity URLs can drift over time (UNED redesigns or URL normalization).
- The DB will keep pointing at stale URLs, making refresh/snapshots potentially fail or miss updates.

### Constraints / Safety
- DB enforces `UNIQUE(uned_id)` (migration `infra/migrations/001_init.up.sql`), so duplicates by `uned_id` are prevented at the DB level.
- But the current update logic can still silently not update anything.

### Proposed Fix (Implementation Options)
Pick one:
1. Update by `uned_id`:
   - Change `update()` to `WHERE uned_id = :uned_id` and include updating `url = :url`.
   - Pros: simple and correct for this case.
   - Cons: assumes `uned_id` is the true stable key (it is unique already).
2. Load existing by `uned_id` then update by `id`:
   - In `save()`, if `existsByUnedId()` true, fetch the existing entity (or just its id) and update using that id.
   - Pros: keeps id-based updates.
   - Cons: extra query.
3. Use `INSERT ... ON CONFLICT (uned_id) DO UPDATE`:
   - Atomic upsert; avoids race conditions across multiple harvester instances.
   - Pros: most robust.
   - Cons: larger SQL change; keep column list in sync.

### Acceptance Criteria
- If DB already contains a row with `uned_id = X` and discovery finds the same `uned_id` at a different URL:
  - The `activities.url` value is updated to the new URL.
  - No new `activities` row is created.
- Add a focused test (unit/integration) for this scenario.

