ALTER TABLE product_variants
    ADD COLUMN IF NOT EXISTS ean VARCHAR(32) NULL AFTER sku,
    ADD COLUMN IF NOT EXISTS track_accounting_stock TINYINT(1) NOT NULL DEFAULT 1 AFTER track_inventory;

CREATE INDEX IF NOT EXISTS idx_variants_ean ON product_variants (ean);
CREATE INDEX IF NOT EXISTS idx_variants_accounting ON product_variants (track_accounting_stock, is_active);

ALTER TABLE invoices
    ADD COLUMN IF NOT EXISTS delivery_date DATE NULL AFTER issue_date,
    ADD COLUMN IF NOT EXISTS accounting_posted_at DATETIME NULL AFTER issued_at;

CREATE TABLE IF NOT EXISTS accounting_suppliers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    legal_name VARCHAR(255) NOT NULL,
    tax_id VARCHAR(40) NOT NULL,
    tax_id_normalized VARCHAR(40) NOT NULL,
    registration_number VARCHAR(100) NULL,
    address_json JSON NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_accounting_suppliers_tax_id (tax_id_normalized),
    KEY idx_accounting_suppliers_name (legal_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_warehouses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(190) NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_accounting_warehouses_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO accounting_warehouses (code,name,is_default,is_active)
VALUES ('PRINCIPALA','Gestiunea principală',1,1)
ON DUPLICATE KEY UPDATE name=VALUES(name),is_active=1;

CREATE TABLE IF NOT EXISTS supplier_invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id BIGINT UNSIGNED NOT NULL,
    supplier_name_snapshot VARCHAR(255) NOT NULL,
    supplier_tax_id_snapshot VARCHAR(40) NOT NULL,
    supplier_address_snapshot_json JSON NULL,
    invoice_series VARCHAR(80) NOT NULL,
    invoice_series_normalized VARCHAR(80) NOT NULL,
    invoice_number VARCHAR(100) NOT NULL,
    invoice_number_normalized VARCHAR(100) NOT NULL,
    invoice_date DATE NOT NULL,
    invoice_received_date DATE NULL,
    due_date DATE NULL,
    currency CHAR(3) NOT NULL DEFAULT 'RON',
    exchange_rate DECIMAL(18,6) NOT NULL DEFAULT 1.000000,
    delivery_note_number VARCHAR(100) NULL,
    delivery_note_date DATE NULL,
    total_without_vat DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    vat_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    grand_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft','partially_received','received','reversed') NOT NULL DEFAULT 'draft',
    is_late_entered TINYINT(1) NOT NULL DEFAULT 0,
    late_entry_reason VARCHAR(500) NULL,
    attachments_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by BIGINT UNSIGNED NULL,
    row_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uq_supplier_invoice_identity (supplier_id,invoice_series_normalized,invoice_number_normalized),
    KEY idx_supplier_invoice_date (invoice_date,status),
    CONSTRAINT fk_supplier_invoice_supplier FOREIGN KEY (supplier_id) REFERENCES accounting_suppliers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_supplier_invoice_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_supplier_invoice_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_invoice_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_invoice_id BIGINT UNSIGNED NOT NULL,
    supplier_product_code VARCHAR(120) NULL,
    supplier_product_name VARCHAR(255) NOT NULL,
    imported_sku VARCHAR(120) NULL,
    imported_ean VARCHAR(32) NULL,
    product_id BIGINT UNSIGNED NULL,
    product_variant_id BIGINT UNSIGNED NULL,
    maison_bebe_sku VARCHAR(120) NULL,
    unit_of_measure VARCHAR(20) NOT NULL DEFAULT 'buc',
    invoiced_quantity DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    unit_price_without_vat DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
    discount_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    value_without_vat DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    vat_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    total_with_vat DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    line_type ENUM('stockable','made_to_order','assembled_bundle','service','transport','tax','acquisition_cost','ignored') NOT NULL DEFAULT 'stockable',
    association_status ENUM('unmapped','automatic','manual','not_required') NOT NULL DEFAULT 'unmapped',
    is_ignored TINYINT(1) NOT NULL DEFAULT 0,
    ignore_reason VARCHAR(500) NULL,
    original_imported_data_json JSON NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_supplier_lines_invoice (supplier_invoice_id,sort_order),
    KEY idx_supplier_lines_mapping (product_variant_id,maison_bebe_sku),
    CONSTRAINT fk_supplier_line_invoice FOREIGN KEY (supplier_invoice_id) REFERENCES supplier_invoices(id) ON DELETE CASCADE,
    CONSTRAINT fk_supplier_line_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    CONSTRAINT fk_supplier_line_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nir_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    series VARCHAR(40) NULL,
    number BIGINT UNSIGNED NULL,
    formatted_number VARCHAR(100) NULL,
    document_kind ENUM('receipt','reversal') NOT NULL DEFAULT 'receipt',
    receipt_date DATE NOT NULL,
    supplier_invoice_id BIGINT UNSIGNED NOT NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    warehouse_id BIGINT UNSIGNED NOT NULL,
    status ENUM('Draft','RequiresProductMapping','InReception','ReadyForConfirmation','Confirmed','PartiallyReceived','Reversed') NOT NULL DEFAULT 'Draft',
    is_late_entered TINYINT(1) NOT NULL DEFAULT 0,
    late_entry_reason VARCHAR(500) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by BIGINT UNSIGNED NULL,
    confirmed_at DATETIME NULL,
    confirmed_by BIGINT UNSIGNED NULL,
    reversed_at DATETIME NULL,
    reversed_by BIGINT UNSIGNED NULL,
    reversal_reason VARCHAR(500) NULL,
    original_nir_id BIGINT UNSIGNED NULL,
    row_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uq_nir_formatted_number (formatted_number),
    KEY idx_nir_status_receipt (status,receipt_date),
    KEY idx_nir_supplier_invoice (supplier_invoice_id),
    CONSTRAINT fk_nir_supplier_invoice FOREIGN KEY (supplier_invoice_id) REFERENCES supplier_invoices(id) ON DELETE RESTRICT,
    CONSTRAINT fk_nir_supplier FOREIGN KEY (supplier_id) REFERENCES accounting_suppliers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_nir_warehouse FOREIGN KEY (warehouse_id) REFERENCES accounting_warehouses(id) ON DELETE RESTRICT,
    CONSTRAINT fk_nir_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_nir_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_nir_confirmed_by FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_nir_reversed_by FOREIGN KEY (reversed_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_nir_original FOREIGN KEY (original_nir_id) REFERENCES nir_documents(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nir_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nir_document_id BIGINT UNSIGNED NOT NULL,
    supplier_invoice_line_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NULL,
    product_variant_id BIGINT UNSIGNED NULL,
    sku_snapshot VARCHAR(120) NULL,
    product_name_snapshot VARCHAR(255) NOT NULL,
    variant_name_snapshot VARCHAR(255) NULL,
    unit_of_measure_snapshot VARCHAR(20) NOT NULL DEFAULT 'buc',
    online_stock_mode_snapshot ENUM('limited','unlimited') NOT NULL DEFAULT 'limited',
    track_accounting_stock_snapshot TINYINT(1) NOT NULL DEFAULT 1,
    invoiced_quantity DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    previously_received_quantity DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    received_quantity DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    accepted_quantity DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    damaged_quantity DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    difference_quantity DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    difference_type ENUM('none','shortage','surplus','damaged','wrong_product','other') NOT NULL DEFAULT 'none',
    difference_reason VARCHAR(500) NULL,
    observations VARCHAR(1000) NULL,
    unit_purchase_price_without_vat DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
    discount_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    allocated_acquisition_cost DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    value_without_vat DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    vat_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    total_with_vat DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_nir_lines_document (nir_document_id,sort_order),
    KEY idx_nir_lines_variant (product_variant_id),
    CONSTRAINT fk_nir_line_document FOREIGN KEY (nir_document_id) REFERENCES nir_documents(id) ON DELETE CASCADE,
    CONSTRAINT fk_nir_line_supplier_line FOREIGN KEY (supplier_invoice_line_id) REFERENCES supplier_invoice_lines(id) ON DELETE RESTRICT,
    CONSTRAINT fk_nir_line_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    CONSTRAINT fk_nir_line_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_supplier_mappings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id BIGINT UNSIGNED NOT NULL,
    supplier_product_code VARCHAR(120) NOT NULL,
    supplier_product_name VARCHAR(255) NULL,
    supplier_ean VARCHAR(32) NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    product_variant_id BIGINT UNSIGNED NOT NULL,
    maison_bebe_sku VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by BIGINT UNSIGNED NULL,
    UNIQUE KEY uq_supplier_product_mapping (supplier_id,supplier_product_code),
    KEY idx_supplier_mapping_variant (product_variant_id),
    CONSTRAINT fk_product_mapping_supplier FOREIGN KEY (supplier_id) REFERENCES accounting_suppliers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_product_mapping_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    CONSTRAINT fk_product_mapping_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT,
    CONSTRAINT fk_product_mapping_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_product_mapping_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_document_sequences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_type VARCHAR(40) NOT NULL,
    series VARCHAR(40) NOT NULL,
    fiscal_year SMALLINT UNSIGNED NOT NULL,
    last_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    row_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uq_accounting_document_sequence (document_type,series,fiscal_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_periods (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_locked TINYINT(1) NOT NULL DEFAULT 0,
    locked_at DATETIME NULL,
    locked_by BIGINT UNSIGNED NULL,
    unlock_reason VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_accounting_period (start_date,end_date),
    KEY idx_accounting_period_locked (is_locked,start_date,end_date),
    CONSTRAINT fk_accounting_period_locked_by FOREIGN KEY (locked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_valuation_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    valuation_method ENUM('WeightedAverage','FIFO') NOT NULL,
    reason VARCHAR(255) NOT NULL,
    earliest_effective_date DATE NULL,
    affected_skus_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    correlation_id VARCHAR(100) NOT NULL,
    KEY idx_valuation_runs_created (created_at),
    CONSTRAINT fk_valuation_run_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_stock_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    product_variant_id BIGINT UNSIGNED NOT NULL,
    sku_snapshot VARCHAR(120) NOT NULL,
    product_name_snapshot VARCHAR(255) NOT NULL,
    variant_name_snapshot VARCHAR(255) NULL,
    warehouse_id BIGINT UNSIGNED NOT NULL,
    effective_date DATE NOT NULL,
    effective_time TIME NULL,
    posted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    movement_type ENUM('NIR_IN','NIR_REVERSAL_OUT','SALES_INVOICE_OUT','SALES_INVOICE_REVERSAL_IN','CUSTOMER_RETURN_IN','SUPPLIER_RETURN_OUT','OPENING_DOCUMENT_IN') NOT NULL,
    quantity_in DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    quantity_out DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    source_unit_cost DECIMAL(18,6) NULL,
    source_document_type VARCHAR(60) NOT NULL,
    source_document_id BIGINT UNSIGNED NOT NULL,
    source_document_line_id BIGINT UNSIGNED NOT NULL,
    source_document_series VARCHAR(80) NULL,
    source_document_number VARCHAR(100) NULL,
    counterparty_snapshot VARCHAR(255) NULL,
    is_late_posted TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    reversal_of_movement_id BIGINT UNSIGNED NULL,
    correlation_id VARCHAR(100) NOT NULL,
    idempotency_key VARCHAR(190) NOT NULL,
    UNIQUE KEY uq_accounting_movement_idempotency (idempotency_key),
    KEY idx_accounting_movements_timeline (product_variant_id,warehouse_id,effective_date,posted_at,id),
    KEY idx_accounting_movements_source (source_document_type,source_document_id),
    CONSTRAINT chk_accounting_movement_direction CHECK ((quantity_in > 0 AND quantity_out = 0) OR (quantity_out > 0 AND quantity_in = 0)),
    CONSTRAINT fk_accounting_movement_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    CONSTRAINT fk_accounting_movement_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT,
    CONSTRAINT fk_accounting_movement_warehouse FOREIGN KEY (warehouse_id) REFERENCES accounting_warehouses(id) ON DELETE RESTRICT,
    CONSTRAINT fk_accounting_movement_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_accounting_movement_reversal FOREIGN KEY (reversal_of_movement_id) REFERENCES accounting_stock_movements(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_stock_balances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    product_variant_id BIGINT UNSIGNED NOT NULL,
    sku VARCHAR(120) NOT NULL,
    warehouse_id BIGINT UNSIGNED NOT NULL,
    current_quantity DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    current_accounting_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    calculated_unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
    minimum_historical_quantity DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    has_current_negative_balance TINYINT(1) NOT NULL DEFAULT 0,
    has_historical_negative_balance TINYINT(1) NOT NULL DEFAULT 0,
    last_movement_date DATE NULL,
    projection_version BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    row_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uq_accounting_balance_variant_warehouse (product_variant_id,warehouse_id),
    KEY idx_accounting_balance_quantity (current_quantity),
    CONSTRAINT fk_accounting_balance_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    CONSTRAINT fk_accounting_balance_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT,
    CONSTRAINT fk_accounting_balance_warehouse FOREIGN KEY (warehouse_id) REFERENCES accounting_warehouses(id) ON DELETE RESTRICT,
    CONSTRAINT fk_accounting_balance_projection FOREIGN KEY (projection_version) REFERENCES accounting_valuation_runs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_stock_valuations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    movement_id BIGINT UNSIGNED NOT NULL,
    valuation_run_id BIGINT UNSIGNED NOT NULL,
    valuation_method ENUM('WeightedAverage','FIFO') NOT NULL,
    calculated_unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
    calculated_movement_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    balance_quantity_after DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    balance_value_after DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    calculated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_accounting_valuation_run_movement (valuation_run_id,movement_id),
    KEY idx_accounting_valuation_movement (movement_id,valuation_run_id),
    CONSTRAINT fk_accounting_valuation_movement FOREIGN KEY (movement_id) REFERENCES accounting_stock_movements(id) ON DELETE RESTRICT,
    CONSTRAINT fk_accounting_valuation_run FOREIGN KEY (valuation_run_id) REFERENCES accounting_valuation_runs(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nir_artifacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nir_document_id BIGINT UNSIGNED NOT NULL,
    artifact_type ENUM('pdf','xlsx','source_pdf','source_xlsx','source_xml','delivery_note') NOT NULL,
    path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    sha256 CHAR(64) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    document_version INT UNSIGNED NOT NULL DEFAULT 1,
    generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    generated_by BIGINT UNSIGNED NULL,
    KEY idx_nir_artifacts_document (nir_document_id,artifact_type,generated_at),
    CONSTRAINT fk_nir_artifact_document FOREIGN KEY (nir_document_id) REFERENCES nir_documents(id) ON DELETE RESTRICT,
    CONSTRAINT fk_nir_artifact_user FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key,value_json,is_public)
VALUES ('accounting_stock',JSON_OBJECT('valuation_method','WeightedAverage','nir_series','NIR-MB','retention_years',10),0)
ON DUPLICATE KEY UPDATE value_json=COALESCE(value_json,VALUES(value_json));

INSERT INTO permissions (name,label) VALUES
    ('nir.view','Vizualizare NIR-uri'),
    ('nir.create','Creare și editare ciorne NIR'),
    ('nir.confirm','Confirmare NIR-uri'),
    ('nir.reverse','Inversare NIR-uri'),
    ('accounting_stock.view','Vizualizare Stocuri Conta'),
    ('accounting_stock.export','Export Stocuri Conta'),
    ('accounting_stock.settings','Configurare Stocuri Conta'),
    ('accounting_periods.manage','Administrare perioade contabile')
ON DUPLICATE KEY UPDATE label=VALUES(label);

INSERT INTO roles (name,label) VALUES
    ('reception_operator','Operator recepție'),
    ('accounting_viewer','Vizualizare Conta')
ON DUPLICATE KEY UPDATE label=VALUES(label);

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p
WHERE r.name='accountant' AND p.name IN ('nir.view','nir.create','nir.confirm','nir.reverse','accounting_stock.view','accounting_stock.export');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p
WHERE r.name='reception_operator' AND p.name IN ('dashboard.view','nir.view','nir.create','accounting_stock.view');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p
WHERE r.name='accounting_viewer' AND p.name IN ('dashboard.view','nir.view','accounting_stock.view','accounting_stock.export');
