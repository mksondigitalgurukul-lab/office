<?php
/**
 * Small shared helpers used across the admin panel.
 */

require_once __DIR__ . '/db.php';

/** Escape a string for safe HTML output. */
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Read a single value from the settings table. */
function getSetting(string $key, ?string $default = null): ?string
{
    $stmt = getDB()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();

    return $value === false ? $default : $value;
}

/** Create or update a value in the settings table. */
function setSetting(string $key, string $value): void
{
    $stmt = getDB()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

/**
 * Resolve the work timing that applies to a staff member on a given date
 * (defaults to today): the latest staff_work_time_history row with
 * effective_from <= $onDate, or the universal settings default if that
 * row has NULL times (an explicit "use default") or no row exists yet.
 */
function getCurrentWorkTiming(int $staffId, ?string $onDate = null): array
{
    $onDate = $onDate ?? date('Y-m-d');

    $stmt = getDB()->prepare(
        'SELECT work_start_time, work_end_time, effective_from
         FROM staff_work_time_history
         WHERE staff_id = ? AND effective_from <= ?
         ORDER BY effective_from DESC, id DESC
         LIMIT 1'
    );
    $stmt->execute([$staffId, $onDate]);
    $row = $stmt->fetch();

    if ($row && $row['work_start_time'] !== null && $row['work_end_time'] !== null) {
        return [
            'start'          => $row['work_start_time'],
            'end'            => $row['work_end_time'],
            'source'         => 'override',
            'effective_from' => $row['effective_from'],
        ];
    }

    return [
        'start'          => getSetting('default_work_start_time'),
        'end'            => getSetting('default_work_end_time'),
        'source'         => 'default',
        'effective_from' => $row['effective_from'] ?? null,
    ];
}

/** The requesting client's IP address, used for office-WiFi verification. */
function getClientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

/** Whether an IP matches one of the active office_locations rows. */
function isOfficeIp(string $ip): bool
{
    if ($ip === '') {
        return false;
    }

    $stmt = getDB()->prepare('SELECT COUNT(*) FROM office_locations WHERE is_active = 1 AND ip_address = ?');
    $stmt->execute([$ip]);
    return (int) $stmt->fetchColumn() > 0;
}

/** Grace period (minutes) before a check-in counts as late. */
function getAttendanceGraceMinutes(): int
{
    return (int) getSetting('attendance_grace_minutes', '15');
}

/**
 * The holiday name for a date, or null if it isn't a holiday.
 * applies_to = 'specific' is treated the same as 'all' for now — targeting
 * specific staff via holiday_staff is deferred to Prompt 4, so any holiday
 * row currently blocks attendance company-wide on that date.
 */
function getHolidayName(string $date): ?string
{
    $stmt = getDB()->prepare('SELECT name FROM holidays WHERE holiday_date = ? LIMIT 1');
    $stmt->execute([$date]);
    $name = $stmt->fetchColumn();
    return $name !== false ? $name : null;
}

/** 'present' if $checkInTime is within $graceMinutes of $scheduledStart, else 'late'. */
function computeCheckInStatus(string $scheduledStart, string $checkInTime, int $graceMinutes): string
{
    $deadline = date('H:i:s', strtotime($scheduledStart) + $graceMinutes * 60);
    return $checkInTime <= $deadline ? 'present' : 'late';
}

/** Whether the worked duration is under half the scheduled shift length. */
function isHalfDay(string $scheduledStart, string $scheduledEnd, string $checkInTime, string $checkOutTime): bool
{
    $scheduledSeconds = strtotime($scheduledEnd) - strtotime($scheduledStart);
    if ($scheduledSeconds <= 0) {
        return false;
    }

    $workedSeconds = strtotime($checkOutTime) - strtotime($checkInTime);
    return $workedSeconds < ($scheduledSeconds / 2);
}

/** Whether a staff member has an approved WFH request for a specific date. */
function hasApprovedWfh(int $staffId, string $date): bool
{
    $stmt = getDB()->prepare(
        "SELECT COUNT(*) FROM wfh_requests WHERE staff_id = ? AND wfh_date = ? AND status = 'approved'"
    );
    $stmt->execute([$staffId, $date]);
    return (int) $stmt->fetchColumn() > 0;
}

/** Whether a staff member has an approved leave request covering a specific date. */
function hasApprovedLeave(int $staffId, string $date): bool
{
    $stmt = getDB()->prepare(
        "SELECT COUNT(*) FROM leave_requests WHERE staff_id = ? AND status = 'approved' AND from_date <= ? AND to_date >= ?"
    );
    $stmt->execute([$staffId, $date, $date]);
    return (int) $stmt->fetchColumn() > 0;
}
