<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config/env.php';
require_once __DIR__ . '/../src/config/db.php';

$defaultPassword = 'Tutor123!';
$passwordHash = password_hash($defaultPassword, PASSWORD_BCRYPT);

$subjectPlan = [
    'Tamil',
    'Maths',
    'Maths',
    'Science',
    'Science',
    'Religion',
    'English',
    'Civics',
    'History',
    'Geography',
    'ICT',
    'Health Science',
    'Sinhala',
    'Commerce',
    'Tamil',
];

$maleNames = [
    'Kajan', 'Niroshan', 'Sutharsan', 'Mathivanan', 'Tharsan',
    'Ketheeswaran', 'Niranjan', 'Yathushan', 'Sivakumar', 'Logeshwaran',
    'Pradeepan', 'Jegan', 'Diluxshan', 'Kishok', 'Ramesh'
];

$femaleNames = [
    'Tharmini', 'Yasodha', 'Nivetha', 'Harini', 'Krishanthini',
    'Janani', 'Abinaya', 'Kavitha', 'Yalini', 'Subhashini',
    'Sathya', 'Nirasha', 'Madhumitha', 'Sanjana', 'Thevaki'
];

$initials = ['S.', 'K.', 'P.', 'R.', 'V.', 'T.', 'M.', 'J.', 'N.', 'A.', 'D.', 'L.', 'G.'];

$addresses = [
    'Nallur, Jaffna',
    'Kokuvil, Jaffna',
    'Chunnakam, Jaffna',
    'Chavakachcheri, Jaffna',
    'Point Pedro, Jaffna',
    'Kopay, Jaffna',
    'Manipay, Jaffna',
    'Vaddukoddai, Jaffna',
    'Uduvil, Jaffna',
    'Tellippalai, Jaffna'
];

function randomItem(array $items)
{
    return $items[array_rand($items)];
}

function generatePhone(array &$usedPhones): string
{
    do {
        $phone = '07' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
    } while (isset($usedPhones[$phone]));

    $usedPhones[$phone] = true;

    return $phone;
}

function generateNic(): string
{
    if (random_int(0, 1) === 0) {
        return str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT) . randomItem(['V', 'X']);
    }

    return (string) random_int(198000000000, 200199999999);
}

function generateDob(): string
{
    $year = random_int(1981, 2001);
    $month = str_pad((string) random_int(1, 12), 2, '0', STR_PAD_LEFT);
    $day = str_pad((string) random_int(1, 28), 2, '0', STR_PAD_LEFT);
    return "{$year}-{$month}-{$day}";
}

function buildTutorName(string $gender, array $initials, array $maleNames, array $femaleNames): string
{
    $name = $gender === 'Male' ? randomItem($maleNames) : randomItem($femaleNames);
    return randomItem($initials) . ' ' . $name;
}

function buildEmail(string $fullName, string $subject, int $index): string
{
    $namePart = strtolower(str_replace(['.', ' '], '', $fullName));
    $subjectPart = strtolower(str_replace([' ', '&'], ['', 'and'], $subject));
    return sprintf('%s.%s.%02d@gmail.com', $namePart, $subjectPart, $index);
}

try {
    $db = getDB();

    $roleStmt = $db->prepare("SELECT id FROM roles WHERE name = 'TUTOR' LIMIT 1");
    $roleStmt->execute();
    $tutorRoleId = $roleStmt->fetchColumn();

    if (!$tutorRoleId) {
        throw new RuntimeException('TUTOR role not found in roles table.');
    }

    $checkUserStmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $insertUserStmt = $db->prepare(
        "INSERT INTO users (full_name, email, password_hash, role_id, phone, dob, gender, address, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $insertTutorStmt = $db->prepare(
        "INSERT INTO tutors (user_id, nic_number, subject)
         VALUES (?, ?, ?)"
    );

    $usedPhones = [];
    $insertedCount = 0;
    $skippedCount = 0;

    $db->beginTransaction();

    foreach ($subjectPlan as $index => $subject) {
        $gender = random_int(0, 1) === 0 ? 'Male' : 'Female';
        $fullName = buildTutorName($gender, $initials, $maleNames, $femaleNames);
        $email = buildEmail($fullName, $subject, $index + 1);

        $checkUserStmt->execute([$email]);
        if ($checkUserStmt->fetchColumn()) {
            $skippedCount++;
            continue;
        }

        $phone = generatePhone($usedPhones);
        $dob = generateDob();
        $address = randomItem($addresses);
        $nic = generateNic();

        $insertUserStmt->execute([
            $fullName,
            $email,
            $passwordHash,
            $tutorRoleId,
            $phone,
            $dob,
            $gender,
            $address,
            'ACTIVE',
        ]);

        $userId = (int) $db->lastInsertId();

        $insertTutorStmt->execute([
            $userId,
            $nic,
            $subject,
        ]);

        $insertedCount++;
    }

    $db->commit();

    echo "Tutor seeding completed successfully." . PHP_EOL;
    echo "Inserted: {$insertedCount}" . PHP_EOL;
    echo "Skipped (existing email): {$skippedCount}" . PHP_EOL;
    echo "Subjects seeded: " . count($subjectPlan) . PHP_EOL;
    echo "Default password for seeded tutors: {$defaultPassword}" . PHP_EOL;
    echo "Status used: ACTIVE (schema equivalent of approved)." . PHP_EOL;
    echo "Distribution note: the requested subject list totals 14, so one additional Tamil tutor was inserted to reach 15 records." . PHP_EOL;
    echo "Schema note: qualification, experience, hourly rate, and joining date were not inserted because those columns do not exist in the current tutors table." . PHP_EOL;
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    fwrite(STDERR, "Seeding failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
