<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../src/config/env.php';
require_once __DIR__ . '/../../src/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $payload = json_decode(file_get_contents('php://input') ?: '', true);

    $name = trim((string) ($payload['name'] ?? ''));
    $email = trim((string) ($payload['email'] ?? ''));
    $message = trim((string) ($payload['message'] ?? ''));

    if ($name === '' || $email === '' || $message === '') {
        http_response_code(400);
        echo json_encode(['error' => 'All fields are required.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email address.']);
        exit;
    }

    $db = getDB();

    $db->exec("
        CREATE TABLE IF NOT EXISTS contact_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL,
            message TEXT NOT NULL,
            submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $stmt = $db->prepare("
        INSERT INTO contact_messages (name, email, message)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$name, $email, $message]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
