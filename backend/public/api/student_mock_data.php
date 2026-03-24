<?php

declare(strict_types=1);

function studentMockGrade(int $studentId, ?string $grade = null): string
{
    if ($grade !== null && trim($grade) !== '') {
        $normalized = trim($grade);
        return str_starts_with($normalized, 'Grade ') ? $normalized : 'Grade ' . $normalized;
    }

    $grades = ['Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11'];
    return $grades[$studentId % count($grades)];
}

function studentMockOverview(int $studentId, ?string $grade = null): array
{
    $resolvedGrade = studentMockGrade($studentId, $grade);
    $offset = $studentId % 5;

    return [
        'total_enrolled_classes' => 6 + $offset,
        'attendance_percentage' => 82 + $offset,
        'pending_payments_count' => 1 + ($studentId % 2),
        'upcoming_exams_count' => 2 + ($studentId % 3),
        'todays_classes' => [
            [
                'class_name' => 'Mathematics',
                'grade' => $resolvedGrade,
                'start_time' => '15:00',
                'room' => 'Hall 1',
            ],
            [
                'class_name' => 'Science',
                'grade' => $resolvedGrade,
                'start_time' => '16:00',
                'room' => 'Hall 2',
            ],
        ],
        'announcements' => [
            [
                'title' => 'Monthly Test Schedule',
                'message' => 'Monthly tests will begin next Monday. Please come 15 minutes early.',
                'created_at' => date('Y-m-d', strtotime('-1 day')),
            ],
            [
                'title' => 'Science Practical Session',
                'message' => 'Bring your lab record book for the weekend practical class.',
                'created_at' => date('Y-m-d', strtotime('-3 days')),
            ],
            [
                'title' => 'Fee Reminder',
                'message' => 'Kindly settle any pending monthly payments before the 25th.',
                'created_at' => date('Y-m-d', strtotime('-5 days')),
            ],
        ],
    ];
}

function studentMockAttendance(int $studentId): array
{
    $present = 18 + ($studentId % 4);
    $absent = 2 + ($studentId % 2);
    $total = $present + $absent;

    return [
        'total_classes_held' => $total,
        'total_present' => $present,
        'total_absent' => $absent,
        'attendance_percentage' => (int) round(($present / $total) * 100),
        'history' => [
            ['date' => '2026-03-18', 'subject' => 'Mathematics', 'status' => 'Present'],
            ['date' => '2026-03-17', 'subject' => 'Science', 'status' => 'Present'],
            ['date' => '2026-03-16', 'subject' => 'English', 'status' => 'Absent'],
            ['date' => '2026-03-15', 'subject' => 'History', 'status' => 'Present'],
            ['date' => '2026-03-14', 'subject' => 'ICT', 'status' => 'Present'],
        ],
    ];
}

function studentMockPayments(int $studentId): array
{
    $outstanding = 2500 + (($studentId % 3) * 500);

    return [
        'total_paid_months' => 7,
        'total_unpaid_months' => 2,
        'total_outstanding_amount' => $outstanding,
        'history' => [
            ['month' => 'March 2026', 'amount' => 3500, 'status' => 'Unpaid', 'payment_date' => 'N/A'],
            ['month' => 'February 2026', 'amount' => 3500, 'status' => 'Paid', 'payment_date' => '2026-02-05'],
            ['month' => 'January 2026', 'amount' => 3500, 'status' => 'Paid', 'payment_date' => '2026-01-07'],
            ['month' => 'December 2025', 'amount' => 3000, 'status' => 'Unpaid', 'payment_date' => 'N/A'],
        ],
    ];
}

function studentMockExams(int $studentId): array
{
    $results = [
        ['exam_name' => 'Monthly Test 1', 'subject' => 'Mathematics', 'marks_obtained' => 78, 'total_marks' => 100, 'grade' => 'B+'],
        ['exam_name' => 'Monthly Test 1', 'subject' => 'Science', 'marks_obtained' => 84, 'total_marks' => 100, 'grade' => 'A'],
        ['exam_name' => 'Monthly Test 1', 'subject' => 'English', 'marks_obtained' => 72, 'total_marks' => 100, 'grade' => 'B'],
        ['exam_name' => 'Monthly Test 1', 'subject' => 'History', 'marks_obtained' => 81, 'total_marks' => 100, 'grade' => 'A-'],
    ];

    $sum = 0;
    foreach ($results as $row) {
        $sum += (int) $row['marks_obtained'];
    }

    return [
        'average_mark' => (int) round($sum / count($results)),
        'results' => $results,
    ];
}

function studentMockTimetable(int $studentId, ?string $grade = null): array
{
    $resolvedGrade = studentMockGrade($studentId, $grade);

    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $timeSlots = [
        '08:00 - 09:00',
        '09:00 - 10:00',
        '10:00 - 11:00',
        '14:00 - 15:00',
        '15:00 - 16:00',
        '16:00 - 17:00',
        '17:00 - 18:00',
    ];
    $dayTimeSlots = [
        'Monday' => ['15:00 - 16:00', '16:00 - 17:00', '17:00 - 18:00'],
        'Tuesday' => ['15:00 - 16:00', '16:00 - 17:00', '17:00 - 18:00'],
        'Wednesday' => ['15:00 - 16:00', '16:00 - 17:00', '17:00 - 18:00'],
        'Thursday' => ['15:00 - 16:00', '16:00 - 17:00', '17:00 - 18:00'],
        'Friday' => [],
        'Saturday' => ['08:00 - 09:00', '09:00 - 10:00', '10:00 - 11:00', '14:00 - 15:00', '15:00 - 16:00'],
        'Sunday' => ['08:00 - 09:00', '09:00 - 10:00', '10:00 - 11:00', '14:00 - 15:00'],
    ];

    return [
        'grade' => $resolvedGrade,
        'days' => $days,
        'time_slots' => $timeSlots,
        'day_time_slots' => $dayTimeSlots,
        'entries' => [
            ['day' => 'Monday', 'time' => '15:00 - 16:00', 'subject' => 'Mathematics', 'teacher' => 'Mr. Kajan', 'room' => 'Hall 1'],
            ['day' => 'Tuesday', 'time' => '16:00 - 17:00', 'subject' => 'Science', 'teacher' => 'Ms. Nivetha', 'room' => 'Hall 2'],
            ['day' => 'Wednesday', 'time' => '17:00 - 18:00', 'subject' => 'English', 'teacher' => 'Mrs. Harini', 'room' => 'Hall 3'],
            ['day' => 'Saturday', 'time' => '09:00 - 10:00', 'subject' => 'ICT', 'teacher' => 'Mr. Mathushan', 'room' => 'Hall 4'],
            ['day' => 'Sunday', 'time' => '14:00 - 15:00', 'subject' => 'History', 'teacher' => 'Mr. Tharshan', 'room' => 'Hall 5'],
        ],
    ];
}
