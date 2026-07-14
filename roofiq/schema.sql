-- ============================================================
-- SHEKHAR ROOFIQ AI ENTERPRISE — Complete Database Schema
-- MySQL 5.7+ Compatible
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- ============================================================
-- 1. USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`             INT(11)      NOT NULL AUTO_INCREMENT,
  `username`       VARCHAR(80)  NOT NULL,
  `email`          VARCHAR(180) NOT NULL,
  `password_hash`  VARCHAR(255) NOT NULL,
  `full_name`      VARCHAR(120) NOT NULL,
  `role`           ENUM('Admin','Estimator') NOT NULL DEFAULT 'Estimator',
  `status`         ENUM('Active','Inactive','Suspended') NOT NULL DEFAULT 'Active',
  `remember_token` VARCHAR(64)  DEFAULT NULL,
  `reset_token`    VARCHAR(64)  DEFAULT NULL,
  `reset_expires`  DATETIME     DEFAULT NULL,
  `last_login`     DATETIME     DEFAULT NULL,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY `uq_email`    (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. PROPERTIES
-- ============================================================
CREATE TABLE IF NOT EXISTS `properties` (
  `id`                INT(11)       NOT NULL AUTO_INCREMENT,
  `address`           VARCHAR(255)  NOT NULL,
  `city`              VARCHAR(100)  DEFAULT NULL,
  `state`             VARCHAR(50)   DEFAULT NULL,
  `zip`               VARCHAR(20)   DEFAULT NULL,
  `latitude`          DECIMAL(10,7) DEFAULT NULL,
  `longitude`         DECIMAL(10,7) DEFAULT NULL,
  `formatted_address` VARCHAR(300)  DEFAULT NULL,
  `place_id`          VARCHAR(255)  DEFAULT NULL,
  `property_type`     ENUM('Residential','Commercial','Industrial','Mixed') DEFAULT 'Residential',
  `year_built`        YEAR          DEFAULT NULL,
  `stories`           TINYINT(2)    DEFAULT 1,
  `notes`             TEXT          DEFAULT NULL,
  `created_by`        INT(11)       DEFAULT NULL,
  `created_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_address` (`address`(191)),
  KEY `idx_latlong` (`latitude`,`longitude`),
  KEY `fk_prop_user` (`created_by`),
  CONSTRAINT `fk_prop_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. BUILDING FOOTPRINTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `building_footprints` (
  `id`            INT(11)        NOT NULL AUTO_INCREMENT,
  `property_id`   INT(11)        DEFAULT NULL,
  `address`       VARCHAR(255)   DEFAULT NULL,
  `latitude`      DECIMAL(10,7)  DEFAULT NULL,
  `longitude`     DECIMAL(10,7)  DEFAULT NULL,
  `geojson`       MEDIUMTEXT     DEFAULT NULL,
  `source`        VARCHAR(80)    DEFAULT 'microsoft',
  `roof_area_sqft` DECIMAL(10,2) DEFAULT NULL,
  `base_area_sqft` DECIMAL(10,2) DEFAULT NULL,
  `perimeter_ft`  DECIMAL(10,2)  DEFAULT NULL,
  `building_height_ft` DECIMAL(6,2) DEFAULT NULL,
  `updated_at`    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_fp_prop` (`property_id`),
  CONSTRAINT `fk_fp_prop` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. ROOF ANALYSIS
-- ============================================================
CREATE TABLE IF NOT EXISTS `roof_analysis` (
  `id`                 INT(11)       NOT NULL AUTO_INCREMENT,
  `property_id`        INT(11)       NOT NULL,
  `footprint_id`       INT(11)       DEFAULT NULL,
  `analyzed_by`        INT(11)       DEFAULT NULL,
  -- Measurements
  `roof_area_sqft`     DECIMAL(10,2) DEFAULT NULL,
  `roof_squares`       DECIMAL(8,2)  DEFAULT NULL,
  `roof_pitch_deg`     DECIMAL(5,2)  DEFAULT NULL,
  `roof_pitch_ratio`   VARCHAR(20)   DEFAULT NULL,
  `roof_slope`         DECIMAL(5,2)  DEFAULT NULL,
  `roof_height_ft`     DECIMAL(6,2)  DEFAULT NULL,
  `ridge_length_ft`    DECIMAL(8,2)  DEFAULT NULL,
  `valley_length_ft`   DECIMAL(8,2)  DEFAULT NULL,
  `hip_length_ft`      DECIMAL(8,2)  DEFAULT NULL,
  `eave_length_ft`     DECIMAL(8,2)  DEFAULT NULL,
  `rake_length_ft`     DECIMAL(8,2)  DEFAULT NULL,
  `perimeter_ft`       DECIMAL(8,2)  DEFAULT NULL,
  `facets_count`       TINYINT(3)    DEFAULT NULL,
  `complexity`         ENUM('Simple','Moderate','Complex','Very Complex') DEFAULT 'Moderate',
  -- Type
  `roof_type`          VARCHAR(80)   DEFAULT NULL,
  `roof_type_confidence` DECIMAL(5,2) DEFAULT NULL,
  `roof_material`      VARCHAR(80)   DEFAULT NULL,
  -- Scores
  `condition_score`    TINYINT(3)    DEFAULT NULL,
  `condition_label`    VARCHAR(40)   DEFAULT NULL,
  -- Solar
  `solar_area_sqft`    DECIMAL(10,2) DEFAULT NULL,
  `solar_panels`       SMALLINT(5)   DEFAULT NULL,
  `solar_kwh_year`     DECIMAL(10,2) DEFAULT NULL,
  `solar_savings_year` DECIMAL(10,2) DEFAULT NULL,
  -- Raw AI response
  `ai_raw_json`        LONGTEXT      DEFAULT NULL,
  `ai_processing_ms`   INT(11)       DEFAULT NULL,
  `status`             ENUM('Pending','Processing','Complete','Failed') DEFAULT 'Pending',
  `created_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_ra_prop`  (`property_id`),
  KEY `fk_ra_fp`    (`footprint_id`),
  KEY `fk_ra_user`  (`analyzed_by`),
  CONSTRAINT `fk_ra_prop` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ra_fp`   FOREIGN KEY (`footprint_id`) REFERENCES `building_footprints` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ra_user` FOREIGN KEY (`analyzed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. DAMAGE REPORTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `damage_reports` (
  `id`             INT(11)    NOT NULL AUTO_INCREMENT,
  `analysis_id`    INT(11)    NOT NULL,
  `property_id`    INT(11)    NOT NULL,
  `damage_type`    VARCHAR(80) NOT NULL,
  `damage_label`   VARCHAR(120) DEFAULT NULL,
  `severity`       ENUM('Low','Medium','High','Critical') DEFAULT 'Medium',
  `confidence`     DECIMAL(5,3) DEFAULT NULL,
  `location_desc`  VARCHAR(255) DEFAULT NULL,
  `bbox_x1`        DECIMAL(7,4) DEFAULT NULL,
  `bbox_y1`        DECIMAL(7,4) DEFAULT NULL,
  `bbox_x2`        DECIMAL(7,4) DEFAULT NULL,
  `bbox_y2`        DECIMAL(7,4) DEFAULT NULL,
  `repair_priority` TINYINT(3) DEFAULT 5,
  `estimated_cost` DECIMAL(10,2) DEFAULT NULL,
  `notes`          TEXT DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_dr_analysis` (`analysis_id`),
  KEY `fk_dr_prop`     (`property_id`),
  CONSTRAINT `fk_dr_analysis` FOREIGN KEY (`analysis_id`) REFERENCES `roof_analysis` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dr_prop`     FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. MATERIALS (Master Catalog)
-- ============================================================
CREATE TABLE IF NOT EXISTS `materials` (
  `id`               INT(11)      NOT NULL AUTO_INCREMENT,
  `sku`              VARCHAR(80)  DEFAULT NULL,
  `name`             VARCHAR(200) NOT NULL,
  `category`         VARCHAR(80)  DEFAULT NULL,
  `subcategory`      VARCHAR(80)  DEFAULT NULL,
  `roof_type`        ENUM('Commercial','Residential','Both') DEFAULT 'Both',
  `manufacturer`     VARCHAR(120) DEFAULT NULL,
  `unit`             VARCHAR(30)  DEFAULT 'SQ',
  `unit_cost`        DECIMAL(10,2) DEFAULT NULL,
  `coverage`         DECIMAL(10,2) DEFAULT NULL,
  `coverage_unit`    VARCHAR(30)  DEFAULT 'sq ft',
  `waste_factor_pct` DECIMAL(5,2) DEFAULT 10.00,
  `description`      TEXT         DEFAULT NULL,
  `specifications`   TEXT         DEFAULT NULL,
  `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order`       SMALLINT(5)  DEFAULT 0,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mat_category` (`category`),
  KEY `idx_mat_rooftype` (`roof_type`),
  KEY `idx_mat_active`   (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. MATERIAL TAKEOFFS
-- ============================================================
CREATE TABLE IF NOT EXISTS `material_takeoffs` (
  `id`             INT(11)       NOT NULL AUTO_INCREMENT,
  `analysis_id`    INT(11)       NOT NULL,
  `property_id`    INT(11)       NOT NULL,
  `material_id`    INT(11)       DEFAULT NULL,
  `material_name`  VARCHAR(200)  NOT NULL,
  `manufacturer`   VARCHAR(120)  DEFAULT NULL,
  `category`       VARCHAR(80)   DEFAULT NULL,
  `quantity`       DECIMAL(10,3) DEFAULT NULL,
  `unit`           VARCHAR(30)   DEFAULT NULL,
  `unit_cost`      DECIMAL(10,2) DEFAULT NULL,
  `total_cost`     DECIMAL(10,2) DEFAULT NULL,
  `waste_factor`   DECIMAL(5,2)  DEFAULT 10.00,
  `notes`          VARCHAR(255)  DEFAULT NULL,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_mt_analysis` (`analysis_id`),
  KEY `fk_mt_prop`     (`property_id`),
  KEY `fk_mt_mat`      (`material_id`),
  CONSTRAINT `fk_mt_analysis` FOREIGN KEY (`analysis_id`) REFERENCES `roof_analysis` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mt_prop`     FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mt_mat`      FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. VENDORS
-- ============================================================
CREATE TABLE IF NOT EXISTS `vendors` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(180) NOT NULL,
  `contact_name` VARCHAR(120) DEFAULT NULL,
  `email`        VARCHAR(180) DEFAULT NULL,
  `phone`        VARCHAR(30)  DEFAULT NULL,
  `website`      VARCHAR(255) DEFAULT NULL,
  `address`      VARCHAR(255) DEFAULT NULL,
  `city`         VARCHAR(100) DEFAULT NULL,
  `state`        VARCHAR(50)  DEFAULT NULL,
  `zip`          VARCHAR(20)  DEFAULT NULL,
  `specialty`    VARCHAR(255) DEFAULT NULL,
  `notes`        TEXT         DEFAULT NULL,
  `is_preferred` TINYINT(1)   DEFAULT 0,
  `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vendor_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. VENDOR PRICING
-- ============================================================
CREATE TABLE IF NOT EXISTS `vendor_pricing` (
  `id`          INT(11)       NOT NULL AUTO_INCREMENT,
  `vendor_id`   INT(11)       NOT NULL,
  `material_id` INT(11)       DEFAULT NULL,
  `item_name`   VARCHAR(200)  NOT NULL,
  `sku`         VARCHAR(80)   DEFAULT NULL,
  `unit_price`  DECIMAL(10,2) NOT NULL,
  `unit`        VARCHAR(30)   DEFAULT NULL,
  `min_order`   DECIMAL(10,2) DEFAULT NULL,
  `lead_days`   TINYINT(3)    DEFAULT NULL,
  `effective_date` DATE       DEFAULT NULL,
  `expires_date`   DATE       DEFAULT NULL,
  `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_vp_vendor` (`vendor_id`),
  KEY `fk_vp_mat`    (`material_id`),
  CONSTRAINT `fk_vp_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vp_mat`    FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. VENDOR QUOTES
-- ============================================================
CREATE TABLE IF NOT EXISTS `vendor_quotes` (
  `id`           INT(11)       NOT NULL AUTO_INCREMENT,
  `vendor_id`    INT(11)       NOT NULL,
  `analysis_id`  INT(11)       DEFAULT NULL,
  `property_id`  INT(11)       DEFAULT NULL,
  `quote_number` VARCHAR(60)   DEFAULT NULL,
  `total_amount` DECIMAL(12,2) DEFAULT NULL,
  `valid_until`  DATE          DEFAULT NULL,
  `status`       ENUM('Draft','Sent','Accepted','Rejected','Expired') DEFAULT 'Draft',
  `notes`        TEXT          DEFAULT NULL,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_vq_vendor`   (`vendor_id`),
  KEY `fk_vq_analysis` (`analysis_id`),
  CONSTRAINT `fk_vq_vendor`   FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vq_analysis` FOREIGN KEY (`analysis_id`) REFERENCES `roof_analysis` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. PROCUREMENT REQUESTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `procurement_requests` (
  `id`               INT(11)       NOT NULL AUTO_INCREMENT,
  `analysis_id`      INT(11)       DEFAULT NULL,
  `property_id`      INT(11)       DEFAULT NULL,
  `project_id`       INT(11)       DEFAULT NULL,
  `vendor_id`        INT(11)       DEFAULT NULL,
  `requested_by`     INT(11)       DEFAULT NULL,
  `request_number`   VARCHAR(40)   DEFAULT NULL,
  `status`           ENUM('Draft','Submitted','Approved','Ordered','Received','Cancelled') DEFAULT 'Draft',
  `total_estimate`   DECIMAL(12,2) DEFAULT NULL,
  `delivery_address` VARCHAR(300)  DEFAULT NULL,
  `needed_by_date`   DATE          DEFAULT NULL,
  `notes`            TEXT          DEFAULT NULL,
  `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_pr_analysis` (`analysis_id`),
  KEY `fk_pr_prop`     (`property_id`),
  KEY `fk_pr_vendor`   (`vendor_id`),
  KEY `fk_pr_user`     (`requested_by`),
  CONSTRAINT `fk_pr_analysis` FOREIGN KEY (`analysis_id`) REFERENCES `roof_analysis` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pr_vendor`   FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pr_user`     FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. PROJECTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `projects` (
  `id`           INT(11)       NOT NULL AUTO_INCREMENT,
  `property_id`  INT(11)       NOT NULL,
  `analysis_id`  INT(11)       DEFAULT NULL,
  `project_name` VARCHAR(200)  NOT NULL,
  `project_type` ENUM('Repair','Replacement','New Install','Inspection','Solar','Maintenance') DEFAULT 'Replacement',
  `status`       ENUM('Lead','Inspection','Estimate','Proposal Sent','Sold','Ordered','Installed','Closed','Cancelled') DEFAULT 'Lead',
  `estimator_id` INT(11)       DEFAULT NULL,
  `contract_value` DECIMAL(12,2) DEFAULT NULL,
  `start_date`   DATE          DEFAULT NULL,
  `end_date`     DATE          DEFAULT NULL,
  `notes`        TEXT          DEFAULT NULL,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_pj_prop`      (`property_id`),
  KEY `fk_pj_analysis`  (`analysis_id`),
  KEY `fk_pj_estimator` (`estimator_id`),
  CONSTRAINT `fk_pj_prop`      FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pj_analysis`  FOREIGN KEY (`analysis_id`) REFERENCES `roof_analysis` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pj_estimator` FOREIGN KEY (`estimator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 13. REPORTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `reports` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `analysis_id` INT(11)      DEFAULT NULL,
  `property_id` INT(11)      DEFAULT NULL,
  `project_id`  INT(11)      DEFAULT NULL,
  `report_type` VARCHAR(60)  DEFAULT 'Full Analysis',
  `filename`    VARCHAR(255) DEFAULT NULL,
  `generated_by` INT(11)     DEFAULT NULL,
  `generated_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_rpt_analysis` (`analysis_id`),
  KEY `fk_rpt_prop`     (`property_id`),
  KEY `fk_rpt_user`     (`generated_by`),
  CONSTRAINT `fk_rpt_analysis` FOREIGN KEY (`analysis_id`) REFERENCES `roof_analysis` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rpt_prop`     FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rpt_user`     FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 14. NOTIFICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11)      NOT NULL,
  `title`      VARCHAR(200) NOT NULL,
  `message`    TEXT         DEFAULT NULL,
  `type`       VARCHAR(40)  DEFAULT 'info',
  `link`       VARCHAR(255) DEFAULT NULL,
  `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_notif_user` (`user_id`),
  KEY `idx_notif_read` (`is_read`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 15. ACTIVITY LOGS
-- ============================================================
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11)      DEFAULT NULL,
  `action`      VARCHAR(200) NOT NULL,
  `module`      VARCHAR(80)  DEFAULT NULL,
  `record_id`   INT(11)      DEFAULT NULL,
  `ip_address`  VARCHAR(45)  DEFAULT NULL,
  `user_agent`  VARCHAR(300) DEFAULT NULL,
  `details`     TEXT         DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_log_user` (`user_id`),
  KEY `idx_log_module` (`module`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 16. SETTINGS
-- ============================================================
CREATE TABLE IF NOT EXISTS `settings` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `key`         VARCHAR(100) NOT NULL,
  `value`       TEXT         DEFAULT NULL,
  `label`       VARCHAR(200) DEFAULT NULL,
  `group`       VARCHAR(80)  DEFAULT 'general',
  `type`        VARCHAR(30)  DEFAULT 'text',
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default Admin User (password: Admin@RoofIQ1)
INSERT INTO `users` (`username`,`email`,`password_hash`,`full_name`,`role`,`status`) VALUES
('admin','admin@roofiq.com','$2y$10$kirQNlszeC0628D3E/vQz.b8NIwxNP9ET4vH0RpwKjgrug8MkEpjm','Shekhar Admin','Admin','Active'),
('estimator1','estimator@roofiq.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Demo Estimator','Estimator','Active');

-- Default Vendors
INSERT INTO `vendors` (`name`,`contact_name`,`email`,`phone`,`website`,`specialty`,`is_preferred`,`is_active`) VALUES
('Shekhar Building Materials','Shekhar Mehta','sales@shekharbm.com','(401) 555-0100','https://shekharbm.com','Full-Service Roofing Supplier',1,1),
('ABC Supply Co.','Sales Team','orders@abcsupply.com','(800) 555-0101','https://www.abcsupply.com','Residential & Commercial Roofing',0,1),
('Beacon Roofing Supply','Regional Manager','info@beacon.com','(800) 555-0102','https://www.becn.com','Commercial Roofing',0,1),
('SRS Distribution','Sales Desk','sales@srs-distribution.com','(800) 555-0103','https://srsdistribution.com','Residential Roofing',0,1),
('Gulfeagle Supply','Territory Rep','contact@gulfeagle.com','(800) 555-0104','https://www.gulfeagle.com','Commercial Flat Roofing',0,1);

-- Materials: Commercial
INSERT INTO `materials` (`name`,`category`,`subcategory`,`roof_type`,`manufacturer`,`unit`,`unit_cost`,`coverage`,`waste_factor_pct`) VALUES
('TPO 60 mil White Membrane','Membrane','TPO','Commercial','Carlisle SynTec','SQ',95.00,100,10),
('EPDM 60 mil Black Membrane','Membrane','EPDM','Commercial','Firestone','SQ',80.00,100,10),
('PVC 60 mil White Membrane','Membrane','PVC','Commercial','Sika Sarnafil','SQ',110.00,100,10),
('Polyiso 2\" Insulation (4x8)','Insulation','Polyiso','Commercial','Atlas EPS','EA',28.50,32,5),
('1/2\" Cover Board (4x8)','Substrate','Cover Board','Commercial','Georgia-Pacific','EA',18.00,32,5),
('TPO Seam Tape 2\"','Accessories','Tape','Commercial','Carlisle','ROLL',22.00,100,2),
('Pipe Flashing Boot 3\"','Flashings','Pipe Boot','Both','Lifetime Tool','EA',12.50,1,0),
('Edge Metal Drip Edge','Flashings','Edge Metal','Both','Petersen Aluminum','LF',3.20,1,5),
('Roofing Fasteners 3\" (Box/250)','Fasteners','Screws','Commercial','OMG','BOX',45.00,250,0),
('Seam Roller 2\"','Tools','Roller','Commercial','Carlisle','EA',28.00,1,0),
('Walk Pad 3x5','Accessories','Walk Pads','Commercial','Carlisle','EA',32.00,15,0),
('Roof Drain 4" Aluminum','Drainage','Drains','Commercial','Zurn','EA',185.00,1,0),
('EPDM Bonding Adhesive','Accessories','Adhesive','Commercial','Firestone','GAL',45.00,60,5),
('EPDM Seam Tape 3"','Accessories','Tape','Commercial','Firestone','ROLL',35.00,100,5);

-- Materials: Residential
INSERT INTO `materials` (`name`,`category`,`subcategory`,`roof_type`,`manufacturer`,`unit`,`unit_cost`,`coverage`,`waste_factor_pct`) VALUES
('Architectural Shingles 30yr','Shingles','Asphalt','Residential','GAF Timberline HDZ','SQ',130.00,100,15),
('Starter Strip Shingles','Starter','Starter','Residential','GAF ProStart','ROLL',55.00,105,5),
('Synthetic Underlayment 10sq','Underlayment','Synthetic','Residential','GAF FeltBuster','ROLL',65.00,1000,10),
('Ice & Water Shield 2sq','Ice Shield','Self-Adhered','Residential','Grace Ice & Water','ROLL',88.00,200,5),
('Ridge Cap Shingles','Ridge','Ridge Cap','Residential','GAF TimberTex','BDL',75.00,33,5),
('Aluminum Drip Edge 1.5\" 10ft','Flashings','Drip Edge','Residential','Gibraltar','EA',4.50,10,10),
('Ridge Vent 10ft','Ventilation','Ridge Vent','Residential','Air Vent','EA',18.00,10,0),
('Roofing Nails 1.75\" (5lb)','Fasteners','Nails','Residential','Grip-Rite','BOX',18.00,1,0),
('Pipe Boot Flashing 2\"','Flashings','Pipe Boot','Residential','Lifetime Tool','EA',14.00,1,0),
('Step Flashing 4x4x7\" (25pk)','Flashings','Step','Residential','Amerimax','PK',28.00,25,0);

-- Default Settings
INSERT INTO `settings` (`key`,`value`,`label`,`group`,`type`) VALUES
('app_name','SHEKHAR ROOFIQ AI ENTERPRISE','Application Name','general','text'),
('company_name','Shekhar Building Materials','Company Name','general','text'),
('company_logo','','Company Logo URL','general','text'),
('google_maps_api_key','','Google Maps API Key','api_keys','password'),
('cesium_ion_token','','Cesium Ion Access Token','api_keys','password'),
('maptiler_api_key','','MapTiler API Key','api_keys','password'),
('eagleview_api_key','','EagleView API Key','api_keys','password'),
('nearmap_api_key','','Nearmap API Key','api_keys','password'),
('ai_service_url','http://localhost:5001','Python AI Service URL','api_keys','text'),
('openai_api_key','','OpenAI API Key (for AI Assistant)','api_keys','password'),
('waste_factor_residential','15','Default Waste Factor % (Residential)','defaults','number'),
('waste_factor_commercial','10','Default Waste Factor % (Commercial)','defaults','number'),
('default_markup_pct','20','Default Material Markup %','defaults','number'),
('currency_symbol','$','Currency Symbol','general','text'),
('date_format','m/d/Y','Date Format','general','text'),
('timezone','America/New_York','Timezone','general','text'),
('session_timeout','120','Session Timeout (minutes)','security','number'),
('admin_pin','1234','Settings Admin PIN','security','password'),
('reports_per_page','25','Records Per Page','general','number');
