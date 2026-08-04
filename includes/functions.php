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
