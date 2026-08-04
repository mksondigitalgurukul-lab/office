-- Adds a reason to every salary change — always required going forward
-- (admin/staff/set-salary.php: pick a preset from a dropdown, or "Other"
-- to type a short free-text reason). Existing rows before this migration
-- default to '' (shown as "—" in the UI) since we don't know why they
-- were made.
--
-- No CREATE TABLE, so it does NOT auto-run — click "Re-run" for it once
-- on /sql/index.php, same as 011/014/016.
ALTER TABLE staff_salary
  ADD COLUMN IF NOT EXISTS reason VARCHAR(255) NOT NULL DEFAULT '' AFTER monthly_salary;
