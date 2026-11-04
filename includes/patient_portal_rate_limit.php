<?php

function patient_portal_check_rate_limit(PDO $pdo, string $ipAddress): array
{
    if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
        throw new InvalidArgumentException('A valid client IP address is required.');
    }

    try {
        $pdo->beginTransaction();
        $upsert = $pdo->prepare(
            'INSERT INTO `patient_portal_login_rate_limit`
                (`ip_address`, `window_started_at`, `attempt_count`)
             VALUES (?, UTC_TIMESTAMP(), 1)
             ON DUPLICATE KEY UPDATE
                `attempt_count` = IF(
                    `window_started_at` <= UTC_TIMESTAMP() - INTERVAL 15 MINUTE,
                    1,
                    LEAST(`attempt_count` + 1, 11)
                ),
                `window_started_at` = IF(
                    `window_started_at` <= UTC_TIMESTAMP() - INTERVAL 15 MINUTE,
                    UTC_TIMESTAMP(),
                    `window_started_at`
                )'
        );
        $upsert->execute([$ipAddress]);

        $select = $pdo->prepare(
            'SELECT `attempt_count`,
                    GREATEST(0, 900 - TIMESTAMPDIFF(SECOND, `window_started_at`, UTC_TIMESTAMP())) AS `retry_after`
             FROM `patient_portal_login_rate_limit`
             WHERE `ip_address` = ?
             FOR UPDATE'
        );
        $select->execute([$ipAddress]);
        $attempt = $select->fetch(PDO::FETCH_ASSOC);
        $pdo->commit();

        if (!$attempt) {
            throw new RuntimeException('The patient portal rate-limit record could not be read.');
        }

        return [
            'allowed' => (int)$attempt['attempt_count'] <= 10,
            'retry_after' => (int)$attempt['retry_after'],
        ];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[patient_portal_rate_limit] ' . $error->getMessage());
        return ['allowed' => false, 'retry_after' => 60, 'unavailable' => true];
    }
}
