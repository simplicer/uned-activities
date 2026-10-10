-- Track when an activity's enrollment was observed closed, so activities
-- whose enrollment has been closed for more than 3 months can be removed
-- from the catalog automatically (see closePastActivities in the harvest).

ALTER TABLE activities ADD COLUMN IF NOT EXISTS enrollment_closed_at TIMESTAMPTZ NULL;

-- Backfill: date the closure of already-closed enrollments with the best
-- available proxy (enrollment end date, then activity end date, then now).
UPDATE activities
SET enrollment_closed_at = COALESCE(enrollment_end_date, end_date, NOW())
WHERE enrollment_open = false AND enrollment_closed_at IS NULL;
