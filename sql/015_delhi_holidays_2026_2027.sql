-- Seed data: Government of NCT of Delhi / Government of India commonly
-- observed holidays, from today (2026-08-04) through the end of 2027.
--
-- This file has no CREATE TABLE, so sql/index.php's schema runner will
-- NOT auto-run it — click "Re-run" for it once on /sql/index.php after
-- deploying, same as 011_... and 014_....
--
-- Idempotent: every INSERT is guarded by "WHERE NOT EXISTS (... same
-- date ...)", so re-running this file never creates duplicates and never
-- overwrites a holiday you've since edited or replaced via
-- admin/holidays/index.php for that date.
--
-- IMPORTANT — read before running:
--   * Fixed-date holidays (Republic Day, Independence Day, Gandhi
--     Jayanti, Christmas, Labour Day, New Year's Day) are certain — they
--     fall on the same calendar date every year.
--   * The 2026 festival dates below (Raksha Bandhan, Janmashtami,
--     Dussehra, Diwali, Guru Nanak Jayanti) follow the lunar Hindu
--     calendar and are marked ESTIMATE — cross-check them against the
--     official Delhi Government gazetted holiday list and correct via
--     admin/holidays/index.php (delete + re-add) if they're off by a day.
--   * 2027's lunar-calendar festivals (Holi, Diwali, Eid, etc.) are
--     DELIBERATELY NOT included here — the official 2027 holiday
--     calendar hasn't been published yet as of 2026-08, and guessing
--     those dates a year and a half out would be unreliable for a live
--     attendance/payroll system. Add them via admin/holidays/index.php
--     once the government notification is out (usually published a few
--     months ahead of the year).
--   * Sundays are handled separately by the "Generate Sundays" bulk
--     action on admin/holidays/index.php — not part of this file.

-- ===== Remainder of 2026 =====

INSERT INTO holidays (holiday_date, name, applies_to)
SELECT '2026-08-15', 'Independence Day', 'all' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM holidays WHERE holiday_date = '2026-08-15');

INSERT INTO holidays (holiday_date, name, applies_to)
SELECT '2026-08-28', 'Raksha Bandhan (estimate — verify)', 'all' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM holidays WHERE holiday_date = '2026-08-28');

INSERT INTO holidays (holiday_date, name, applies_to)
SELECT '2026-09-04', 'Janmashtami (estimate — verify)', 'all' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM holidays WHERE holiday_date = '2026-09-04');

INSERT INTO holidays (holiday_date, name, applies_to)
SELECT '2026-10-02', 'Gandhi Jayanti', 'all' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM holidays WHERE holiday_date = '2026-10-02');

INSERT INTO holidays (holiday_date, name, applies_to)
SELECT '2026-10-20', 'Dussehra (estimate — verify)', 'all' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM holidays WHERE holiday_date = '2026-10-20');

INSERT INTO holidays (holiday_date, name, applies_to)
SELECT '2026-11-08', 'Diwali (estimate — verify)', 'all' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM holidays WHERE holiday_date = '2026-11-08');

INSERT INTO holidays (holiday_date, name, applies_to)
SELECT '2026-11-24', 'Guru Nanak Jayanti (estimate — verify)', 'all' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM holidays WHERE holiday_date = '2026-11-24');

INSERT INTO holidays (holiday_date, name, applies_to)
SELECT '2026-12-25', 'Christmas', 'all' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM holidays WHERE holiday_date = '2026-12-25');

-- ===== 2027 — fixed-date holidays only (see note above) =====

INSERT INTO holidays (holiday_date, name, applies_to)
SELECT '2027-01-01', 'New Year''s Day', 'all' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM holidays WHERE holiday_date = '2027-01-01');

INSERT INTO holidays (holiday_date, name, applies_to)
SELECT '2027-01-26', 'Republic Day', 'all' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM holidays WHERE holiday_date = '2027-01-26');

INSERT INTO holidays (holiday_date, name, applies_to)
SELECT '2027-05-01', 'Labour Day', 'all' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM holidays WHERE holiday_date = '2027-05-01');

INSERT INTO holidays (holiday_date, name, applies_to)
SELECT '2027-08-15', 'Independence Day', 'all' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM holidays WHERE holiday_date = '2027-08-15');

INSERT INTO holidays (holiday_date, name, applies_to)
SELECT '2027-10-02', 'Gandhi Jayanti', 'all' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM holidays WHERE holiday_date = '2027-10-02');

INSERT INTO holidays (holiday_date, name, applies_to)
SELECT '2027-12-25', 'Christmas', 'all' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM holidays WHERE holiday_date = '2027-12-25');
