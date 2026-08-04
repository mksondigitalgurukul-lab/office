-- Add is_active for soft-deactivating leave types — existing
-- leave_requests may reference a type, so it's never hard-deleted.
--
-- This file has no CREATE TABLE, so sql/index.php's schema runner will
-- NOT auto-run it. Click "Re-run" for it once on /sql/index.php after
-- deploying — safe to click more than once (MariaDB's ADD COLUMN IF NOT
-- EXISTS is idempotent).
ALTER TABLE leave_types
  ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER is_paid;
