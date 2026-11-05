INSERT INTO `admission_status` (`status_name`, `color_code`)
SELECT 'Emergency', '#dc2626'
WHERE NOT EXISTS (
    SELECT 1
    FROM `admission_status`
    WHERE `status_name` = 'Emergency'
);

UPDATE `admission` a
INNER JOIN `admission_status` current_status
    ON current_status.`status_id` = a.`status_id`
INNER JOIN `admission_status` emergency_status
    ON emergency_status.`status_name` = 'Emergency'
SET a.`status_id` = emergency_status.`status_id`
WHERE a.`care_scope` = 'ER'
  AND current_status.`status_name` <> 'Discharged'
  AND EXISTS (
      SELECT 1
      FROM `room_assignment` ra
      INNER JOIN `room` r ON r.`room_id` = ra.`room_id`
      INNER JOIN `room_type` rt ON rt.`room_type_id` = r.`room_type_id`
      WHERE ra.`admission_id` = a.`admission_id`
        AND ra.`is_active` = 1
        AND ra.`end_datetime` IS NULL
        AND rt.`room_type_name` = 'Emergency Bed'
  );

UPDATE `admission`
SET `admission_type` = 'Urgent'
WHERE UPPER(TRIM(COALESCE(`admission_type`, ''))) IN ('EMERGENCY', 'ER');

ALTER TABLE `admission`
    MODIFY COLUMN `care_scope` enum('ER','INPATIENT','OUTPATIENT')
    NOT NULL DEFAULT 'INPATIENT';

UPDATE `admission` a
INNER JOIN `admission_status` ast
    ON ast.`status_id` = a.`status_id`
SET a.`care_scope` = CASE
    WHEN ast.`status_name` = 'Emergency' THEN 'ER'
    WHEN ast.`status_name` = 'Admitted' THEN 'INPATIENT'
    WHEN ast.`status_name` = 'Discharged' THEN 'OUTPATIENT'
    ELSE 'INPATIENT'
END
WHERE NOT (a.`care_scope` <=> CASE
    WHEN ast.`status_name` = 'Emergency' THEN 'ER'
    WHEN ast.`status_name` = 'Admitted' THEN 'INPATIENT'
    WHEN ast.`status_name` = 'Discharged' THEN 'OUTPATIENT'
    ELSE 'INPATIENT'
END);
