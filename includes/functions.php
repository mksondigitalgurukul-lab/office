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
