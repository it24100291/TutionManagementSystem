<?php

class EmailVerification
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDB();
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS email_verifications (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                otp_code VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                is_verified TINYINT(1) NOT NULL DEFAULT 0,
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_email_verifications_email (email),
                INDEX idx_email_verifications_expires_at (expires_at)
            )
        ");

        $verifiedStmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'email_verifications'
              AND column_name = 'is_verified'
        ");
        $verifiedStmt->execute();
        if ((int) $verifiedStmt->fetchColumn() === 0) {
            $this->db->exec("
                ALTER TABLE email_verifications
                ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER expires_at
            ");
        }
    }

    public function upsert(string $email, string $otpHash, string $expiresAt): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO email_verifications (email, otp_code, expires_at, is_verified, attempts)
            VALUES (?, ?, ?, 0, 0)
            ON DUPLICATE KEY UPDATE
                otp_code = VALUES(otp_code),
                expires_at = VALUES(expires_at),
                is_verified = 0,
                attempts = 0,
                created_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$email, $otpHash, $expiresAt]);
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, email, otp_code, expires_at, attempts, created_at
                   , is_verified
            FROM email_verifications
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function incrementAttempts(int $id): void
    {
        $stmt = $this->db->prepare("
            UPDATE email_verifications
            SET attempts = attempts + 1
            WHERE id = ?
        ");
        $stmt->execute([$id]);
    }

    public function markVerified(int $id): void
    {
        $stmt = $this->db->prepare("
            UPDATE email_verifications
            SET is_verified = 1
            WHERE id = ?
        ");
        $stmt->execute([$id]);
    }

    public function deleteByEmail(string $email): void
    {
        $stmt = $this->db->prepare("DELETE FROM email_verifications WHERE email = ?");
        $stmt->execute([$email]);
    }
}
