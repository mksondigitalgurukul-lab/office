<?php
/**
 * Daily absent-marker.
 *
 * For YESTERDAY's date, inserts an 'absent' attendance row for every
 * active staff member who has no attendance row for that date — unless
 * yesterday was a holiday, in which case nothing is marked.
 *
 * Run once daily, shortly after midnight, via cPanel cron:
 *   php /home/USERNAME/public_html/office/cron/mark-absent.php
 * See README.md for the full cron setup (including the HTTP fallback).
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (PHP_SAPI !== 'cli') {
    $token = $_GET['key'] ?? '';
    if (!hash_equals(CRON_SECRET, $token)) {
        http_response_code(403);
        exit('Forbidden');
    }
    header('Content-Type: text/plain');
}

$pdo        = getDB();
$targetDate = date('Y-m-d', strtotime('-1 day'));

$holidayName = getHolidayName($targetDate);
if ($holidayName !== null) {
    echo "Skipped {$targetDate}: holiday ({$holidayName}).\n";
    exit(0);
}

$staffIds = $pdo->query("SELECT id FROM staff WHERE status = 'active'")->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare('SELECT staff_id FROM attendance WHERE attendance_date = ?');
$stmt->execute([$targetDate]);
$existingSet = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

$insertStmt = $pdo->prepare(
    "INSERT INTO attendance (staff_id, attendance_date, work_location, status, notes)
     VALUES (?, ?, 'unverified', 'absent', ?)"
);

$marked = 0;
foreach ($staffIds as $staffId) {
    if (isset($existingSet[$staffId])) {
        continue;
    }

    try {
        $insertStmt->execute([$staffId, $targetDate, 'Auto-marked absent by cron/mark-absent.php (no check-in recorded)']);
        $marked++;
    } catch (PDOException $e) {
        // Unique constraint hit (e.g. a concurrent manual edit) — skip.
    }
}

echo "Marked {$marked} staff absent for {$targetDate}.\n";
