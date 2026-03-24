<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../src/config/env.php';
require_once __DIR__ . '/../../src/config/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$studentId = isset($_GET['student_id']) ? trim((string) $_GET['student_id']) : '';
if ($studentId === '' || !ctype_digit($studentId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid student_id']);
    exit;
}

function tableExists(PDO $db, string $tableName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = ?
    ");
    $stmt->execute([$tableName]);
    return (int) $stmt->fetchColumn() > 0;
}

function columnExists(PDO $db, string $tableName, string $columnName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
    ");
    $stmt->execute([$tableName, $columnName]);
    return (int) $stmt->fetchColumn() > 0;
}

try {
    $db = getDB();
    $studentIdentifier = (int) $studentId;

    if (!tableExists($db, 'students')) {
        echo json_encode([
            'attendance_percent' => 0,
            'avg_score' => 0,
            'avg_score_change' => 0,
            'fee_status' => 'paid',
            'outstanding_amount' => 0,
            'upcoming_classes' => 0,
        ]);
        exit;
    }

    $studentFields = ['id'];
    if (columnExists($db, 'students', 'user_id')) {
        $studentFields[] = 'user_id';
    }
    if (columnExists($db, 'students', 'class_id')) {
        $studentFields[] = 'class_id';
    }

    $studentSql = 'SELECT ' . implode(', ', $studentFields) . ' FROM students WHERE id = ?';
    if (in_array('user_id', $studentFields, true)) {
        $studentSql .= ' OR user_id = ?';
    }
    $studentSql .= ' LIMIT 1';

    $studentStmt = $db->prepare($studentSql);
    $studentParams = [$studentIdentifier];
    if (in_array('user_id', $studentFields, true)) {
        $studentParams[] = $studentIdentifier;
    }
    $studentStmt->execute($studentParams);
    $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode([
            'attendance_percent' => 0,
            'avg_score' => 0,
            'avg_score_change' => 0,
            'fee_status' => 'paid',
            'outstanding_amount' => 0,
            'upcoming_classes' => 0,
        ]);
        exit;
    }

    $currentTermId = null;
    if (tableExists($db, 'terms') && columnExists($db, 'terms', 'is_current')) {
        $termStmt = $db->prepare('SELECT id FROM terms WHERE is_current = 1 ORDER BY id DESC LIMIT 1');
        $termStmt->execute();
        $currentTermId = $termStmt->fetchColumn() ?: null;
    }

    $lastTermId = null;
    if ($currentTermId && tableExists($db, 'terms')) {
        $lastTermStmt = $db->prepare('SELECT id FROM terms WHERE id < ? ORDER BY id DESC LIMIT 1');
        $lastTermStmt->execute([$currentTermId]);
        $lastTermId = $lastTermStmt->fetchColumn() ?: null;
    }

    $attendancePercent = 0;
    if ($currentTermId && tableExists($db, 'attendance')) {
        $attendanceStmt = $db->prepare("
            SELECT COUNT(*) AS total,
                   SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present
            FROM attendance
            WHERE student_id = ? AND term_id = ?
        ");
        $attendanceStmt->execute([(int) $student['id'], $currentTermId]);
        $attendance = $attendanceStmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'present' => 0];
        $totalAttendance = (int) ($attendance['total'] ?? 0);
        $presentAttendance = (int) ($attendance['present'] ?? 0);
        $attendancePercent = $totalAttendance > 0 ? (int) round(($presentAttendance / $totalAttendance) * 100) : 0;
    }

    $avgScore = 0;
    $lastAvgScore = 0;
    if ($currentTermId && tableExists($db, 'exam_results')) {
        $avgScoreStmt = $db->prepare('SELECT AVG(marks) AS avg_score FROM exam_results WHERE student_id = ? AND term_id = ?');
        $avgScoreStmt->execute([(int) $student['id'], $currentTermId]);
        $avgScore = (int) round((float) ($avgScoreStmt->fetchColumn() ?: 0));

        if ($lastTermId) {
            $lastAvgStmt = $db->prepare('SELECT AVG(marks) AS avg_score FROM exam_results WHERE student_id = ? AND term_id = ?');
            $lastAvgStmt->execute([(int) $student['id'], $lastTermId]);
            $lastAvgScore = (int) round((float) ($lastAvgStmt->fetchColumn() ?: 0));
        }
    }

    $feeStatus = 'paid';
    $outstandingAmount = 0;
    if (tableExists($db, 'fee_payments') && columnExists($db, 'fee_payments', 'month')) {
        $currentMonth = date('Y-m');
        $feeStmt = $db->prepare("
            SELECT status, amount
            FROM fee_payments
            WHERE student_id = ? AND month = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $feeStmt->execute([(int) $student['id'], $currentMonth]);
        $fee = $feeStmt->fetch(PDO::FETCH_ASSOC);

        if ($fee) {
            $feeStatus = strtolower((string) ($fee['status'] ?? '')) === 'paid' ? 'paid' : 'overdue';
            $outstandingAmount = $feeStatus === 'overdue' ? (float) ($fee['amount'] ?? 0) : 0;
        }
    }

    $upcomingClasses = 0;
    if (
        tableExists($db, 'timetable') &&
        !empty($student['class_id']) &&
        columnExists($db, 'timetable', 'class_id') &&
        columnExists($db, 'timetable', 'class_date') &&
        columnExists($db, 'timetable', 'status')
    ) {
        $upcomingStmt = $db->prepare("
            SELECT COUNT(*)
            FROM timetable
            WHERE class_id = ?
              AND class_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
              AND status = 'active'
        ");
        $upcomingStmt->execute([(int) $student['class_id']]);
        $upcomingClasses = (int) ($upcomingStmt->fetchColumn() ?: 0);
    }

    echo json_encode([
        'attendance_percent' => $attendancePercent,
        'avg_score' => $avgScore,
        'avg_score_change' => $avgScore - $lastAvgScore,
        'fee_status' => $feeStatus,
        'outstanding_amount' => $outstandingAmount,
        'upcoming_classes' => $upcomingClasses,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
