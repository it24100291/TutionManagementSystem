<?php

declare(strict_types=1);

function tutorMockOverview(int $tutorId): array
{
    return [
        'classes_today' => 3 + ($tutorId % 2),
        'total_students' => 64 + ($tutorId % 7),
        'hours_this_month' => 42 + ($tutorId % 6),
        'target_hours' => 60,
        'salary_status' => $tutorId % 2 === 0 ? 'paid' : 'pending',
    ];
}

function tutorMockTodaySchedule(): array
{
    return [
        [
            'id' => 101,
            'class_name' => 'Mathematics',
            'grade' => 'Grade 10',
            'start_time' => '15:00',
            'duration_hours' => 2,
            'room' => 'Hall 1',
            'student_count' => 28,
            'status' => 'upcoming',
        ],
        [
            'id' => 102,
            'class_name' => 'Science',
            'grade' => 'Grade 9',
            'start_time' => '17:30',
            'duration_hours' => 1,
            'room' => 'Hall 2',
            'student_count' => 24,
            'status' => 'confirmed',
        ],
        [
            'id' => 103,
            'class_name' => 'ICT',
            'grade' => 'Grade 11',
            'start_time' => '19:00',
            'duration_hours' => 1,
            'room' => 'Hall 4',
            'student_count' => 19,
            'status' => 'rescheduled',
        ],
    ];
}

function tutorMockNextClass(): array
{
    return [
        'id' => 101,
        'class_id' => 201,
        'name' => 'Mathematics',
        'grade' => 'Grade 10',
        'start_time' => '15:00',
        'attendance_submitted' => 0,
    ];
}

function tutorMockClassStudents(): array
{
    return [
        ['id' => 1, 'name' => 'S. Tharshan', 'attendance_status' => null],
        ['id' => 2, 'name' => 'K. Nivetha', 'attendance_status' => null],
        ['id' => 3, 'name' => 'P. Harini', 'attendance_status' => null],
        ['id' => 4, 'name' => 'R. Mathushan', 'attendance_status' => null],
        ['id' => 5, 'name' => 'V. Kavishan', 'attendance_status' => null],
        ['id' => 6, 'name' => 'T. Janani', 'attendance_status' => null],
    ];
}

function tutorMockStudents(): array
{
    return [
        ['id' => 1, 'name' => 'S. Tharshan', 'grade' => 'Grade 10', 'class_name' => 'Mathematics', 'attendance_percent' => 92],
        ['id' => 2, 'name' => 'K. Nivetha', 'grade' => 'Grade 10', 'class_name' => 'Mathematics', 'attendance_percent' => 88],
        ['id' => 3, 'name' => 'P. Harini', 'grade' => 'Grade 9', 'class_name' => 'Science', 'attendance_percent' => 81],
        ['id' => 4, 'name' => 'R. Mathushan', 'grade' => 'Grade 9', 'class_name' => 'Science', 'attendance_percent' => 74],
        ['id' => 5, 'name' => 'V. Kavishan', 'grade' => 'Grade 11', 'class_name' => 'ICT', 'attendance_percent' => 69],
        ['id' => 6, 'name' => 'T. Janani', 'grade' => 'Grade 11', 'class_name' => 'ICT', 'attendance_percent' => 95],
    ];
}

function tutorMockClassPerformance(): array
{
    return [
        ['class_name' => 'Mathematics', 'grade' => 'Grade 10', 'avg_score' => 84, 'student_count' => 28],
        ['class_name' => 'Science', 'grade' => 'Grade 9', 'avg_score' => 76, 'student_count' => 24],
        ['class_name' => 'ICT', 'grade' => 'Grade 11', 'avg_score' => 63, 'student_count' => 19],
    ];
}

function tutorMockSalarySummary(): array
{
    return [
        'hours_this_month' => 46,
        'rate_per_hour' => 1500,
        'base_salary' => 69000,
        'current_status' => 'pending',
        'history' => [
            ['month' => 'March 2026', 'amount' => 69000, 'status' => 'pending'],
            ['month' => 'February 2026', 'amount' => 64500, 'status' => 'paid'],
            ['month' => 'January 2026', 'amount' => 61200, 'status' => 'paid'],
        ],
    ];
}

function tutorMockLeaveRequests(): array
{
    return [
        [
            'id' => 1,
            'student_id' => 2,
            'student_name' => 'K. Nivetha',
            'class_name' => 'Mathematics · Grade 10',
            'absence_date' => date('Y-m-d', strtotime('+1 day')),
            'reason' => 'Medical appointment in Jaffna Teaching Hospital.',
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
        ],
        [
            'id' => 2,
            'student_id' => 4,
            'student_name' => 'R. Mathushan',
            'class_name' => 'Science · Grade 9',
            'absence_date' => date('Y-m-d', strtotime('+2 days')),
            'reason' => 'Family function in Chavakachcheri.',
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s', strtotime('-5 hours')),
        ],
        [
            'id' => 3,
            'student_id' => 5,
            'student_name' => 'V. Kavishan',
            'class_name' => 'ICT · Grade 11',
            'absence_date' => date('Y-m-d', strtotime('-2 days')),
            'reason' => 'Attended school sports meet.',
            'status' => 'approved',
            'created_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
        ],
        [
            'id' => 4,
            'student_id' => 6,
            'student_name' => 'T. Janani',
            'class_name' => 'ICT · Grade 11',
            'absence_date' => date('Y-m-d', strtotime('-4 days')),
            'reason' => 'Travelled to Point Pedro for a family emergency.',
            'status' => 'denied',
            'created_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
        ],
    ];
}
