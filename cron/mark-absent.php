<?php
/**
 * Daily absent-marker.
 *
 * For YESTERDAY's date, inserts an 'absent' attendance row for every
 * active staff member who has no attendance row for that date — unless
 * yesterday was a holiday, in which case nothing is marked at all.
 *
 * Staff with an approved leave request covering that date instead get an
 * 'on_leave' row (a distinct status, not 'absent'). Staff with an approved
 * WFH request for that date but no check-in are skipped entirely, same as
 * a holiday — no row is created, no absent penalty; see CLAUDE.md
 * "Attendance" for why leave and WFH are handled differently here.
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

$absentStmt = $pdo->prepare(
    "INSERT INTO attendance (staff_id, attendance_date, work_location, status, notes)
     VALUES (?, ?, 'unverified', 'absent', ?)"
);
$onLeaveStmt = $pdo->prepare(
    "INSERT INTO attendance (staff_id, attendance_date, work_location, status, notes)
     VALUES (?, ?, 'unverified', 'on_leave', ?)"
);

$marked   = 0;
$onLeave  = 0;
$wfhSkip  = 0;
foreach ($staffIds as $staffId) {
    if (isset($existingSet[$staffId])) {
        continue;
    }

    try {
        if (hasApprovedLeave($staffId, $targetDate)) {
            $onLeaveStmt->execute([$staffId, $targetDate, 'Auto-marked on_leave by cron/mark-absent.php (approved leave request covers this date)']);
            $onLeave++;
        } elseif (hasApprovedWfh($staffId, $targetDate)) {
            // Approved WFH day with no check-in recorded — skip, same as a
            // holiday: no absent penalty, no fabricated attendance row.
            $wfhSkip++;
        } else {
            $absentStmt->execute([$staffId, $targetDate, 'Auto-marked absent by cron/mark-absent.php (no check-in recorded)']);
            $marked++;
        }
    } catch (PDOException $e) {
        // Unique constraint hit (e.g. a concurrent manual edit) — skip.
    }
}

echo "Marked {$marked} staff absent, {$onLeave} on_leave, skipped {$wfhSkip} approved-WFH-no-checkin, for {$targetDate}.\n";
