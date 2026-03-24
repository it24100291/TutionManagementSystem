<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config/env.php';
require_once __DIR__ . '/../src/config/db.php';

$grades = ['6', '7', '8', '9', '10', '11'];
$studentsPerGrade = 20;
$defaultPassword = 'Student123!';
$passwordHash = password_hash($defaultPassword, PASSWORD_BCRYPT);

$maleNames = [
    'Tharshan', 'Mathushan', 'Kavishan', 'Kajan', 'Ramesh', 'Sutharsan',
    'Niranjan', 'Yathushan', 'Pradeepan', 'Jathushan', 'Akilan', 'Logesh',
    'Mithushan', 'Sasitharan', 'Vignesh', 'Sanjeev', 'Thivyan', 'Gokulan',
    'Janarthan', 'Kishoren', 'Dilaksan', 'Darshan', 'Madhushan', 'Ketheeswaran'
];

$femaleNames = [
    'Nivetha', 'Harini', 'Kavitha', 'Yalini', 'Sathya', 'Kirthika',
    'Tharshika', 'Nirasha', 'Madhumitha', 'Sanjana', 'Abinaya', 'Krishanthini',
    'Yogitha', 'Sharvika', 'Anushiya', 'Thevaki', 'Kawshika', 'Vithusha',
    'Thivya', 'Janani', 'Kayathiri', 'Inoka', 'Subhashini', 'Prashanthi'
];

$initials = ['S.', 'K.', 'P.', 'R.', 'V.', 'T.', 'M.', 'J.', 'N.', 'A.', 'D.', 'L.', 'G.', 'H.', 'Y.'];

$schools = [
    "Jaffna Hindu College",
    "Vembadi Girls' High School",
    "St. John's College",
    "Jaffna Central College",
    "Hartley College",
    "Uduvil Girls' College",
    "Chundikuli Girls' College"
];

$addresses = [
    'Nallur, Jaffna',
    'Chunnakam, Jaffna',
    'Kokuvil, Jaffna',
    'Kilinochchi Road, Jaffna',
    'Point Pedro, Jaffna',
    'Chavakachcheri, Jaffna',
    'Kopay, Jaffna',
    'Vaddukoddai, Jaffna',
    'Karainagar, Jaffna',
    'Manipay, Jaffna'
];

$guardianMaleNames = [
    'Sivakumar', 'Parameswaran', 'Ravindran', 'Naguleswaran', 'Jeyakumar',
    'Tharmalingam', 'Sivanesan', 'Ketheeswaran', 'Kanthasamy', 'Rajeswaran',
    'Balachandran', 'Mahendran', 'Yogarajah', 'Sivapalan', 'Nadarajah'
];

$guardianFemaleNames = [
    'Vasugi', 'Tharani', 'Kalaivani', 'Sivagami', 'Kamaladevi',
    'Yogeswari', 'Sivamalar', 'Rajeswary', 'Thenmoli', 'Luxmy'
];

$jobs = [
    'Teacher', 'Farmer', 'Government Officer', 'Business Owner', 'Driver',
    'Nurse', 'Accountant', 'Shop Keeper', 'Electrician', 'Clerk'
];

function randomElement(array $items)
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
        return str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT) . randomElement(['V', 'X']);
    }

    return (string) random_int(198000000000, 200299999999);
}

function generateDobForGrade(string $grade, int $index): string
{
    $baseYears = [
        '6' => 2014,
        '7' => 2013,
        '8' => 2012,
        '9' => 2011,
        '10' => 2010,
        '11' => 2009,
    ];

    $year = $baseYears[$grade] + ($index % 2 === 0 ? 0 : 1);
    $month = str_pad((string) random_int(1, 12), 2, '0', STR_PAD_LEFT);
    $day = str_pad((string) random_int(1, 28), 2, '0', STR_PAD_LEFT);

    return "{$year}-{$month}-{$day}";
}

function buildStudentName(string $gender, array $initials, array $maleNames, array $femaleNames): string
{
    $firstName = $gender === 'Male' ? randomElement($maleNames) : randomElement($femaleNames);
    return randomElement($initials) . ' ' . $firstName;
}

function buildGuardianName(string $gender, array $maleNames, array $femaleNames): string
{
    return $gender === 'Male' ? randomElement($maleNames) : randomElement($femaleNames);
}

try {
    $db = getDB();

    $roleStmt = $db->prepare("SELECT id FROM roles WHERE name = 'STUDENT' LIMIT 1");
    $roleStmt->execute();
    $studentRoleId = $roleStmt->fetchColumn();

    if (!$studentRoleId) {
        throw new RuntimeException("STUDENT role not found in roles table.");
    }

    $checkUserStmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $insertUserStmt = $db->prepare(
        "INSERT INTO users (full_name, email, password_hash, role_id, phone, dob, gender, address, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $insertStudentStmt = $db->prepare(
        "INSERT INTO students (user_id, school_name, grade, siblings_count, guardian_name, guardian_job, guardian_nic)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    $usedPhones = [];
    $insertedCount = 0;
    $skippedCount = 0;

    $db->beginTransaction();

    foreach ($grades as $grade) {
        for ($i = 1; $i <= $studentsPerGrade; $i++) {
            $gender = random_int(0, 1) === 0 ? 'Male' : 'Female';
            $fullName = buildStudentName($gender, $initials, $maleNames, $femaleNames);
            $emailSlug = strtolower(str_replace(['.', ' '], '', $fullName));
            $email = sprintf('%s.g%s.%02d@gmail.com', $emailSlug, $grade, $i);

            $checkUserStmt->execute([$email]);
            if ($checkUserStmt->fetchColumn()) {
                $skippedCount++;
                continue;
            }

            $phone = generatePhone($usedPhones);
            $dob = generateDobForGrade($grade, $i);
            $address = randomElement($addresses);
            $school = randomElement($schools);
            $siblingsCount = random_int(0, 3);
            $guardianGender = random_int(0, 1) === 0 ? 'Male' : 'Female';
            $guardianName = buildGuardianName($guardianGender, $guardianMaleNames, $guardianFemaleNames);
            $guardianJob = randomElement($jobs);
            $guardianNic = generateNic();

            $insertUserStmt->execute([
                $fullName,
                $email,
                $passwordHash,
                $studentRoleId,
                $phone,
                $dob,
                $gender,
                $address,
                'ACTIVE',
            ]);

            $userId = (int) $db->lastInsertId();

            $insertStudentStmt->execute([
                $userId,
                $school,
                $grade,
                $siblingsCount,
                $guardianName,
                $guardianJob,
                $guardianNic,
            ]);

            $insertedCount++;
        }
    }

    $db->commit();

    echo "Student seeding completed successfully." . PHP_EOL;
    echo "Inserted: {$insertedCount}" . PHP_EOL;
    echo "Skipped (existing email): {$skippedCount}" . PHP_EOL;
    echo "Grades covered: 6 to 11, {$studentsPerGrade} students each target." . PHP_EOL;
    echo "Default password for seeded students: {$defaultPassword}" . PHP_EOL;
    echo "Status used: ACTIVE (schema equivalent of approved)." . PHP_EOL;
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    fwrite(STDERR, "Seeding failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
