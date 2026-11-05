INSERT INTO `charge_item`
    (`category_id`, `item_code`, `item_name`, `default_price`, `is_active`,
     `is_taxable`, `requires_doctor_order`, `unit_of_measure`, `is_stockable`,
     `stock_quantity`)
SELECT cc.category_id, items.item_code, items.item_name, items.default_price, 1,
       1, 1, items.unit_of_measure, 0, 0
FROM `charge_category` cc
CROSS JOIN (
    SELECT 'ER-SUP-BANDAGE' AS item_code, 'Elastic Bandage, 4 in' AS item_name, 120.00 AS default_price, 'roll' AS unit_of_measure
    UNION ALL SELECT 'ER-SUP-GAUZE', 'Sterile Gauze Pads, 4 x 4 in', 35.00, 'pack'
    UNION ALL SELECT 'ER-SUP-ROLLER-GAUZE', 'Roller Gauze Bandage, 4 in', 45.00, 'roll'
    UNION ALL SELECT 'ER-SUP-ADHESIVE-BANDAGE', 'Adhesive Bandage Strip', 10.00, 'piece'
    UNION ALL SELECT 'ER-SUP-MEDICAL-TAPE', 'Medical Adhesive Tape, 1 in', 55.00, 'roll'
    UNION ALL SELECT 'ER-SUP-WOUND-DRESSING', 'Sterile Wound Dressing Kit', 180.00, 'kit'
    UNION ALL SELECT 'ER-SUP-STERILE-GLOVES', 'Sterile Gloves', 35.00, 'pair'
    UNION ALL SELECT 'ER-SUP-ALCOHOL-SWAB', 'Alcohol Prep Swab', 5.00, 'piece'
) AS items
WHERE cc.category_name = 'Ancillary Services'
  AND NOT EXISTS (
      SELECT 1
      FROM `charge_item` existing
      WHERE existing.item_code = items.item_code
  );
