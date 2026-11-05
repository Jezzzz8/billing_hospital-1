ALTER TABLE `admission`
    ADD COLUMN `inpatient_requested_at` datetime DEFAULT NULL
        AFTER `discharged_by_user_id`,
    ADD COLUMN `inpatient_requested_by_user_id` int(11) DEFAULT NULL
        AFTER `inpatient_requested_at`,
    ADD KEY `idx_admission_inpatient_requested_at` (`inpatient_requested_at`);
