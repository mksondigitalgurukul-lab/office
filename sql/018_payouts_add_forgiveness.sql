-- Lets an admin waive/forgive a draft payout's unpaid_deduction (e.g. an
-- exceptional circumstance) without touching attendance/leave data. See
-- CLAUDE.md "Payout" for the exact effect on net_payout.
--
-- forgiven_by is a soft reference to admins.id (no FOREIGN KEY) — same
-- pattern as wfh_requests.created_by_id, kept simple/portable rather
-- than an "ADD CONSTRAINT ... FOREIGN KEY IF NOT EXISTS" clause, which
-- isn't supported identically across MySQL vs MariaDB.
--
-- No CREATE TABLE, so it does NOT auto-run — click "Re-run" for it once
-- on /sql/index.php, same as 011/014/016/017.
ALTER TABLE payouts
  ADD COLUMN IF NOT EXISTS forgiven_amount DECIMAL(12,2) NULL AFTER unpaid_deduction,
  ADD COLUMN IF NOT EXISTS forgiven_by INT UNSIGNED NULL AFTER forgiven_amount,
  ADD COLUMN IF NOT EXISTS forgiven_at TIMESTAMP NULL AFTER forgiven_by;
