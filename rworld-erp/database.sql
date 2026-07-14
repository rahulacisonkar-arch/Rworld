-- ============================================================
-- QuickBill POS System - Complete MySQL Database Schema
-- Version: 1.0
-- Compatible: MySQL 5.7 / MariaDB 10.x
-- Generated: Phase 1 - Database Foundation
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO';

-- ============================================================
-- CREATE DATABASE
-- ============================================================
CREATE DATABASE IF NOT EXISTS rworld_erp
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE rworld_erp;

-- ============================================================
-- DROP ALL TABLES (clean install order - child before parent)
-- ============================================================
DROP TABLE IF EXISTS import_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS eway_bills;
DROP TABLE IF EXISTS gst_returns;
DROP TABLE IF EXISTS loyalty_transactions;
DROP TABLE IF EXISTS promotion_items;
DROP TABLE IF EXISTS promotions;
DROP TABLE IF EXISTS shift_closure;
DROP TABLE IF EXISTS paytm_config;
DROP TABLE IF EXISTS google_drive_config;
DROP TABLE IF EXISTS serial_numbers;
DROP TABLE IF EXISTS batch_master;
DROP TABLE IF EXISTS stock_ledger;
DROP TABLE IF EXISTS grn_detail;
DROP TABLE IF EXISTS grn;
DROP TABLE IF EXISTS delivery_note_detail;
DROP TABLE IF EXISTS delivery_notes;
DROP TABLE IF EXISTS stock_journal_detail;
DROP TABLE IF EXISTS stock_journal;
DROP TABLE IF EXISTS transfer_in_detail;
DROP TABLE IF EXISTS transfer_in;
DROP TABLE IF EXISTS transfer_out_detail;
DROP TABLE IF EXISTS transfer_out;
DROP TABLE IF EXISTS quotation_detail;
DROP TABLE IF EXISTS quotations;
DROP TABLE IF EXISTS purchase_return_detail;
DROP TABLE IF EXISTS purchase_returns;
DROP TABLE IF EXISTS purchase_order_detail;
DROP TABLE IF EXISTS purchase_orders;
DROP TABLE IF EXISTS purchase_detail;
DROP TABLE IF EXISTS purchase_header;
DROP TABLE IF EXISTS sales_return_detail;
DROP TABLE IF EXISTS sales_returns;
DROP TABLE IF EXISTS sales_order_detail;
DROP TABLE IF EXISTS sales_orders;
DROP TABLE IF EXISTS sales_detail;
DROP TABLE IF EXISTS sales_header;
DROP TABLE IF EXISTS bank_transactions;
DROP TABLE IF EXISTS bank_accounts;
DROP TABLE IF EXISTS voucher_detail;
DROP TABLE IF EXISTS vouchers;
DROP TABLE IF EXISTS payments_made;
DROP TABLE IF EXISTS payments_received;
DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS expense_categories;
DROP TABLE IF EXISTS doc_numbering;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS price_levels;
DROP TABLE IF EXISTS tax_components;
DROP TABLE IF EXISTS tax_types;
DROP TABLE IF EXISTS reason_codes;
DROP TABLE IF EXISTS discount_codes;
DROP TABLE IF EXISTS sales_staff;
DROP TABLE IF EXISTS locations;
DROP TABLE IF EXISTS units;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS ledger_accounts;
DROP TABLE IF EXISTS ledger_groups;
DROP TABLE IF EXISTS payment_modes;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS branches;
DROP TABLE IF EXISTS companies;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SECTION 1: COMPANY & ORGANISATION
-- ============================================================

CREATE TABLE companies (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(200) NOT NULL,
    name_short    VARCHAR(50)  DEFAULT NULL,
    address1      VARCHAR(255) DEFAULT NULL,
    address2      VARCHAR(255) DEFAULT NULL,
    city          VARCHAR(100) DEFAULT NULL,
    state         VARCHAR(100) DEFAULT NULL,
    country       VARCHAR(100) DEFAULT 'India',
    pin           VARCHAR(20)  DEFAULT NULL,
    phone         VARCHAR(50)  DEFAULT NULL,
    email         VARCHAR(150) DEFAULT NULL,
    website       VARCHAR(150) DEFAULT NULL,
    gstin         VARCHAR(20)  DEFAULT NULL,
    pan           VARCHAR(20)  DEFAULT NULL,
    cin           VARCHAR(30)  DEFAULT NULL,
    logo_path     VARCHAR(255) DEFAULT NULL,
    currency      VARCHAR(10)  DEFAULT 'INR',
    currency_symbol VARCHAR(5) DEFAULT '₹',
    decimal_places TINYINT UNSIGNED DEFAULT 2,
    qty_decimal   TINYINT UNSIGNED DEFAULT 2,
    financial_year_start DATE DEFAULT NULL,
    tax_region    VARCHAR(20)  DEFAULT 'IN_GST'
                  COMMENT 'IN_GST|UAE_VAT|KE_VAT|GH_VAT|CUSTOM',
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Multi-company support — one DB per installation, one company typically';

-- ============================================================

CREATE TABLE branches (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id    INT UNSIGNED NOT NULL,
    code          VARCHAR(10)  NOT NULL
                  COMMENT '3-letter code matching original Warehouse naming (e.g. PHI, PRI, NEW)',
    name          VARCHAR(150) NOT NULL,
    address1      VARCHAR(255) DEFAULT NULL,
    address2      VARCHAR(255) DEFAULT NULL,
    city          VARCHAR(100) DEFAULT NULL,
    state         VARCHAR(100) DEFAULT NULL,
    pin           VARCHAR(20)  DEFAULT NULL,
    phone         VARCHAR(50)  DEFAULT NULL,
    email         VARCHAR(150) DEFAULT NULL,
    gstin         VARCHAR(20)  DEFAULT NULL
                  COMMENT 'Branch-specific GSTIN for inter-state transfers',
    is_warehouse  TINYINT(1)   NOT NULL DEFAULT 0,
    is_head_office TINYINT(1)  NOT NULL DEFAULT 0,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order    SMALLINT     NOT NULL DEFAULT 0,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_branch_code (company_id, code),
    KEY idx_branch_company (company_id),
    CONSTRAINT fk_branch_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE locations (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    branch_id     INT UNSIGNED NOT NULL,
    code          VARCHAR(20)  NOT NULL,
    name          VARCHAR(100) NOT NULL,
    description   VARCHAR(255) DEFAULT NULL,
    is_default    TINYINT(1)   NOT NULL DEFAULT 0,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_location (branch_id, code),
    CONSTRAINT fk_location_branch FOREIGN KEY (branch_id)
        REFERENCES branches(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Sub-location/bin/rack within a branch';

-- ============================================================
-- SECTION 2: ROLES & USERS
-- ============================================================

CREATE TABLE roles (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id    INT UNSIGNED NOT NULL,
    name          VARCHAR(80)  NOT NULL,
    description   VARCHAR(255) DEFAULT NULL,
    is_system     TINYINT(1)   NOT NULL DEFAULT 0
                  COMMENT '1=built-in role, cannot be deleted',
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_role_company (company_id),
    CONSTRAINT fk_role_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE permissions (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_id       INT UNSIGNED NOT NULL,
    module        VARCHAR(50)  NOT NULL
                  COMMENT 'e.g. sales, purchase, inventory, reports, settings',
    sub_module    VARCHAR(50)  DEFAULT NULL
                  COMMENT 'e.g. sales_order, sales_return',
    can_view      TINYINT(1)   NOT NULL DEFAULT 0,
    can_create    TINYINT(1)   NOT NULL DEFAULT 0,
    can_edit      TINYINT(1)   NOT NULL DEFAULT 0,
    can_delete    TINYINT(1)   NOT NULL DEFAULT 0,
    can_print     TINYINT(1)   NOT NULL DEFAULT 0,
    can_export    TINYINT(1)   NOT NULL DEFAULT 0,
    can_approve   TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_perm (role_id, module, sub_module),
    CONSTRAINT fk_perm_role FOREIGN KEY (role_id)
        REFERENCES roles(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE users (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED DEFAULT NULL
                    COMMENT 'NULL = access all branches',
    role_id         INT UNSIGNED NOT NULL,
    username        VARCHAR(50)  NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    name            VARCHAR(150) NOT NULL,
    email           VARCHAR(150) DEFAULT NULL,
    phone           VARCHAR(30)  DEFAULT NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    login_attempts  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until    DATETIME     DEFAULT NULL,
    last_login_at   DATETIME     DEFAULT NULL,
    last_login_ip   VARCHAR(45)  DEFAULT NULL,
    remember_token  VARCHAR(100) DEFAULT NULL,
    token_expires   DATETIME     DEFAULT NULL,
    must_change_pwd TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_username (company_id, username),
    KEY idx_user_branch (branch_id),
    KEY idx_user_role (role_id),
    CONSTRAINT fk_user_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_user_branch FOREIGN KEY (branch_id)
        REFERENCES branches(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_user_role FOREIGN KEY (role_id)
        REFERENCES roles(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECTION 3: MASTER DATA
-- ============================================================

CREATE TABLE price_levels (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id  INT UNSIGNED NOT NULL,
    level_no    TINYINT UNSIGNED NOT NULL COMMENT '1 to 5',
    name        VARCHAR(50)  NOT NULL,
    description VARCHAR(150) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_price_level (company_id, level_no),
    CONSTRAINT fk_pl_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Five price levels per item (Price1-Price5 from original grid)';

-- ============================================================

CREATE TABLE categories (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id  INT UNSIGNED NOT NULL,
    parent_id   INT UNSIGNED DEFAULT NULL,
    code        VARCHAR(30)  NOT NULL,
    name        VARCHAR(150) NOT NULL,
    level_no    TINYINT UNSIGNED NOT NULL DEFAULT 1
                COMMENT '1=Class1, 2=Class2, ... 5=Class5 — matches original 5-level classification',
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cat_code (company_id, code),
    KEY idx_cat_parent (parent_id),
    CONSTRAINT fk_cat_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_cat_parent FOREIGN KEY (parent_id)
        REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='5-level category hierarchy: Class1Guid..Class5Guid from original';

-- ============================================================

CREATE TABLE units (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    code            VARCHAR(20)  NOT NULL,
    name            VARCHAR(80)  NOT NULL,
    decimal_places  TINYINT UNSIGNED NOT NULL DEFAULT 2
                    COMMENT 'QtyDecimalPlace from original grid — 0=whole units, 2=kg etc',
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_unit_code (company_id, code),
    CONSTRAINT fk_unit_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TAX ENGINE — Multi-tier configurable (T1-T5 from original)
-- ============================================================

CREATE TABLE tax_types (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    code            VARCHAR(30)  NOT NULL,
    name            VARCHAR(100) NOT NULL,
    tax_region      VARCHAR(20)  NOT NULL DEFAULT 'IN_GST'
                    COMMENT 'IN_GST|UAE_VAT|KE_VAT|GH_VAT|CUSTOM',
    is_inclusive    TINYINT(1)   NOT NULL DEFAULT 0
                    COMMENT '1=tax included in price, 0=tax added on top',
    components_count TINYINT UNSIGNED NOT NULL DEFAULT 1
                    COMMENT 'Number of active tax components (1-5)',
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order      SMALLINT     NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tax_code (company_id, code),
    KEY idx_tax_company (company_id),
    CONSTRAINT fk_tax_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tax type master — maps to ItemPTaxType / ItemGstTypeGuid from original';

-- ============================================================

CREATE TABLE tax_components (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tax_type_id     INT UNSIGNED NOT NULL,
    component_no    TINYINT UNSIGNED NOT NULL COMMENT '1=T1, 2=T2, 3=T3, 4=T4, 5=T5',
    name            VARCHAR(80)  NOT NULL
                    COMMENT 'e.g. CGST, SGST, IGST, CESS, VAT',
    short_name      VARCHAR(20)  DEFAULT NULL,
    is_rate         TINYINT(1)   NOT NULL DEFAULT 1
                    COMMENT '1=percentage rate, 0=fixed amount (T1IsRateorAmount)',
    rate_or_amt     DECIMAL(12,4) NOT NULL DEFAULT 0.0000
                    COMMENT 'Percentage or fixed amount (T1RateorAmt)',
    applied_on      TINYINT UNSIGNED NOT NULL DEFAULT 0
                    COMMENT '0=gross value, 1=after T1, 2=after T2, 3=after T3, 4=after T4 (T1on)',
    slab_enabled    TINYINT(1)   NOT NULL DEFAULT 0,
    slab_start      DECIMAL(15,4) DEFAULT 0.0000 COMMENT 'T1StartValue',
    slab_end        DECIMAL(15,4) DEFAULT 0.0000 COMMENT 'T1EndValue — 0=no upper limit',
    account_id      INT UNSIGNED  DEFAULT NULL
                    COMMENT 'GL account for this tax component',
    sort_order      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tax_comp (tax_type_id, component_no),
    CONSTRAINT fk_tc_type FOREIGN KEY (tax_type_id)
        REFERENCES tax_types(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='T1-T5 tax components from original: rate, applied_on, slab ranges, cascade support';

-- ============================================================

CREATE TABLE customers (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    code            VARCHAR(30)  NOT NULL,
    name            VARCHAR(200) NOT NULL,
    alias           VARCHAR(100) DEFAULT NULL,
    address1        VARCHAR(255) DEFAULT NULL,
    address2        VARCHAR(255) DEFAULT NULL,
    city            VARCHAR(100) DEFAULT NULL,
    state           VARCHAR(100) DEFAULT NULL,
    state_code      VARCHAR(5)   DEFAULT NULL COMMENT 'For GST state code',
    country         VARCHAR(100) DEFAULT 'India',
    pin             VARCHAR(20)  DEFAULT NULL,
    phone1          VARCHAR(30)  DEFAULT NULL,
    phone2          VARCHAR(30)  DEFAULT NULL,
    email           VARCHAR(150) DEFAULT NULL,
    gstin           VARCHAR(20)  DEFAULT NULL,
    pan             VARCHAR(20)  DEFAULT NULL,
    aadhaar         VARCHAR(20)  DEFAULT NULL,
    credit_limit    DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    credit_days     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    price_level     TINYINT UNSIGNED NOT NULL DEFAULT 1
                    COMMENT 'Which of 5 price levels applies to this customer',
    loyalty_points  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    opening_balance DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    opening_bal_type ENUM('Dr','Cr') NOT NULL DEFAULT 'Dr',
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cust_code (company_id, code),
    KEY idx_cust_name (company_id, name),
    KEY idx_cust_phone (phone1),
    KEY idx_cust_gstin (gstin),
    CONSTRAINT fk_cust_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE suppliers (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    code            VARCHAR(30)  NOT NULL,
    name            VARCHAR(200) NOT NULL,
    alias           VARCHAR(100) DEFAULT NULL,
    address1        VARCHAR(255) DEFAULT NULL,
    address2        VARCHAR(255) DEFAULT NULL,
    city            VARCHAR(100) DEFAULT NULL,
    state           VARCHAR(100) DEFAULT NULL,
    state_code      VARCHAR(5)   DEFAULT NULL,
    country         VARCHAR(100) DEFAULT 'India',
    pin             VARCHAR(20)  DEFAULT NULL,
    phone1          VARCHAR(30)  DEFAULT NULL,
    phone2          VARCHAR(30)  DEFAULT NULL,
    email           VARCHAR(150) DEFAULT NULL,
    gstin           VARCHAR(20)  DEFAULT NULL,
    pan             VARCHAR(20)  DEFAULT NULL,
    credit_limit    DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    credit_days     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    opening_balance DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    opening_bal_type ENUM('Dr','Cr') NOT NULL DEFAULT 'Cr',
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_supp_code (company_id, code),
    KEY idx_supp_name (company_id, name),
    KEY idx_supp_gstin (gstin),
    CONSTRAINT fk_supp_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE sales_staff (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED DEFAULT NULL,
    code            VARCHAR(20)  NOT NULL,
    name            VARCHAR(150) NOT NULL,
    phone           VARCHAR(30)  DEFAULT NULL,
    email           VARCHAR(150) DEFAULT NULL,
    commission_rate DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_staff_code (company_id, code),
    CONSTRAINT fk_staff_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_staff_branch FOREIGN KEY (branch_id)
        REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SMGuid from original sales/purchase grids';

-- ============================================================

CREATE TABLE discount_codes (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    code            VARCHAR(30)  NOT NULL,
    name            VARCHAR(100) NOT NULL,
    disc_type       ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    disc_value      DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    min_qty         DECIMAL(12,4) DEFAULT NULL,
    min_value       DECIMAL(15,4) DEFAULT NULL,
    valid_from      DATE         DEFAULT NULL,
    valid_to        DATE         DEFAULT NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_disc_code (company_id, code),
    CONSTRAINT fk_disc_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='DiscCode from original sales detail grid';

-- ============================================================

CREATE TABLE reason_codes (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id  INT UNSIGNED NOT NULL,
    code        VARCHAR(20)  NOT NULL,
    reason      VARCHAR(200) NOT NULL,
    module      VARCHAR(30)  DEFAULT NULL
                COMMENT 'sales_return|purchase_return|stock_journal|delete|all',
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reason_code (company_id, code),
    CONSTRAINT fk_reason_company FOREIGN KEY (company_id)
        REFERENCES companies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ReasonCdGuid from original grids';

-- ============================================================
-- ITEM MASTER — Core of the inventory system
-- ============================================================

CREATE TABLE items (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id          INT UNSIGNED NOT NULL,
    stock_no            VARCHAR(50)  NOT NULL
                        COMMENT 'Stockno field from original — primary item identifier',
    description         VARCHAR(300) NOT NULL,
    alias               VARCHAR(200) DEFAULT NULL,
    barcode             VARCHAR(100) DEFAULT NULL,
    barcode_type        VARCHAR(20)  DEFAULT 'CODE128',

    -- Classification: 5 levels matching Class1Guid..Class5Guid
    cat1_id             INT UNSIGNED DEFAULT NULL COMMENT 'Level 1 category (Class1)',
    cat2_id             INT UNSIGNED DEFAULT NULL COMMENT 'Level 2 category (Class2)',
    cat3_id             INT UNSIGNED DEFAULT NULL COMMENT 'Level 3 category (Class3)',
    cat4_id             INT UNSIGNED DEFAULT NULL COMMENT 'Level 4 category (Class4)',
    cat5_id             INT UNSIGNED DEFAULT NULL COMMENT 'Level 5 category (Class5)',

    -- Units of Measure
    unit_id             INT UNSIGNED DEFAULT NULL COMMENT 'Primary UOM',
    alt_unit_id         INT UNSIGNED DEFAULT NULL COMMENT 'Alternate UOM (AltUOM field)',
    alt_unit_factor     DECIMAL(12,6) DEFAULT 1.000000
                        COMMENT 'Conversion: 1 alt_unit = alt_unit_factor primary units',

    -- Pricing: 5 price levels + MRP + cost
    price1              DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'ItemPrice1 — primary/default sell price',
    price2              DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'ItemPrice2',
    price3              DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'ItemPrice3',
    price4              DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'ItemPrice4',
    price5              DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'ItemPrice5',
    mrp                 DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'MRP field from original',
    cost_price          DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'Standard cost / purchase price',

    -- Tax
    tax_type_id         INT UNSIGNED DEFAULT NULL COMMENT 'ItemGstTypeGuid from original',

    -- HSN/SAC
    hsn_code            VARCHAR(20)  DEFAULT NULL,
    sac_code            VARCHAR(20)  DEFAULT NULL,

    -- Inventory settings
    maintain_inventory  TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'MaintainInv from original',
    reorder_level       DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    min_order_qty       DECIMAL(12,4) NOT NULL DEFAULT 0.0000 COMMENT 'MinTranQty from original',
    max_order_qty       DECIMAL(12,4) NOT NULL DEFAULT 0.0000,

    -- Tracking
    has_batch           TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Batch tracking',
    has_serial          TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Serial number tracking (Srlno)',
    track_expiry        TINYINT(1)   NOT NULL DEFAULT 0,
    expiry_alert_days   SMALLINT UNSIGNED NOT NULL DEFAULT 30,

    -- Misc
    image_path          VARCHAR(255) DEFAULT NULL,
    weight              DECIMAL(10,4) DEFAULT NULL,
    is_active           TINYINT(1)   NOT NULL DEFAULT 1,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_stock_no (company_id, stock_no),
    KEY idx_item_barcode (barcode),
    KEY idx_item_cat1 (cat1_id),
    KEY idx_item_tax (tax_type_id),
    FULLTEXT KEY ft_item_search (stock_no, description, alias, barcode),
    CONSTRAINT fk_item_company  FOREIGN KEY (company_id)  REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_item_cat1    FOREIGN KEY (cat1_id)     REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_item_cat2    FOREIGN KEY (cat2_id)     REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_item_cat3    FOREIGN KEY (cat3_id)     REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_item_cat4    FOREIGN KEY (cat4_id)     REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_item_cat5    FOREIGN KEY (cat5_id)     REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_item_unit    FOREIGN KEY (unit_id)     REFERENCES units(id) ON DELETE SET NULL,
    CONSTRAINT fk_item_altunit FOREIGN KEY (alt_unit_id) REFERENCES units(id) ON DELETE SET NULL,
    CONSTRAINT fk_item_tax     FOREIGN KEY (tax_type_id) REFERENCES tax_types(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Item/product master — reverse engineered from original sales/purchase grid columns';

-- ============================================================

CREATE TABLE batch_master (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id         INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED NOT NULL,
    batch_no        VARCHAR(50)  NOT NULL,
    mfg_date        DATE         DEFAULT NULL,
    exp_date        DATE         DEFAULT NULL,
    mrp             DECIMAL(15,4) DEFAULT NULL,
    cost_price      DECIMAL(15,4) DEFAULT NULL,
    qty_in          DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    qty_out         DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    balance_qty     DECIMAL(15,4) GENERATED ALWAYS AS (qty_in - qty_out) STORED,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_batch (item_id, branch_id, batch_no),
    KEY idx_batch_exp (exp_date),
    CONSTRAINT fk_batch_item   FOREIGN KEY (item_id)   REFERENCES items(id) ON DELETE RESTRICT,
    CONSTRAINT fk_batch_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE serial_numbers (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id         INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED NOT NULL,
    batch_id        INT UNSIGNED DEFAULT NULL,
    serial_no       VARCHAR(100) NOT NULL,
    status          ENUM('available','sold','returned','damaged') NOT NULL DEFAULT 'available',
    sold_in_doc     VARCHAR(30)  DEFAULT NULL COMMENT 'Invoice/doc number where sold',
    sold_at         DATETIME     DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_serial (item_id, serial_no),
    KEY idx_serial_status (status),
    CONSTRAINT fk_serial_item   FOREIGN KEY (item_id)   REFERENCES items(id) ON DELETE RESTRICT,
    CONSTRAINT fk_serial_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
    CONSTRAINT fk_serial_batch  FOREIGN KEY (batch_id)  REFERENCES batch_master(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- FINANCIAL ACCOUNTS
-- ============================================================

CREATE TABLE ledger_groups (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    parent_id       INT UNSIGNED DEFAULT NULL,
    code            VARCHAR(20)  NOT NULL,
    name            VARCHAR(150) NOT NULL,
    group_type      ENUM('asset','liability','income','expense','equity') NOT NULL DEFAULT 'asset',
    affects_cashflow TINYINT(1)  NOT NULL DEFAULT 0,
    sort_order      SMALLINT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lgrp_code (company_id, code),
    CONSTRAINT fk_lgrp_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_lgrp_parent  FOREIGN KEY (parent_id)  REFERENCES ledger_groups(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE ledger_accounts (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    group_id        INT UNSIGNED DEFAULT NULL,
    code            VARCHAR(20)  NOT NULL,
    name            VARCHAR(200) NOT NULL,
    is_customer     TINYINT(1)   NOT NULL DEFAULT 0,
    is_supplier     TINYINT(1)   NOT NULL DEFAULT 0,
    is_bank         TINYINT(1)   NOT NULL DEFAULT 0,
    is_cash         TINYINT(1)   NOT NULL DEFAULT 0,
    is_tax          TINYINT(1)   NOT NULL DEFAULT 0,
    opening_balance DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    opening_type    ENUM('Dr','Cr') NOT NULL DEFAULT 'Dr',
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ledger_code (company_id, code),
    KEY idx_ledger_group (group_id),
    CONSTRAINT fk_ledger_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ledger_group   FOREIGN KEY (group_id)   REFERENCES ledger_groups(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE payment_modes (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    name            VARCHAR(80)  NOT NULL,
    type            ENUM('cash','card','upi','cheque','bank_transfer','credit','other') NOT NULL DEFAULT 'cash',
    ledger_id       INT UNSIGNED DEFAULT NULL COMMENT 'GL account to post this payment to',
    is_default      TINYINT(1)   NOT NULL DEFAULT 0,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order      SMALLINT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    CONSTRAINT fk_pmode_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pmode_ledger  FOREIGN KEY (ledger_id)  REFERENCES ledger_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DOCUMENT NUMBERING
-- ============================================================

CREATE TABLE doc_numbering (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED DEFAULT NULL,
    doc_type        VARCHAR(30)  NOT NULL
                    COMMENT 'SALE|PURCHASE|SALE_RETURN|PUR_RETURN|SO|PO|QUOT|TRANSFER|VOUCHER|EXPENSE',
    prefix          VARCHAR(20)  DEFAULT '',
    suffix          VARCHAR(20)  DEFAULT '',
    current_no      INT UNSIGNED NOT NULL DEFAULT 0,
    min_digits      TINYINT UNSIGNED NOT NULL DEFAULT 6,
    reset_yearly    TINYINT(1)   NOT NULL DEFAULT 1,
    last_reset_year YEAR         DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_docnum (company_id, branch_id, doc_type),
    CONSTRAINT fk_docnum_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_docnum_branch  FOREIGN KEY (branch_id)  REFERENCES branches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECTION 4: TRANSACTIONS — STOCK LEDGER
-- ============================================================

CREATE TABLE stock_ledger (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED NOT NULL,
    item_id         INT UNSIGNED NOT NULL,
    location_id     INT UNSIGNED DEFAULT NULL,
    batch_id        INT UNSIGNED DEFAULT NULL,
    txn_date        DATE         NOT NULL,
    txn_type        VARCHAR(30)  NOT NULL
                    COMMENT 'PURCHASE|SALE|SALE_RTN|PUR_RTN|TRANSFER_IN|TRANSFER_OUT|ADJ_IN|ADJ_OUT|OPENING',
    doc_no          VARCHAR(30)  DEFAULT NULL,
    ref_id          INT UNSIGNED DEFAULT NULL COMMENT 'FK to the source document header id',
    ref_detail_id   INT UNSIGNED DEFAULT NULL COMMENT 'FK to the source document detail id',
    qty_in          DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    qty_out         DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    rate            DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    value           DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    balance_qty     DECIMAL(15,4) NOT NULL DEFAULT 0.0000
                    COMMENT 'Running balance after this transaction',
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sl_item    (item_id),
    KEY idx_sl_branch  (branch_id),
    KEY idx_sl_date    (txn_date),
    KEY idx_sl_doc     (doc_no),
    KEY idx_sl_type    (txn_type),
    CONSTRAINT fk_sl_company  FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sl_branch   FOREIGN KEY (branch_id)  REFERENCES branches(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sl_item     FOREIGN KEY (item_id)    REFERENCES items(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sl_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
    CONSTRAINT fk_sl_batch    FOREIGN KEY (batch_id)   REFERENCES batch_master(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Running stock ledger — every inventory movement creates a row here';

-- ============================================================
-- SECTION 5: SALES TRANSACTIONS
-- ============================================================

CREATE TABLE sales_header (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id          INT UNSIGNED NOT NULL,
    branch_id           INT UNSIGNED NOT NULL,
    doc_no              VARCHAR(30)  NOT NULL,
    doc_date            DATE         NOT NULL,
    doc_time            TIME         DEFAULT NULL,
    customer_id         INT UNSIGNED DEFAULT NULL,
    customer_name       VARCHAR(200) DEFAULT NULL COMMENT 'Snapshot at time of billing',
    customer_gstin      VARCHAR(20)  DEFAULT NULL COMMENT 'Snapshot',
    customer_state_code VARCHAR(5)   DEFAULT NULL,
    ref_no              VARCHAR(50)  DEFAULT NULL,
    sales_staff_id      INT UNSIGNED DEFAULT NULL,
    payment_mode_id     INT UNSIGNED DEFAULT NULL,

    -- Header-level tax destination
    dest_tax_type       VARCHAR(10)  DEFAULT NULL
                        COMMENT 'CGST_SGST|IGST|EXEMPT (DestTaxtype from original)',

    -- Amounts — all populated by TaxEngine::calculate()
    gross_amount        DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    bill_disc_perc      DECIMAL(8,4)  NOT NULL DEFAULT 0.0000,
    bill_disc_amount    DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    addon_bef_tax       DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'AddonBefTaxAmount',
    dedn_bef_tax        DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'DednBefTaxAmt',
    taxable_amount      DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    tax1_amount         DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    tax2_amount         DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    tax3_amount         DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    tax4_amount         DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    tax5_amount         DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    total_tax           DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    addon_aft_tax       DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'AddonAftTaxAmount',
    dedn_aft_tax        DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'DednAftTaxAmt',
    round_off           DECIMAL(8,4)  NOT NULL DEFAULT 0.0000,
    net_amount          DECIMAL(18,4) NOT NULL DEFAULT 0.0000,

    -- Payment
    paid_amount         DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    change_amount       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    balance_due         DECIMAL(15,4) NOT NULL DEFAULT 0.0000,

    -- Status
    status              ENUM('draft','confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
    cancel_reason       VARCHAR(255)  DEFAULT NULL,
    is_return_processed TINYINT(1)   NOT NULL DEFAULT 0,

    -- E-Way Bill
    eway_bill_no        VARCHAR(30)  DEFAULT NULL,

    remarks             TEXT         DEFAULT NULL,
    created_by          INT UNSIGNED DEFAULT NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_sale_doc (company_id, branch_id, doc_no),
    KEY idx_sale_date       (doc_date),
    KEY idx_sale_customer   (customer_id),
    KEY idx_sale_branch     (branch_id),
    KEY idx_sale_status     (status),
    CONSTRAINT fk_sh_company  FOREIGN KEY (company_id)      REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sh_branch   FOREIGN KEY (branch_id)       REFERENCES branches(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sh_customer FOREIGN KEY (customer_id)     REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_sh_staff    FOREIGN KEY (sales_staff_id)  REFERENCES sales_staff(id) ON DELETE SET NULL,
    CONSTRAINT fk_sh_paymode  FOREIGN KEY (payment_mode_id) REFERENCES payment_modes(id) ON DELETE SET NULL,
    CONSTRAINT fk_sh_user     FOREIGN KEY (created_by)      REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE sales_detail (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    header_id           INT UNSIGNED NOT NULL,
    srl_no              SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Line serial number (Srlno)',
    item_id             INT UNSIGNED NOT NULL,
    item_snapshot       VARCHAR(300) DEFAULT NULL COMMENT 'Item description at time of sale',
    batch_id            INT UNSIGNED DEFAULT NULL,
    location_id         INT UNSIGNED DEFAULT NULL,
    serial_no           VARCHAR(100) DEFAULT NULL,

    -- Quantity
    qty                 DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'ItemQty',
    unit_id             INT UNSIGNED DEFAULT NULL,
    alt_unit_id         INT UNSIGNED DEFAULT NULL,
    alt_qty             DECIMAL(15,4) DEFAULT NULL COMMENT 'AltUOM quantity',
    doc_qty             DECIMAL(15,4) DEFAULT NULL COMMENT 'DocQty — original transaction qty',

    -- Pricing
    rate                DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'ItemRate',
    mrp                 DECIMAL(15,4) DEFAULT NULL COMMENT 'MRP at time of sale',
    value               DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT 'ItemValue = qty * rate',

    -- Discount
    disc_code           VARCHAR(30)  DEFAULT NULL COMMENT 'DiscCode',
    free_qty            DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'Freeqty',
    disc_perc           DECIMAL(8,4)  NOT NULL DEFAULT 0.0000 COMMENT 'DiscPerc',
    disc_amount         DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'DiscAmt',
    bill_disc_alloc     DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'BillDiscApprAmt — header disc allocated to this line',

    -- Values
    net_value           DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT 'NetValue = value - disc',
    price_factor_amt    DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'PriceFactorAmt',
    total_discount      DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'TotalDiscount',

    -- Tax (T1-T5 per line — reverse engineered from original 100-column grid)
    tax_type_id         INT UNSIGNED DEFAULT NULL,
    tax_inclusive       TINYINT(1)  NOT NULL DEFAULT 0 COMMENT 'ItemTaxInclusive',
    dest_tax_type       VARCHAR(10)  DEFAULT NULL COMMENT 'DestTaxtype',
    total_tax_perc      DECIMAL(8,4) NOT NULL DEFAULT 0.0000 COMMENT 'TotalTaxPerc',
    tax_comp_count      TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TaxCompCount',

    -- T1
    t1_contribution     DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'T1Contribution',
    t1_applied_on       TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'T1on',
    t1_is_rate          TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'T1IsRateorAmount: 1=rate, 0=amount',
    t1_rate_or_amt      DECIMAL(12,4) NOT NULL DEFAULT 0.0000 COMMENT 'T1RateorAmt',
    t1_start_value      DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'T1StartValue',
    t1_end_value        DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'T1EndValue',
    t1_amount           DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'Tax1Amount',

    -- T2
    t2_contribution     DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t2_applied_on       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    t2_is_rate          TINYINT(1)   NOT NULL DEFAULT 1,
    t2_rate_or_amt      DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    t2_start_value      DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t2_end_value        DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t2_amount           DECIMAL(15,4) NOT NULL DEFAULT 0.0000,

    -- T3
    t3_contribution     DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t3_applied_on       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    t3_is_rate          TINYINT(1)   NOT NULL DEFAULT 1,
    t3_rate_or_amt      DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    t3_start_value      DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t3_end_value        DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t3_amount           DECIMAL(15,4) NOT NULL DEFAULT 0.0000,

    -- T4
    t4_contribution     DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t4_applied_on       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    t4_is_rate          TINYINT(1)   NOT NULL DEFAULT 1,
    t4_rate_or_amt      DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    t4_start_value      DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t4_end_value        DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t4_amount           DECIMAL(15,4) NOT NULL DEFAULT 0.0000,

    -- T5
    t5_contribution     DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t5_applied_on       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    t5_is_rate          TINYINT(1)   NOT NULL DEFAULT 1,
    t5_rate_or_amt      DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    t5_start_value      DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t5_end_value        DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t5_amount           DECIMAL(15,4) NOT NULL DEFAULT 0.0000,

    -- Stock tracking
    stock_qty_in        DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'StockQtyIn',
    stock_qty_out       DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'StockQtyOut',
    return_flag         TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'ReturnFlag',

    -- References (for SO→Sale linkage)
    ref_doc_no          VARCHAR(30)  DEFAULT NULL,
    ref_detail_id       INT UNSIGNED DEFAULT NULL,
    ref_qty_in          DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    ref_qty_out         DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    prev_disc_perc      DECIMAL(8,4)  NOT NULL DEFAULT 0.0000,

    PRIMARY KEY (id),
    KEY idx_sd_header  (header_id),
    KEY idx_sd_item    (item_id),
    KEY idx_sd_batch   (batch_id),
    CONSTRAINT fk_sd_header   FOREIGN KEY (header_id)   REFERENCES sales_header(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_sd_item     FOREIGN KEY (item_id)     REFERENCES items(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sd_batch    FOREIGN KEY (batch_id)    REFERENCES batch_master(id) ON DELETE SET NULL,
    CONSTRAINT fk_sd_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
    CONSTRAINT fk_sd_unit     FOREIGN KEY (unit_id)     REFERENCES units(id) ON DELETE SET NULL,
    CONSTRAINT fk_sd_altunit  FOREIGN KEY (alt_unit_id) REFERENCES units(id) ON DELETE SET NULL,
    CONSTRAINT fk_sd_taxtype  FOREIGN KEY (tax_type_id) REFERENCES tax_types(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Sales line items — full T1-T5 tax columns matching original 100-column grid';

-- ============================================================

CREATE TABLE sales_orders (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED NOT NULL,
    doc_no          VARCHAR(30)  NOT NULL,
    doc_date        DATE         NOT NULL,
    valid_till      DATE         DEFAULT NULL,
    customer_id     INT UNSIGNED DEFAULT NULL,
    customer_name   VARCHAR(200) DEFAULT NULL,
    ref_no          VARCHAR(50)  DEFAULT NULL,
    sales_staff_id  INT UNSIGNED DEFAULT NULL,
    gross_amount    DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    net_amount      DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    status          ENUM('open','partial','closed','cancelled') NOT NULL DEFAULT 'open',
    remarks         TEXT DEFAULT NULL,
    created_by      INT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_so_doc (company_id, branch_id, doc_no),
    KEY idx_so_customer (customer_id),
    KEY idx_so_date (doc_date),
    CONSTRAINT fk_so_company  FOREIGN KEY (company_id)  REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_so_branch   FOREIGN KEY (branch_id)   REFERENCES branches(id) ON DELETE RESTRICT,
    CONSTRAINT fk_so_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sales_order_detail (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    header_id       INT UNSIGNED NOT NULL,
    srl_no          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    item_id         INT UNSIGNED NOT NULL,
    qty             DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    delivered_qty   DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    unit_id         INT UNSIGNED DEFAULT NULL,
    rate            DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    value           DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    disc_perc       DECIMAL(8,4)  NOT NULL DEFAULT 0.0000,
    disc_amount     DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    net_value       DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    tax_type_id     INT UNSIGNED DEFAULT NULL,
    total_tax       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    PRIMARY KEY (id),
    KEY idx_sod_header (header_id),
    CONSTRAINT fk_sod_header FOREIGN KEY (header_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_sod_item   FOREIGN KEY (item_id)   REFERENCES items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE quotations (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED NOT NULL,
    doc_no          VARCHAR(30)  NOT NULL,
    doc_date        DATE         NOT NULL,
    valid_till      DATE         DEFAULT NULL,
    customer_id     INT UNSIGNED DEFAULT NULL,
    customer_name   VARCHAR(200) DEFAULT NULL,
    ref_no          VARCHAR(50)  DEFAULT NULL,
    gross_amount    DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    total_tax       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    net_amount      DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    status          ENUM('open','converted','expired','cancelled') NOT NULL DEFAULT 'open',
    remarks         TEXT DEFAULT NULL,
    created_by      INT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_quot_doc (company_id, branch_id, doc_no),
    CONSTRAINT fk_quot_company  FOREIGN KEY (company_id)  REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_quot_branch   FOREIGN KEY (branch_id)   REFERENCES branches(id) ON DELETE RESTRICT,
    CONSTRAINT fk_quot_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quotation_detail (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    header_id       INT UNSIGNED NOT NULL,
    srl_no          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    item_id         INT UNSIGNED NOT NULL,
    qty             DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    unit_id         INT UNSIGNED DEFAULT NULL,
    rate            DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    value           DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    disc_perc       DECIMAL(8,4)  NOT NULL DEFAULT 0.0000,
    disc_amount     DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    net_value       DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    tax_type_id     INT UNSIGNED DEFAULT NULL,
    t1_amount       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t2_amount       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t3_amount       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t4_amount       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t5_amount       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    PRIMARY KEY (id),
    CONSTRAINT fk_qd_header FOREIGN KEY (header_id) REFERENCES quotations(id) ON DELETE CASCADE,
    CONSTRAINT fk_qd_item   FOREIGN KEY (item_id)   REFERENCES items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE sales_returns (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED NOT NULL,
    doc_no          VARCHAR(30)  NOT NULL,
    doc_date        DATE         NOT NULL,
    customer_id     INT UNSIGNED DEFAULT NULL,
    customer_name   VARCHAR(200) DEFAULT NULL,
    orig_sale_id    INT UNSIGNED DEFAULT NULL COMMENT 'Original invoice — NULL = freehand return',
    orig_doc_no     VARCHAR(30)  DEFAULT NULL,
    reason_id       INT UNSIGNED DEFAULT NULL,
    gross_amount    DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    total_tax       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    net_amount      DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    round_off       DECIMAL(8,4)  NOT NULL DEFAULT 0.0000,
    refund_mode_id  INT UNSIGNED DEFAULT NULL,
    status          ENUM('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
    remarks         TEXT DEFAULT NULL,
    created_by      INT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_srtn_doc (company_id, branch_id, doc_no),
    KEY idx_srtn_orig (orig_sale_id),
    CONSTRAINT fk_srtn_company  FOREIGN KEY (company_id)   REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_srtn_branch   FOREIGN KEY (branch_id)    REFERENCES branches(id) ON DELETE RESTRICT,
    CONSTRAINT fk_srtn_customer FOREIGN KEY (customer_id)  REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_srtn_orig     FOREIGN KEY (orig_sale_id) REFERENCES sales_header(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sales_return_detail (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    header_id       INT UNSIGNED NOT NULL,
    srl_no          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    item_id         INT UNSIGNED NOT NULL,
    batch_id        INT UNSIGNED DEFAULT NULL,
    qty             DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    unit_id         INT UNSIGNED DEFAULT NULL,
    rate            DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    value           DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    disc_amount     DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    net_value       DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    t1_amount       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t2_amount       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t3_amount       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t4_amount       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t5_amount       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    orig_detail_id  INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_srd_header FOREIGN KEY (header_id) REFERENCES sales_returns(id) ON DELETE CASCADE,
    CONSTRAINT fk_srd_item   FOREIGN KEY (item_id)   REFERENCES items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECTION 6: PURCHASE TRANSACTIONS
-- ============================================================

CREATE TABLE purchase_header (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id          INT UNSIGNED NOT NULL,
    branch_id           INT UNSIGNED NOT NULL,
    doc_no              VARCHAR(30)  NOT NULL COMMENT 'Our internal GRN/purchase number',
    doc_date            DATE         NOT NULL,
    supplier_id         INT UNSIGNED DEFAULT NULL,
    supplier_name       VARCHAR(200) DEFAULT NULL COMMENT 'Snapshot',
    supplier_gstin      VARCHAR(20)  DEFAULT NULL COMMENT 'Snapshot',
    supplier_inv_no     VARCHAR(50)  DEFAULT NULL COMMENT 'Supplier invoice number',
    supplier_inv_date   DATE         DEFAULT NULL,
    ref_no              VARCHAR(50)  DEFAULT NULL,
    dest_tax_type       VARCHAR(10)  DEFAULT NULL,
    gross_amount        DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    bill_disc_amount    DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    addon_bef_tax       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    dedn_bef_tax        DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    taxable_amount      DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    tax1_amount         DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    tax2_amount         DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    tax3_amount         DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    tax4_amount         DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    tax5_amount         DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    total_tax           DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    addon_aft_tax       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    dedn_aft_tax        DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    round_off           DECIMAL(8,4)  NOT NULL DEFAULT 0.0000,
    net_amount          DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    paid_amount         DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    balance_due         DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    status              ENUM('draft','confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
    cancel_reason       VARCHAR(255)  DEFAULT NULL,
    remarks             TEXT         DEFAULT NULL,
    created_by          INT UNSIGNED DEFAULT NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pur_doc (company_id, branch_id, doc_no),
    KEY idx_pur_date     (doc_date),
    KEY idx_pur_supplier (supplier_id),
    KEY idx_pur_branch   (branch_id),
    CONSTRAINT fk_ph_company  FOREIGN KEY (company_id)  REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ph_branch   FOREIGN KEY (branch_id)   REFERENCES branches(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ph_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_detail (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    header_id       INT UNSIGNED NOT NULL,
    srl_no          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    item_id         INT UNSIGNED NOT NULL,
    item_snapshot   VARCHAR(300) DEFAULT NULL,
    batch_id        INT UNSIGNED DEFAULT NULL,
    location_id     INT UNSIGNED DEFAULT NULL,
    qty             DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    unit_id         INT UNSIGNED DEFAULT NULL,
    alt_unit_id     INT UNSIGNED DEFAULT NULL,
    rate            DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    mrp             DECIMAL(15,4) DEFAULT NULL,
    value           DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    free_qty        DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    disc_perc       DECIMAL(8,4)  NOT NULL DEFAULT 0.0000,
    disc_amount     DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    net_value       DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    tax_type_id     INT UNSIGNED DEFAULT NULL,
    tax_inclusive   TINYINT(1)   NOT NULL DEFAULT 0,
    t1_contribution DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t1_applied_on   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    t1_is_rate      TINYINT(1)   NOT NULL DEFAULT 1,
    t1_rate_or_amt  DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    t1_amount       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t2_contribution DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t2_applied_on   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    t2_is_rate      TINYINT(1)   NOT NULL DEFAULT 1,
    t2_rate_or_amt  DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    t2_amount       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t3_contribution DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t3_applied_on   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    t3_is_rate      TINYINT(1)   NOT NULL DEFAULT 1,
    t3_rate_or_amt  DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    t3_amount       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t4_contribution DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t4_applied_on   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    t4_is_rate      TINYINT(1)   NOT NULL DEFAULT 1,
    t4_rate_or_amt  DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    t4_amount       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t5_contribution DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t5_applied_on   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    t5_is_rate      TINYINT(1)   NOT NULL DEFAULT 1,
    t5_rate_or_amt  DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    t5_amount       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    total_tax_perc  DECIMAL(8,4)  NOT NULL DEFAULT 0.0000,
    ref_po_detail   INT UNSIGNED DEFAULT NULL COMMENT 'PO line this was received against',
    PRIMARY KEY (id),
    KEY idx_pd_header (header_id),
    KEY idx_pd_item   (item_id),
    CONSTRAINT fk_pd_header  FOREIGN KEY (header_id) REFERENCES purchase_header(id) ON DELETE CASCADE,
    CONSTRAINT fk_pd_item    FOREIGN KEY (item_id)   REFERENCES items(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pd_taxtype FOREIGN KEY (tax_type_id) REFERENCES tax_types(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE purchase_orders (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED NOT NULL,
    doc_no          VARCHAR(30)  NOT NULL,
    doc_date        DATE         NOT NULL,
    valid_till      DATE         DEFAULT NULL,
    supplier_id     INT UNSIGNED DEFAULT NULL,
    supplier_name   VARCHAR(200) DEFAULT NULL,
    net_amount      DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    status          ENUM('open','partial','closed','cancelled') NOT NULL DEFAULT 'open',
    remarks         TEXT DEFAULT NULL,
    created_by      INT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_po_doc (company_id, branch_id, doc_no),
    CONSTRAINT fk_po_company  FOREIGN KEY (company_id)  REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_po_branch   FOREIGN KEY (branch_id)   REFERENCES branches(id) ON DELETE RESTRICT,
    CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_order_detail (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    header_id       INT UNSIGNED NOT NULL,
    srl_no          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    item_id         INT UNSIGNED NOT NULL,
    qty             DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    received_qty    DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    unit_id         INT UNSIGNED DEFAULT NULL,
    rate            DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    value           DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    tax_type_id     INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_pod_header FOREIGN KEY (header_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_pod_item   FOREIGN KEY (item_id)   REFERENCES items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE purchase_returns (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED NOT NULL,
    doc_no          VARCHAR(30)  NOT NULL,
    doc_date        DATE         NOT NULL,
    supplier_id     INT UNSIGNED DEFAULT NULL,
    supplier_name   VARCHAR(200) DEFAULT NULL,
    orig_purchase_id INT UNSIGNED DEFAULT NULL,
    orig_doc_no     VARCHAR(30)  DEFAULT NULL,
    reason_id       INT UNSIGNED DEFAULT NULL,
    gross_amount    DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    total_tax       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    net_amount      DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    round_off       DECIMAL(8,4)  NOT NULL DEFAULT 0.0000,
    status          ENUM('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
    remarks         TEXT DEFAULT NULL,
    created_by      INT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_prtn_doc (company_id, branch_id, doc_no),
    CONSTRAINT fk_prtn_company  FOREIGN KEY (company_id)      REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_prtn_branch   FOREIGN KEY (branch_id)       REFERENCES branches(id) ON DELETE RESTRICT,
    CONSTRAINT fk_prtn_supplier FOREIGN KEY (supplier_id)     REFERENCES suppliers(id) ON DELETE SET NULL,
    CONSTRAINT fk_prtn_orig     FOREIGN KEY (orig_purchase_id) REFERENCES purchase_header(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_return_detail (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    header_id       INT UNSIGNED NOT NULL,
    srl_no          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    item_id         INT UNSIGNED NOT NULL,
    batch_id        INT UNSIGNED DEFAULT NULL,
    qty             DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    unit_id         INT UNSIGNED DEFAULT NULL,
    rate            DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    value           DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    disc_amount     DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    net_value       DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    t1_amount       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t2_amount       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t3_amount       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t4_amount       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    t5_amount       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    orig_detail_id  INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_prd_header FOREIGN KEY (header_id) REFERENCES purchase_returns(id) ON DELETE CASCADE,
    CONSTRAINT fk_prd_item   FOREIGN KEY (item_id)   REFERENCES items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECTION 7: INVENTORY MOVEMENTS
-- ============================================================

CREATE TABLE transfer_out (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    from_branch_id  INT UNSIGNED NOT NULL,
    to_branch_id    INT UNSIGNED NOT NULL,
    doc_no          VARCHAR(30)  NOT NULL,
    doc_date        DATE         NOT NULL,
    net_amount      DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    status          ENUM('open','partial','closed','cancelled') NOT NULL DEFAULT 'open',
    remarks         TEXT DEFAULT NULL,
    created_by      INT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_to_doc (company_id, from_branch_id, doc_no),
    CONSTRAINT fk_to_company FOREIGN KEY (company_id)    REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_to_from    FOREIGN KEY (from_branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
    CONSTRAINT fk_to_to      FOREIGN KEY (to_branch_id)   REFERENCES branches(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transfer_out_detail (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    header_id       INT UNSIGNED NOT NULL,
    srl_no          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    item_id         INT UNSIGNED NOT NULL,
    batch_id        INT UNSIGNED DEFAULT NULL,
    location_id     INT UNSIGNED DEFAULT NULL,
    qty             DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    received_qty    DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    unit_id         INT UNSIGNED DEFAULT NULL,
    rate            DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    value           DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    PRIMARY KEY (id),
    CONSTRAINT fk_tod_header FOREIGN KEY (header_id) REFERENCES transfer_out(id) ON DELETE CASCADE,
    CONSTRAINT fk_tod_item   FOREIGN KEY (item_id)   REFERENCES items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transfer_in (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    from_branch_id  INT UNSIGNED NOT NULL,
    to_branch_id    INT UNSIGNED NOT NULL,
    doc_no          VARCHAR(30)  NOT NULL,
    doc_date        DATE         NOT NULL,
    transfer_out_id INT UNSIGNED DEFAULT NULL,
    transfer_out_doc VARCHAR(30) DEFAULT NULL,
    net_amount      DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    status          ENUM('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
    remarks         TEXT DEFAULT NULL,
    created_by      INT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ti_doc (company_id, to_branch_id, doc_no),
    CONSTRAINT fk_ti_company     FOREIGN KEY (company_id)    REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ti_transfer_out FOREIGN KEY (transfer_out_id) REFERENCES transfer_out(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transfer_in_detail (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    header_id           INT UNSIGNED NOT NULL,
    srl_no              SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    transfer_out_det_id INT UNSIGNED DEFAULT NULL,
    item_id             INT UNSIGNED NOT NULL,
    batch_id            INT UNSIGNED DEFAULT NULL,
    location_id         INT UNSIGNED DEFAULT NULL,
    received_qty        DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    unit_id             INT UNSIGNED DEFAULT NULL,
    rate                DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    value               DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    PRIMARY KEY (id),
    CONSTRAINT fk_tid_header FOREIGN KEY (header_id) REFERENCES transfer_in(id) ON DELETE CASCADE,
    CONSTRAINT fk_tid_item   FOREIGN KEY (item_id)   REFERENCES items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE stock_journal (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED NOT NULL,
    doc_no          VARCHAR(30)  NOT NULL,
    doc_date        DATE         NOT NULL,
    reason_id       INT UNSIGNED DEFAULT NULL,
    narration       TEXT DEFAULT NULL,
    total_value     DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    status          ENUM('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
    created_by      INT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sj_doc (company_id, branch_id, doc_no),
    CONSTRAINT fk_sj_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sj_branch  FOREIGN KEY (branch_id)  REFERENCES branches(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sj_reason  FOREIGN KEY (reason_id)  REFERENCES reason_codes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_journal_detail (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    header_id       INT UNSIGNED NOT NULL,
    srl_no          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    item_id         INT UNSIGNED NOT NULL,
    batch_id        INT UNSIGNED DEFAULT NULL,
    location_id     INT UNSIGNED DEFAULT NULL,
    qty_in          DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    qty_out         DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    unit_id         INT UNSIGNED DEFAULT NULL,
    rate            DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    value           DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    PRIMARY KEY (id),
    CONSTRAINT fk_sjd_header FOREIGN KEY (header_id) REFERENCES stock_journal(id) ON DELETE CASCADE,
    CONSTRAINT fk_sjd_item   FOREIGN KEY (item_id)   REFERENCES items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE delivery_notes (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED NOT NULL,
    doc_no          VARCHAR(30)  NOT NULL,
    doc_date        DATE         NOT NULL,
    customer_id     INT UNSIGNED DEFAULT NULL,
    sales_id        INT UNSIGNED DEFAULT NULL,
    status          ENUM('draft','delivered','cancelled') NOT NULL DEFAULT 'draft',
    created_by      INT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dn_doc (company_id, branch_id, doc_no),
    CONSTRAINT fk_dn_sales FOREIGN KEY (sales_id) REFERENCES sales_header(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE delivery_note_detail (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    header_id   INT UNSIGNED NOT NULL,
    item_id     INT UNSIGNED NOT NULL,
    qty         DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    location_id INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_dnd_header FOREIGN KEY (header_id) REFERENCES delivery_notes(id) ON DELETE CASCADE,
    CONSTRAINT fk_dnd_item   FOREIGN KEY (item_id)   REFERENCES items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE grn (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED NOT NULL,
    doc_no          VARCHAR(30)  NOT NULL,
    doc_date        DATE         NOT NULL,
    supplier_id     INT UNSIGNED DEFAULT NULL,
    purchase_id     INT UNSIGNED DEFAULT NULL,
    po_id           INT UNSIGNED DEFAULT NULL,
    status          ENUM('open','posted','cancelled') NOT NULL DEFAULT 'open',
    created_by      INT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_grn_doc (company_id, branch_id, doc_no),
    CONSTRAINT fk_grn_company  FOREIGN KEY (company_id)  REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_grn_purchase FOREIGN KEY (purchase_id) REFERENCES purchase_header(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE grn_detail (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    header_id       INT UNSIGNED NOT NULL,
    item_id         INT UNSIGNED NOT NULL,
    received_qty    DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    location_id     INT UNSIGNED DEFAULT NULL,
    batch_id        INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_grnd_header FOREIGN KEY (header_id) REFERENCES grn(id) ON DELETE CASCADE,
    CONSTRAINT fk_grnd_item   FOREIGN KEY (item_id)   REFERENCES items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECTION 8: FINANCIAL TRANSACTIONS
-- ============================================================

CREATE TABLE vouchers (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED NOT NULL,
    doc_no          VARCHAR(30)  NOT NULL,
    doc_date        DATE         NOT NULL,
    voucher_type    ENUM('receipt','payment','journal','contra') NOT NULL,
    narration       TEXT DEFAULT NULL,
    total_amount    DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    status          ENUM('draft','confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
    created_by      INT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vch_doc (company_id, branch_id, doc_no),
    CONSTRAINT fk_vch_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_vch_branch  FOREIGN KEY (branch_id)  REFERENCES branches(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE voucher_detail (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    voucher_id  INT UNSIGNED NOT NULL,
    ledger_id   INT UNSIGNED NOT NULL,
    debit       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    credit      DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    narration   VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_vd_voucher FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE CASCADE,
    CONSTRAINT fk_vd_ledger  FOREIGN KEY (ledger_id)  REFERENCES ledger_accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE payments_received (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED NOT NULL,
    doc_no          VARCHAR(30)  NOT NULL,
    doc_date        DATE         NOT NULL,
    customer_id     INT UNSIGNED DEFAULT NULL,
    amount          DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    payment_mode_id INT UNSIGNED DEFAULT NULL,
    cheque_no       VARCHAR(30)  DEFAULT NULL,
    cheque_date     DATE         DEFAULT NULL,
    bank_name       VARCHAR(100) DEFAULT NULL,
    reference       VARCHAR(100) DEFAULT NULL,
    against_sale_id INT UNSIGNED DEFAULT NULL,
    narration       TEXT DEFAULT NULL,
    created_by      INT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pr_doc (company_id, branch_id, doc_no),
    CONSTRAINT fk_pr_company  FOREIGN KEY (company_id)     REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pr_customer FOREIGN KEY (customer_id)    REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_pr_paymode  FOREIGN KEY (payment_mode_id) REFERENCES payment_modes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments_made (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED NOT NULL,
    doc_no          VARCHAR(30)  NOT NULL,
    doc_date        DATE         NOT NULL,
    supplier_id     INT UNSIGNED DEFAULT NULL,
    amount          DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    payment_mode_id INT UNSIGNED DEFAULT NULL,
    cheque_no       VARCHAR(30)  DEFAULT NULL,
    cheque_date     DATE         DEFAULT NULL,
    bank_name       VARCHAR(100) DEFAULT NULL,
    reference       VARCHAR(100) DEFAULT NULL,
    against_purchase_id INT UNSIGNED DEFAULT NULL,
    narration       TEXT DEFAULT NULL,
    created_by      INT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pm_doc (company_id, branch_id, doc_no),
    CONSTRAINT fk_pmade_company  FOREIGN KEY (company_id)     REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pmade_supplier FOREIGN KEY (supplier_id)    REFERENCES suppliers(id) ON DELETE SET NULL,
    CONSTRAINT fk_pmade_paymode  FOREIGN KEY (payment_mode_id) REFERENCES payment_modes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE bank_accounts (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED DEFAULT NULL,
    ledger_id       INT UNSIGNED DEFAULT NULL,
    name            VARCHAR(150) NOT NULL,
    account_no      VARCHAR(50)  DEFAULT NULL,
    ifsc            VARCHAR(20)  DEFAULT NULL,
    bank_name       VARCHAR(100) DEFAULT NULL,
    branch_name     VARCHAR(100) DEFAULT NULL,
    opening_balance DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    CONSTRAINT fk_ba_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ba_ledger  FOREIGN KEY (ledger_id)  REFERENCES ledger_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bank_transactions (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bank_id         INT UNSIGNED NOT NULL,
    txn_date        DATE         NOT NULL,
    type            ENUM('credit','debit') NOT NULL,
    amount          DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    reference       VARCHAR(100) DEFAULT NULL,
    narration       TEXT DEFAULT NULL,
    is_reconciled   TINYINT(1)   NOT NULL DEFAULT 0,
    reconciled_at   DATETIME     DEFAULT NULL,
    created_by      INT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bt_bank (bank_id),
    KEY idx_bt_date (txn_date),
    CONSTRAINT fk_bt_bank FOREIGN KEY (bank_id) REFERENCES bank_accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE expense_categories (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id  INT UNSIGNED NOT NULL,
    name        VARCHAR(100) NOT NULL,
    ledger_id   INT UNSIGNED DEFAULT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    CONSTRAINT fk_ec_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expenses (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED NOT NULL,
    doc_no          VARCHAR(30)  NOT NULL,
    doc_date        DATE         NOT NULL,
    category_id     INT UNSIGNED DEFAULT NULL,
    amount          DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    payment_mode_id INT UNSIGNED DEFAULT NULL,
    narration       TEXT DEFAULT NULL,
    created_by      INT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_exp_company  FOREIGN KEY (company_id)     REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_exp_category FOREIGN KEY (category_id)    REFERENCES expense_categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_exp_paymode  FOREIGN KEY (payment_mode_id) REFERENCES payment_modes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECTION 9: SYSTEM TABLES
-- ============================================================

CREATE TABLE settings (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id  INT UNSIGNED NOT NULL,
    branch_id   INT UNSIGNED DEFAULT NULL COMMENT 'NULL = company-wide setting',
    setting_key VARCHAR(80)  NOT NULL,
    setting_val TEXT         DEFAULT NULL,
    setting_type ENUM('string','int','decimal','bool','json') NOT NULL DEFAULT 'string',
    PRIMARY KEY (id),
    UNIQUE KEY uq_setting (company_id, branch_id, setting_key),
    CONSTRAINT fk_set_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Key-value configuration store — all runtime settings';

-- ============================================================

CREATE TABLE audit_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id  INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED DEFAULT NULL,
    action      VARCHAR(20)  NOT NULL COMMENT 'CREATE|UPDATE|DELETE|LOGIN|LOGOUT|PRINT|EXPORT',
    module      VARCHAR(50)  NOT NULL,
    record_id   INT UNSIGNED DEFAULT NULL,
    doc_no      VARCHAR(30)  DEFAULT NULL,
    old_values  JSON         DEFAULT NULL,
    new_values  JSON         DEFAULT NULL,
    ip_address  VARCHAR(45)  DEFAULT NULL,
    user_agent  VARCHAR(255) DEFAULT NULL,
    notes       VARCHAR(255) DEFAULT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_al_company (company_id),
    KEY idx_al_user    (user_id),
    KEY idx_al_module  (module),
    KEY idx_al_date    (created_at),
    CONSTRAINT fk_al_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE promotions (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    name            VARCHAR(150) NOT NULL,
    promo_type      ENUM('discount_percent','discount_fixed','free_item','buy_x_get_y') NOT NULL,
    start_date      DATE         DEFAULT NULL,
    end_date        DATE         DEFAULT NULL,
    min_qty         DECIMAL(12,4) DEFAULT NULL,
    min_value       DECIMAL(15,4) DEFAULT NULL,
    disc_percent    DECIMAL(8,4)  DEFAULT NULL,
    disc_amount     DECIMAL(15,4) DEFAULT NULL,
    free_item_id    INT UNSIGNED DEFAULT NULL,
    free_qty        DECIMAL(12,4) DEFAULT NULL,
    applies_to      ENUM('all_items','specific_items','category') NOT NULL DEFAULT 'all_items',
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    CONSTRAINT fk_promo_company FOREIGN KEY (company_id)  REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_promo_freeitem FOREIGN KEY (free_item_id) REFERENCES items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE promotion_items (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    promotion_id    INT UNSIGNED NOT NULL,
    item_id         INT UNSIGNED DEFAULT NULL,
    category_id     INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_pi_promo FOREIGN KEY (promotion_id) REFERENCES promotions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE loyalty_transactions (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    customer_id     INT UNSIGNED NOT NULL,
    sales_id        INT UNSIGNED DEFAULT NULL,
    txn_type        ENUM('earn','redeem','adjust','expire') NOT NULL DEFAULT 'earn',
    points          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    balance_after   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes           VARCHAR(255) DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_lt_customer (customer_id),
    CONSTRAINT fk_lt_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_lt_sales    FOREIGN KEY (sales_id)    REFERENCES sales_header(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE eway_bills (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    sales_id        INT UNSIGNED DEFAULT NULL,
    ewb_no          VARCHAR(30)  NOT NULL,
    generated_at    DATETIME     DEFAULT NULL,
    valid_till      DATETIME     DEFAULT NULL,
    vehicle_no      VARCHAR(20)  DEFAULT NULL,
    transport_id    VARCHAR(50)  DEFAULT NULL,
    distance_km     INT          DEFAULT NULL,
    status          ENUM('active','cancelled','expired') NOT NULL DEFAULT 'active',
    qr_code         TEXT         DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ewb_no (company_id, ewb_no),
    CONSTRAINT fk_ewb_sales FOREIGN KEY (sales_id) REFERENCES sales_header(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE gst_returns (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED NOT NULL,
    return_type     ENUM('GSTR1','GSTR3B','GSTR2A','GSTR9') NOT NULL,
    period_month    TINYINT UNSIGNED NOT NULL COMMENT '1-12',
    period_year     SMALLINT UNSIGNED NOT NULL,
    status          ENUM('draft','filed','error') NOT NULL DEFAULT 'draft',
    filed_at        DATETIME     DEFAULT NULL,
    arn_no          VARCHAR(50)  DEFAULT NULL COMMENT 'Acknowledgement Reference Number',
    json_data       LONGTEXT     DEFAULT NULL COMMENT 'Export JSON for GSTN portal',
    created_by      INT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_gst_period (company_id, branch_id, return_type, period_month, period_year),
    CONSTRAINT fk_gst_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE shift_closure (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED DEFAULT NULL,
    shift_date      DATE         NOT NULL,
    opened_at       DATETIME     NOT NULL,
    closed_at       DATETIME     DEFAULT NULL,
    opening_cash    DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    closing_cash    DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    total_sales     DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    total_returns   DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    total_cash      DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    total_card      DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    total_upi       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    total_credit    DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    variance        DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    notes           TEXT DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_shift_date (shift_date),
    CONSTRAINT fk_shift_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_shift_branch  FOREIGN KEY (branch_id)  REFERENCES branches(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE notifications (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id  INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED DEFAULT NULL COMMENT 'NULL = broadcast to all',
    title       VARCHAR(150) NOT NULL,
    message     TEXT NOT NULL,
    type        ENUM('info','warning','error','success') NOT NULL DEFAULT 'info',
    is_read     TINYINT(1)   NOT NULL DEFAULT 0,
    link        VARCHAR(255) DEFAULT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notif_user (user_id),
    KEY idx_notif_read (is_read),
    CONSTRAINT fk_notif_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE google_drive_config (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED DEFAULT NULL,
    client_id       VARCHAR(255) DEFAULT NULL,
    client_secret   VARCHAR(255) DEFAULT NULL,
    access_token    TEXT         DEFAULT NULL,
    refresh_token   TEXT         DEFAULT NULL,
    token_expires   DATETIME     DEFAULT NULL,
    folder_id       VARCHAR(100) DEFAULT NULL COMMENT 'Google Drive folder ID for backups',
    is_active       TINYINT(1)   NOT NULL DEFAULT 0,
    last_backup_at  DATETIME     DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_gd_branch (company_id, branch_id),
    CONSTRAINT fk_gd_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE paytm_config (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED DEFAULT NULL,
    merchant_id     VARCHAR(50)  DEFAULT NULL,
    merchant_key    VARCHAR(100) DEFAULT NULL,
    channel         VARCHAR(20)  DEFAULT 'WEB',
    industry_type   VARCHAR(30)  DEFAULT 'Retail',
    is_production   TINYINT(1)   NOT NULL DEFAULT 0,
    is_active       TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_paytm_branch (company_id, branch_id),
    CONSTRAINT fk_paytm_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

CREATE TABLE import_logs (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    branch_id       INT UNSIGNED DEFAULT NULL,
    filename        VARCHAR(255) NOT NULL,
    import_type     VARCHAR(50)  NOT NULL COMMENT 'ITEMS|CUSTOMERS|SUPPLIERS|PURCHASE|SALES',
    total_rows      INT UNSIGNED NOT NULL DEFAULT 0,
    success_rows    INT UNSIGNED NOT NULL DEFAULT 0,
    error_rows      INT UNSIGNED NOT NULL DEFAULT 0,
    error_details   JSON         DEFAULT NULL,
    status          ENUM('processing','completed','failed') NOT NULL DEFAULT 'completed',
    created_by      INT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_il_company (company_id),
    CONSTRAINT fk_il_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECTION 10: SEED DATA
-- ============================================================

-- Company: Artée (from PrintDesigner template names)
INSERT INTO companies (id, name, name_short, address1, city, state, country, gstin, currency, currency_symbol, tax_region, financial_year_start)
VALUES (1, 'Artée Fabrics Private Limited', 'Artée', 'Registered Office', 'Mumbai', 'Maharashtra', 'India', '', 'INR', '₹', 'IN_GST', '2024-04-01');

-- Branch: Head Office
INSERT INTO branches (id, company_id, code, name, city, state, is_head_office, is_active)
VALUES (1, 1, 'HO', 'Head Office', 'Mumbai', 'Maharashtra', 1, 1);

-- Default Location
INSERT INTO locations (id, branch_id, code, name, is_default) VALUES (1, 1, 'MAIN', 'Main Warehouse', 1);

-- Roles
INSERT INTO roles (id, company_id, name, is_system, is_active) VALUES
(1, 1, 'Administrator', 1, 1),
(2, 1, 'Manager', 0, 1),
(3, 1, 'Cashier', 0, 1),
(4, 1, 'Accountant', 0, 1),
(5, 1, 'Inventory Manager', 0, 1);

-- Default Admin User (password: Admin@123)
INSERT INTO users (id, company_id, branch_id, role_id, username, password_hash, name, email, is_active)
VALUES (1, 1, 1, 1, 'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'System Administrator', 'admin@rworld-erp.local', 1);

-- Price Levels
INSERT INTO price_levels (company_id, level_no, name) VALUES
(1, 1, 'Retail Price'),
(1, 2, 'Wholesale Price'),
(1, 3, 'Distributor Price'),
(1, 4, 'Trade Price'),
(1, 5, 'Special Price');

-- Units of Measurement
INSERT INTO units (company_id, code, name, decimal_places) VALUES
(1, 'PCS',  'Pieces',     0),
(1, 'MTR',  'Metres',     2),
(1, 'KG',   'Kilograms',  3),
(1, 'LTR',  'Litres',     3),
(1, 'BOX',  'Box',        0),
(1, 'DZ',   'Dozen',      0),
(1, 'PAIR', 'Pair',       0),
(1, 'SET',  'Set',        0),
(1, 'GM',   'Grams',      2),
(1, 'NOS',  'Numbers',    0);

-- ============================================================
-- TAX TYPES: India GST (most common presets)
-- ============================================================

INSERT INTO tax_types (id, company_id, code, name, tax_region, is_inclusive, components_count, is_active) VALUES
(1,  1, 'GST-0',    'GST 0% (Exempt)',          'IN_GST', 0, 0, 1),
(2,  1, 'GST-5',    'GST 5% (CGST 2.5+SGST 2.5)','IN_GST',0, 2, 1),
(3,  1, 'GST-12',   'GST 12% (CGST 6+SGST 6)',  'IN_GST', 0, 2, 1),
(4,  1, 'GST-18',   'GST 18% (CGST 9+SGST 9)',  'IN_GST', 0, 2, 1),
(5,  1, 'GST-28',   'GST 28% (CGST 14+SGST 14)','IN_GST', 0, 2, 1),
(6,  1, 'IGST-5',   'IGST 5%',                  'IN_GST', 0, 1, 1),
(7,  1, 'IGST-12',  'IGST 12%',                 'IN_GST', 0, 1, 1),
(8,  1, 'IGST-18',  'IGST 18%',                 'IN_GST', 0, 1, 1),
(9,  1, 'IGST-28',  'IGST 28%',                 'IN_GST', 0, 1, 1),
(10, 1, 'GST-5-INC','GST 5% Inclusive',         'IN_GST', 1, 2, 1),
(11, 1, 'GST-12-INC','GST 12% Inclusive',       'IN_GST', 1, 2, 1),
(12, 1, 'GST-18-INC','GST 18% Inclusive',       'IN_GST', 1, 2, 1),
(13, 1, 'GST-28-INC','GST 28% Inclusive',       'IN_GST', 1, 2, 1),
(14, 1, 'CESS-5-12', 'GST 12%+CESS 5%',        'IN_GST', 0, 3, 1),
(15, 1, 'CESS-5-28', 'GST 28%+CESS 5%',        'IN_GST', 0, 3, 1);

-- Tax Components for GST-5 (T1=CGST 2.5%, T2=SGST 2.5%)
INSERT INTO tax_components (tax_type_id, component_no, name, short_name, is_rate, rate_or_amt, applied_on, sort_order) VALUES
(2, 1, 'CGST', 'CGST', 1, 2.5000, 0, 1),
(2, 2, 'SGST', 'SGST', 1, 2.5000, 0, 2);

-- Tax Components for GST-12
INSERT INTO tax_components (tax_type_id, component_no, name, short_name, is_rate, rate_or_amt, applied_on, sort_order) VALUES
(3, 1, 'CGST', 'CGST', 1, 6.0000, 0, 1),
(3, 2, 'SGST', 'SGST', 1, 6.0000, 0, 2);

-- Tax Components for GST-18
INSERT INTO tax_components (tax_type_id, component_no, name, short_name, is_rate, rate_or_amt, applied_on, sort_order) VALUES
(4, 1, 'CGST', 'CGST', 1, 9.0000, 0, 1),
(4, 2, 'SGST', 'SGST', 1, 9.0000, 0, 2);

-- Tax Components for GST-28
INSERT INTO tax_components (tax_type_id, component_no, name, short_name, is_rate, rate_or_amt, applied_on, sort_order) VALUES
(5, 1, 'CGST', 'CGST', 1, 14.0000, 0, 1),
(5, 2, 'SGST', 'SGST', 1, 14.0000, 0, 2);

-- Tax Components for IGST-5
INSERT INTO tax_components (tax_type_id, component_no, name, short_name, is_rate, rate_or_amt, applied_on) VALUES
(6, 1, 'IGST', 'IGST', 1, 5.0000, 0);

-- Tax Components for IGST-12
INSERT INTO tax_components (tax_type_id, component_no, name, short_name, is_rate, rate_or_amt, applied_on) VALUES
(7, 1, 'IGST', 'IGST', 1, 12.0000, 0);

-- Tax Components for IGST-18
INSERT INTO tax_components (tax_type_id, component_no, name, short_name, is_rate, rate_or_amt, applied_on) VALUES
(8, 1, 'IGST', 'IGST', 1, 18.0000, 0);

-- Tax Components for IGST-28
INSERT INTO tax_components (tax_type_id, component_no, name, short_name, is_rate, rate_or_amt, applied_on) VALUES
(9, 1, 'IGST', 'IGST', 1, 28.0000, 0);

-- Inclusive versions (same components, tax_types.is_inclusive=1)
INSERT INTO tax_components (tax_type_id, component_no, name, short_name, is_rate, rate_or_amt, applied_on) VALUES
(10, 1, 'CGST', 'CGST', 1, 2.5000,  0),
(10, 2, 'SGST', 'SGST', 1, 2.5000,  0),
(11, 1, 'CGST', 'CGST', 1, 6.0000,  0),
(11, 2, 'SGST', 'SGST', 1, 6.0000,  0),
(12, 1, 'CGST', 'CGST', 1, 9.0000,  0),
(12, 2, 'SGST', 'SGST', 1, 9.0000,  0),
(13, 1, 'CGST', 'CGST', 1, 14.0000, 0),
(13, 2, 'SGST', 'SGST', 1, 14.0000, 0);

-- CESS variants (T3 = CESS applied on gross)
INSERT INTO tax_components (tax_type_id, component_no, name, short_name, is_rate, rate_or_amt, applied_on) VALUES
(14, 1, 'CGST', 'CGST', 1, 6.0000,  0),
(14, 2, 'SGST', 'SGST', 1, 6.0000,  0),
(14, 3, 'CESS', 'CESS', 1, 5.0000,  0),
(15, 1, 'CGST', 'CGST', 1, 14.0000, 0),
(15, 2, 'SGST', 'SGST', 1, 14.0000, 0),
(15, 3, 'CESS', 'CESS', 1, 5.0000,  0);

-- ============================================================
-- LEDGER GROUPS (standard chart of accounts)
-- ============================================================

INSERT INTO ledger_groups (company_id, parent_id, code, name, group_type, sort_order) VALUES
(1, NULL, 'ASSET',      'Assets',                     'asset',     1),
(1, NULL, 'LIAB',       'Liabilities',                'liability', 2),
(1, NULL, 'INCOME',     'Income',                     'income',    3),
(1, NULL, 'EXPENSE',    'Expenses',                   'expense',   4),
(1, NULL, 'EQUITY',     'Capital & Equity',           'equity',    5);

INSERT INTO ledger_groups (company_id, parent_id, code, name, group_type, sort_order)
SELECT 1, g.id, 'CURR_ASSET', 'Current Assets',           'asset',     1 FROM ledger_groups g WHERE g.company_id=1 AND g.code='ASSET';
INSERT INTO ledger_groups (company_id, parent_id, code, name, group_type, sort_order)
SELECT 1, g.id, 'FIX_ASSET', 'Fixed Assets',              'asset',     2 FROM ledger_groups g WHERE g.company_id=1 AND g.code='ASSET';
INSERT INTO ledger_groups (company_id, parent_id, code, name, group_type, sort_order)
SELECT 1, g.id, 'CURR_LIAB', 'Current Liabilities',       'liability', 1 FROM ledger_groups g WHERE g.company_id=1 AND g.code='LIAB';
INSERT INTO ledger_groups (company_id, parent_id, code, name, group_type, sort_order)
SELECT 1, g.id, 'SUNDRY_DEB','Sundry Debtors',            'asset',     1 FROM ledger_groups g WHERE g.company_id=1 AND g.code='CURR_ASSET';
INSERT INTO ledger_groups (company_id, parent_id, code, name, group_type, sort_order)
SELECT 1, g.id, 'SUNDRY_CRED','Sundry Creditors',         'liability', 1 FROM ledger_groups g WHERE g.company_id=1 AND g.code='CURR_LIAB';
INSERT INTO ledger_groups (company_id, parent_id, code, name, group_type, sort_order)
SELECT 1, g.id, 'BANK_ACCS', 'Bank Accounts',             'asset',     2 FROM ledger_groups g WHERE g.company_id=1 AND g.code='CURR_ASSET';
INSERT INTO ledger_groups (company_id, parent_id, code, name, group_type, sort_order)
SELECT 1, g.id, 'CASH_ACCS', 'Cash-in-Hand',              'asset',     3 FROM ledger_groups g WHERE g.company_id=1 AND g.code='CURR_ASSET';
INSERT INTO ledger_groups (company_id, parent_id, code, name, group_type, sort_order)
SELECT 1, g.id, 'DUTIES',    'Duties & Taxes',            'liability', 2 FROM ledger_groups g WHERE g.company_id=1 AND g.code='CURR_LIAB';
INSERT INTO ledger_groups (company_id, parent_id, code, name, group_type, sort_order)
SELECT 1, g.id, 'DIRECT_INC','Direct Income',             'income',    1 FROM ledger_groups g WHERE g.company_id=1 AND g.code='INCOME';
INSERT INTO ledger_groups (company_id, parent_id, code, name, group_type, sort_order)
SELECT 1, g.id, 'INDIRECT_INC','Indirect Income',         'income',    2 FROM ledger_groups g WHERE g.company_id=1 AND g.code='INCOME';
INSERT INTO ledger_groups (company_id, parent_id, code, name, group_type, sort_order)
SELECT 1, g.id, 'DIRECT_EXP','Direct Expenses',           'expense',   1 FROM ledger_groups g WHERE g.company_id=1 AND g.code='EXPENSE';
INSERT INTO ledger_groups (company_id, parent_id, code, name, group_type, sort_order)
SELECT 1, g.id, 'INDIRECT_EXP','Indirect Expenses',       'expense',   2 FROM ledger_groups g WHERE g.company_id=1 AND g.code='EXPENSE';

-- Core ledger accounts
INSERT INTO ledger_accounts (company_id, code, name, is_cash, is_active)
SELECT 1, 'CASH', 'Cash-in-Hand', 1, 1 FROM DUAL;
INSERT INTO ledger_accounts (company_id, code, name, is_active)
SELECT 1, 'SALES', 'Sales Account', 1 FROM DUAL;
INSERT INTO ledger_accounts (company_id, code, name, is_active)
SELECT 1, 'PURCHASE', 'Purchase Account', 1 FROM DUAL;
INSERT INTO ledger_accounts (company_id, code, name, is_tax, is_active)
SELECT 1, 'CGST-OUT', 'CGST Output', 1, 1 FROM DUAL;
INSERT INTO ledger_accounts (company_id, code, name, is_tax, is_active)
SELECT 1, 'SGST-OUT', 'SGST Output', 1, 1 FROM DUAL;
INSERT INTO ledger_accounts (company_id, code, name, is_tax, is_active)
SELECT 1, 'IGST-OUT', 'IGST Output', 1, 1 FROM DUAL;
INSERT INTO ledger_accounts (company_id, code, name, is_tax, is_active)
SELECT 1, 'CGST-IN', 'CGST Input (ITC)', 1, 1 FROM DUAL;
INSERT INTO ledger_accounts (company_id, code, name, is_tax, is_active)
SELECT 1, 'SGST-IN', 'SGST Input (ITC)', 1, 1 FROM DUAL;
INSERT INTO ledger_accounts (company_id, code, name, is_tax, is_active)
SELECT 1, 'IGST-IN', 'IGST Input (ITC)', 1, 1 FROM DUAL;

-- Payment Modes
INSERT INTO payment_modes (company_id, name, type, is_default, sort_order) VALUES
(1, 'Cash',          'cash',         1, 1),
(1, 'Credit Card',   'card',         0, 2),
(1, 'Debit Card',    'card',         0, 3),
(1, 'UPI / Paytm',   'upi',          0, 4),
(1, 'Bank Transfer', 'bank_transfer',0, 5),
(1, 'Cheque',        'cheque',       0, 6),
(1, 'Credit (Due)',  'credit',       0, 7);

-- Document Numbering defaults
INSERT INTO doc_numbering (company_id, branch_id, doc_type, prefix, current_no, min_digits, reset_yearly) VALUES
(1, 1, 'SALE',        'INV',  0, 6, 1),
(1, 1, 'PURCHASE',    'PUR',  0, 6, 1),
(1, 1, 'SALE_RETURN', 'SR',   0, 6, 1),
(1, 1, 'PUR_RETURN',  'PR',   0, 6, 1),
(1, 1, 'SO',          'SO',   0, 6, 1),
(1, 1, 'PO',          'PO',   0, 6, 1),
(1, 1, 'QUOTATION',   'QT',   0, 6, 1),
(1, 1, 'TRANSFER_OUT','TO',   0, 6, 1),
(1, 1, 'TRANSFER_IN', 'TI',   0, 6, 1),
(1, 1, 'STOCK_JOURNAL','SJ',  0, 6, 1),
(1, 1, 'RECEIPT',     'RCT',  0, 6, 1),
(1, 1, 'PAYMENT',     'PMT',  0, 6, 1),
(1, 1, 'JOURNAL',     'JV',   0, 6, 1),
(1, 1, 'EXPENSE',     'EXP',  0, 6, 1),
(1, 1, 'DELIVERY',    'DN',   0, 6, 1),
(1, 1, 'GRN',         'GRN',  0, 6, 1);

-- Default Settings
INSERT INTO settings (company_id, branch_id, setting_key, setting_val, setting_type) VALUES
(1, NULL, 'app_name',               'Rworld ERP',               'string'),
(1, NULL, 'app_version',            '1.0.0',                   'string'),
(1, NULL, 'date_format',            'd/m/Y',                   'string'),
(1, NULL, 'time_format',            'H:i',                     'string'),
(1, NULL, 'currency',               'INR',                     'string'),
(1, NULL, 'currency_symbol',        '₹',                       'string'),
(1, NULL, 'decimal_places',         '2',                       'int'),
(1, NULL, 'qty_decimal_places',     '2',                       'int'),
(1, NULL, 'round_off_enabled',      '1',                       'bool'),
(1, NULL, 'round_off_method',       'nearest',                 'string'),
(1, NULL, 'tax_region',             'IN_GST',                  'string'),
(1, NULL, 'financial_year_start',   '04',                      'int'),
(1, NULL, 'stock_negative_allowed', '0',                       'bool'),
(1, NULL, 'loyalty_enabled',        '0',                       'bool'),
(1, NULL, 'loyalty_earn_rate',      '1',                       'decimal'),
(1, NULL, 'loyalty_redeem_rate',    '1',                       'decimal'),
(1, NULL, 'eway_bill_threshold',    '50000',                   'decimal'),
(1, NULL, 'session_timeout',        '480',                     'int'),
(1, NULL, 'items_per_page',         '25',                      'int'),
(1, NULL, 'backup_auto',            '0',                       'bool'),
(1, NULL, 'backup_time',            '23:00',                   'string'),
(1, NULL, 'smtp_host',              '',                        'string'),
(1, NULL, 'smtp_port',              '587',                     'int'),
(1, NULL, 'smtp_user',              '',                        'string'),
(1, NULL, 'smtp_pass',              '',                        'string'),
(1, NULL, 'smtp_from',              '',                        'string'),
(1, NULL, 'barcode_default_type',   'CODE128',                 'string'),
(1, NULL, 'invoice_print_copies',   '1',                       'int'),
(1, NULL, 'invoice_template',       'A4_GST_default',          'string'),
(1, NULL, 'receipt_template',       '3INCH_GST_default',       'string');

-- Reason Codes
INSERT INTO reason_codes (company_id, code, reason, module) VALUES
(1, 'DEFECT',   'Product Defective',         'sales_return'),
(1, 'WRONG',    'Wrong Product Delivered',   'sales_return'),
(1, 'DAMAGED',  'Damaged in Transit',        'sales_return'),
(1, 'CUST_REQ', 'Customer Request',          'sales_return'),
(1, 'QUALITY',  'Quality Issue',             'purchase_return'),
(1, 'OVERSTOCK','Overstocked',               'purchase_return'),
(1, 'BREAKAGE', 'Physical Breakage',         'stock_journal'),
(1, 'EXPIRED',  'Product Expired',           'stock_journal'),
(1, 'OPENING',  'Opening Stock Entry',       'stock_journal'),
(1, 'COUNT_VAR','Physical Count Variance',   'stock_journal');

-- Expense Categories
INSERT INTO expense_categories (company_id, name) VALUES
(1, 'Rent'),
(1, 'Electricity'),
(1, 'Salaries'),
(1, 'Transport'),
(1, 'Stationery'),
(1, 'Repairs & Maintenance'),
(1, 'Marketing & Advertising'),
(1, 'Miscellaneous');

-- Permissions for Administrator (all modules, all permissions)
INSERT INTO permissions (role_id, module, sub_module, can_view, can_create, can_edit, can_delete, can_print, can_export, can_approve)
SELECT 1, m.module, m.sub_module, 1, 1, 1, 1, 1, 1, 1
FROM (
    SELECT 'dashboard'   AS module, NULL AS sub_module UNION ALL
    SELECT 'sales',       NULL        UNION ALL
    SELECT 'sales',       'sales_order' UNION ALL
    SELECT 'sales',       'quotation'   UNION ALL
    SELECT 'sales',       'sales_return' UNION ALL
    SELECT 'purchase',    NULL        UNION ALL
    SELECT 'purchase',    'purchase_order' UNION ALL
    SELECT 'purchase',    'purchase_return' UNION ALL
    SELECT 'inventory',   NULL        UNION ALL
    SELECT 'inventory',   'transfer'  UNION ALL
    SELECT 'inventory',   'adjustment' UNION ALL
    SELECT 'reports',     NULL        UNION ALL
    SELECT 'masters',     NULL        UNION ALL
    SELECT 'masters',     'items'     UNION ALL
    SELECT 'masters',     'customers' UNION ALL
    SELECT 'masters',     'suppliers' UNION ALL
    SELECT 'masters',     'tax_types' UNION ALL
    SELECT 'masters',     'categories' UNION ALL
    SELECT 'masters',     'units'     UNION ALL
    SELECT 'finance',     NULL        UNION ALL
    SELECT 'finance',     'vouchers'  UNION ALL
    SELECT 'finance',     'bank'      UNION ALL
    SELECT 'finance',     'expenses'  UNION ALL
    SELECT 'settings',    NULL        UNION ALL
    SELECT 'settings',    'users'     UNION ALL
    SELECT 'settings',    'roles'     UNION ALL
    SELECT 'settings',    'backup'    UNION ALL
    SELECT 'gst',         NULL        UNION ALL
    SELECT 'barcode',     NULL        UNION ALL
    SELECT 'imports',     NULL
) m;

-- Permissions for Cashier role (limited)
INSERT INTO permissions (role_id, module, sub_module, can_view, can_create, can_edit, can_delete, can_print, can_export, can_approve)
SELECT 3, m.module, m.sub_module, m.cv, m.cc, m.ce, m.cd, m.cp, m.cx, m.ca
FROM (
    SELECT 'dashboard' AS module, NULL AS sub_module, 1 cv, 0 cc, 0 ce, 0 cd, 0 cp, 0 cx, 0 ca UNION ALL
    SELECT 'sales',    NULL,       1,1,0,0,1,0,0 UNION ALL
    SELECT 'sales',    'sales_return',1,1,0,0,1,0,0 UNION ALL
    SELECT 'purchase', NULL,       1,0,0,0,0,0,0 UNION ALL
    SELECT 'inventory',NULL,       1,0,0,0,0,0,0 UNION ALL
    SELECT 'masters',  'items',    1,0,0,0,0,0,0 UNION ALL
    SELECT 'masters',  'customers',1,1,0,0,0,0,0
) m;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- END OF database.sql
-- QuickBill Phase 1 - Database Schema Complete
-- Tables: 65 | Seed rows: ~200+
-- ============================================================
