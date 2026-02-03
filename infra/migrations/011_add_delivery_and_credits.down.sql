-- Migration 011: Rollback delivery mode and credits fields
-- Version: 20250202000001

ALTER TABLE activities DROP COLUMN IF EXISTS credits;
ALTER TABLE activities DROP COLUMN IF EXISTS has_live;
ALTER TABLE activities DROP COLUMN IF EXISTS has_recorded;
