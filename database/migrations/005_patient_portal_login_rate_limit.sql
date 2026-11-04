CREATE TABLE `patient_portal_login_rate_limit` (
    `ip_address` varchar(45) NOT NULL,
    `window_started_at` datetime NOT NULL,
    `attempt_count` smallint(5) unsigned NOT NULL,
    PRIMARY KEY (`ip_address`),
    KEY `idx_patient_portal_login_rate_limit_window` (`window_started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
