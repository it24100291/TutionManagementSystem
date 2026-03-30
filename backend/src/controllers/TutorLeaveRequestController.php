<?php

class TutorLeaveRequestController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDB();
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS tutor_leave_requests (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tutor_id INT UNSIGNED NOT NULL,
                leave_date DATE NOT NULL,
                reason TEXT NOT NULL,
                status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
                admin_reply TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_tutor_leave_requests_tutor
                    FOREIGN KEY (tutor_id) REFERENCES tutors(id)
                    ON UPDATE CASCADE
                    ON DELETE CASCADE,
                INDEX idx_tutor_leave_requests_tutor_id (tutor_id),
                INDEX idx_tutor_leave_requests_leave_date (leave_date),
                INDEX idx_tutor_leave_requests_status (status)
            )
        ");

        $columnStmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'tutor_leave_requests'
              AND column_name = 'attachment_path'
        ");
        $columnStmt->execute();
        if ((int) $columnStmt->fetchColumn() === 0) {
            $this->db->exec("
                ALTER TABLE tutor_leave_requests
                ADD COLUMN attachment_path VARCHAR(500) NULL AFTER admin_reply
            ");
        }

        $multiColumnStmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'tutor_leave_requests'
              AND column_name = 'attachment_paths'
        ");
        $multiColumnStmt->execute();
        if ((int) $multiColumnStmt->fetchColumn() === 0) {
            $this->db->exec("
                ALTER TABLE tutor_leave_requests
                ADD COLUMN attachment_paths LONGTEXT NULL AFTER attachment_path
            ");
        }
    }

    private function getTutorProfileByUserId(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                t.id,
                t.subject,
                u.full_name
            FROM tutors t
            JOIN users u ON u.id = t.user_id
            WHERE t.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            Response::error('Tutor profile not found', 404);
        }

        return $row;
    }

    private function normalizeLeaveRequestRow(array $row): array
    {
        $paths = [];
        if (!empty($row['attachment_paths'])) {
            $decoded = json_decode((string) $row['attachment_paths'], true);
            if (is_array($decoded)) {
                $paths = array_values(array_filter($decoded, fn ($value) => is_string($value) && $value !== ''));
            }
        }

        if (empty($paths) && !empty($row['attachment_path'])) {
            $paths = [(string) $row['attachment_path']];
        }

        $row['attachment_files'] = $paths;
        return $row;
    }

    private function getLeaveRequestById(int $id): array
    {
        $stmt = $this->db->prepare("
            SELECT
                tlr.id,
                tlr.tutor_id,
                tlr.leave_date,
                tlr.reason,
                tlr.status,
                tlr.admin_reply,
                tlr.attachment_path,
                tlr.attachment_paths,
                tlr.created_at,
                u.full_name AS tutor_name,
                t.subject
            FROM tutor_leave_requests tlr
            JOIN tutors t ON t.id = tlr.tutor_id
            JOIN users u ON u.id = t.user_id
            WHERE tlr.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            Response::error('Leave request not found', 404);
        }

        return $this->normalizeLeaveRequestRow($row);
    }

    public function getMine(): void
    {
        $userId = (int) $_SESSION['user']['id'];
        $tutor = $this->getTutorProfileByUserId($userId);

        $stmt = $this->db->prepare("
            SELECT
                id,
                leave_date,
                reason,
                status,
                admin_reply,
                attachment_path,
                attachment_paths,
                created_at
            FROM tutor_leave_requests
            WHERE tutor_id = ?
            ORDER BY
                CASE status
                    WHEN 'Pending' THEN 0
                    WHEN 'Approved' THEN 1
                    WHEN 'Rejected' THEN 2
                    ELSE 3
                END,
                leave_date DESC,
                created_at DESC
        ");
        $stmt->execute([(int) $tutor['id']]);

        $rows = array_map([$this, 'normalizeLeaveRequestRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        Response::success($rows);
    }

    public function submit(): void
    {
        $userId = (int) $_SESSION['user']['id'];
        $tutor = $this->getTutorProfileByUserId($userId);
        $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        $data = str_contains(strtolower($contentType), 'multipart/form-data')
            ? $_POST
            : (json_decode(file_get_contents('php://input'), true) ?: []);

        $leaveDate = trim((string) ($data['leave_date'] ?? ''));
        $reason = trim((string) ($data['reason'] ?? ''));

        if ($leaveDate === '') {
            Response::error('Leave date is required');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $leaveDate)) {
            Response::error('Invalid leave date');
        }

        $today = new DateTimeImmutable('today');
        $selectedDate = DateTimeImmutable::createFromFormat('Y-m-d', $leaveDate);
        if (!$selectedDate || $selectedDate->format('Y-m-d') !== $leaveDate) {
            Response::error('Invalid leave date');
        }

        if ($selectedDate <= $today) {
            Response::error('Leave date must be after today');
        }

        if ($reason === '') {
            Response::error('Reason is required');
        }

        $duplicateStmt = $this->db->prepare("
            SELECT id
            FROM tutor_leave_requests
            WHERE tutor_id = ? AND leave_date = ? AND status = 'Pending'
            LIMIT 1
        ");
        $duplicateStmt->execute([(int) $tutor['id'], $leaveDate]);
        if ($duplicateStmt->fetchColumn()) {
            Response::error('A pending leave request already exists for this date', 409);
        }

        $attachmentPaths = $this->handleAttachmentUpload(true);
        $primaryAttachmentPath = $attachmentPaths[0] ?? null;

        $insertStmt = $this->db->prepare("
            INSERT INTO tutor_leave_requests (tutor_id, leave_date, reason, status, attachment_path, attachment_paths)
            VALUES (?, ?, ?, 'Pending', ?, ?)
        ");
        $insertStmt->execute([
            (int) $tutor['id'],
            $leaveDate,
            $reason,
            $primaryAttachmentPath,
            json_encode($attachmentPaths),
        ]);

        $createdId = (int) $this->db->lastInsertId();
        Response::success($this->getLeaveRequestById($createdId), 201);
    }

    public function getAllForAdmin(): void
    {
        $stmt = $this->db->query("
            SELECT
                tlr.id,
                u.full_name AS tutor_name,
                t.subject,
                tlr.leave_date,
                tlr.reason,
                tlr.status,
                tlr.admin_reply,
                tlr.attachment_path,
                tlr.attachment_paths,
                tlr.created_at
            FROM tutor_leave_requests tlr
            JOIN tutors t ON t.id = tlr.tutor_id
            JOIN users u ON u.id = t.user_id
            ORDER BY
                CASE tlr.status
                    WHEN 'Pending' THEN 0
                    WHEN 'Approved' THEN 1
                    WHEN 'Rejected' THEN 2
                    ELSE 3
                END,
                tlr.leave_date DESC,
                tlr.created_at DESC
        ");

        $rows = array_map([$this, 'normalizeLeaveRequestRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        Response::success($rows);
    }

    public function updateByAdmin(int $id): void
    {
        $existing = $this->getLeaveRequestById($id);
        $data = json_decode(file_get_contents('php://input'), true) ?: [];

        $status = trim((string) ($data['status'] ?? ''));
        $adminReply = trim((string) ($data['admin_reply'] ?? ''));

        if (!in_array($status, ['Approved', 'Rejected'], true)) {
            Response::error('Invalid status');
        }

        if ($status === 'Rejected' && $adminReply === '') {
            Response::error('Reply message is required when rejecting a request');
        }

        if ($existing['status'] !== 'Pending') {
            Response::error('Only pending leave requests can be updated', 409);
        }

        $updateStmt = $this->db->prepare("
            UPDATE tutor_leave_requests
            SET status = ?, admin_reply = ?
            WHERE id = ?
        ");
        $updateStmt->execute([
            $status,
            $adminReply !== '' ? $adminReply : null,
            $id,
        ]);

        Response::success($this->getLeaveRequestById($id));
    }

    private function handleAttachmentUpload(bool $required = false): array
    {
        if (!isset($_FILES['attachment']) || !is_array($_FILES['attachment'])) {
            if ($required) {
                Response::error('Upload file or worksheet is required');
            }
            return [];
        }

        $file = $_FILES['attachment'];
        $isMulti = is_array($file['name'] ?? null);
        $names = $isMulti ? ($file['name'] ?? []) : [$file['name'] ?? ''];
        $errors = $isMulti ? ($file['error'] ?? []) : [$file['error'] ?? UPLOAD_ERR_NO_FILE];
        $tmpNames = $isMulti ? ($file['tmp_name'] ?? []) : [$file['tmp_name'] ?? ''];
        $sizes = $isMulti ? ($file['size'] ?? []) : [$file['size'] ?? 0];

        $hasRealFile = false;
        foreach ($errors as $uploadError) {
            if ((int) $uploadError !== UPLOAD_ERR_NO_FILE) {
                $hasRealFile = true;
                break;
            }
        }

        if (!$hasRealFile) {
            if ($required) {
                Response::error('Upload file or worksheet is required');
            }
            return [];
        }

        $maxBytes = 5 * 1024 * 1024;
        $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

        $uploadDir = __DIR__ . '/../../public/uploads/tutor-leave-requests';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            Response::error('Failed to prepare upload directory', 500);
        }

        $savedPaths = [];

        foreach ($names as $index => $originalName) {
            $uploadError = (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadError === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($uploadError !== UPLOAD_ERR_OK) {
                Response::error('Attachment upload failed');
            }

            if ((int) ($sizes[$index] ?? 0) > $maxBytes) {
                Response::error('Each attachment must be 5MB or smaller');
            }

            $originalName = (string) $originalName;
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions, true)) {
                Response::error('Invalid attachment type');
            }

            $safeBaseName = preg_replace('/[^a-zA-Z0-9_-]/', '-', pathinfo($originalName, PATHINFO_FILENAME));
            $safeBaseName = trim((string) $safeBaseName, '-');
            if ($safeBaseName === '') {
                $safeBaseName = 'attachment';
            }

            $fileName = sprintf(
                '%s-%s.%s',
                $safeBaseName,
                bin2hex(random_bytes(6)),
                $extension
            );
            $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

            if (!move_uploaded_file((string) ($tmpNames[$index] ?? ''), $targetPath)) {
                Response::error('Failed to save attachment', 500);
            }

            $savedPaths[] = '/uploads/tutor-leave-requests/' . $fileName;
        }

        if ($required && empty($savedPaths)) {
            Response::error('Upload file or worksheet is required');
        }

        return $savedPaths;
    }
}
