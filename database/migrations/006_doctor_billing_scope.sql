ALTER TABLE `admission`
    ADD COLUMN `care_scope` enum('ER','INPATIENT') NOT NULL DEFAULT 'INPATIENT'
    AFTER `admission_type`;

UPDATE `admission`
SET `care_scope` = CASE
    WHEN UPPER(TRIM(COALESCE(`admission_type`, ''))) IN ('EMERGENCY', 'ER')
        THEN 'ER'
    ELSE 'INPATIENT'
END;

ALTER TABLE `doctor`
    ADD COLUMN `care_scope` enum('ER','INPATIENT') NOT NULL DEFAULT 'INPATIENT'
    AFTER `consultation_fee`;

UPDATE `doctor` d
INNER JOIN `admission_doctor` ad ON ad.doctor_id = d.doctor_id
INNER JOIN `admission` a ON a.admission_id = ad.admission_id
SET d.care_scope = 'ER'
WHERE a.care_scope = 'ER';

ALTER TABLE `admission_doctor`
    ADD COLUMN `care_scope` enum('ER','INPATIENT') NOT NULL DEFAULT 'INPATIENT'
    AFTER `doctor_id`;

UPDATE `admission_doctor` ad
INNER JOIN `admission` a ON a.admission_id = ad.admission_id
SET ad.care_scope = a.care_scope;

ALTER TABLE `charge`
    ADD COLUMN `care_scope` enum('ER','INPATIENT') NOT NULL DEFAULT 'INPATIENT'
    AFTER `source_type`;

UPDATE `charge`
SET `care_scope` = 'ER'
WHERE `source_type` = 'ER';

INSERT INTO `charge_item`
    (`category_id`, `item_code`, `item_name`, `default_price`, `is_active`,
     `is_taxable`, `requires_doctor_order`, `unit_of_measure`, `is_stockable`,
     `stock_quantity`, `tracks_patient_doses`)
SELECT cc.category_id, 'PROF-ER-CONSULT', 'Emergency Doctor Fee', 800.00, 1,
       0, 0, 'visit', 0, 0, 0
FROM `charge_category` cc
WHERE cc.category_name = 'Emergency Room'
  AND NOT EXISTS (
      SELECT 1 FROM `charge_item` WHERE `item_code` = 'PROF-ER-CONSULT'
  );

INSERT INTO `room_type`
    (`room_type_name`, `description`, `rate_per_day`, `charge_interval`, `capacity`)
SELECT 'Emergency Bed', 'Emergency department treatment bed', 500.00, 'hour', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `room_type` WHERE `room_type_name` = 'Emergency Bed'
);

INSERT INTO `charge_item`
    (`category_id`, `item_code`, `item_name`, `default_price`, `is_active`,
     `is_taxable`, `requires_doctor_order`, `unit_of_measure`, `is_stockable`,
     `stock_quantity`, `tracks_patient_doses`)
SELECT cc.category_id, 'ROOM-ER-BED', 'Emergency Bed per Hour', 500.00, 1,
       0, 0, 'hour', 0, 0, 0
FROM `charge_category` cc
WHERE cc.category_name = 'Room & Board'
  AND NOT EXISTS (
      SELECT 1 FROM `charge_item` WHERE `item_code` = 'ROOM-ER-BED'
  );

INSERT INTO `room`
    (`room_type_id`, `status_id`, `floor_level_id`, `room_number`, `is_active`)
SELECT rt.room_type_id,
       (SELECT status_id FROM `room_status` WHERE status_name = 'Available' LIMIT 1),
       (SELECT floor_level_id FROM `floor_level` WHERE building_id = 1 ORDER BY floor_level_id LIMIT 1),
       'ER-01',
       1
FROM `room_type` rt
WHERE rt.room_type_name = 'Emergency Bed'
  AND NOT EXISTS (
      SELECT 1 FROM `room` WHERE `room_number` = 'ER-01'
  );
