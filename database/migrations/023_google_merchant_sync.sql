CREATE TABLE IF NOT EXISTS google_merchant_product_sync (
    product_variant_id BIGINT UNSIGNED PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    offer_id VARCHAR(100) NOT NULL,
    product_input_name VARCHAR(500) NULL,
    payload_hash CHAR(64) NULL,
    status ENUM('pending','synced','deleted','failed') NOT NULL DEFAULT 'pending',
    last_error VARCHAR(1000) NULL,
    synced_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_google_merchant_offer (offer_id),
    KEY idx_google_merchant_product (product_id),
    KEY idx_google_merchant_status (status),
    CONSTRAINT fk_google_merchant_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
    CONSTRAINT fk_google_merchant_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS google_merchant_sync_jobs (
    product_id BIGINT UNSIGNED PRIMARY KEY,
    status ENUM('pending','processing','retry','synced','requires_attention') NOT NULL DEFAULT 'pending',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_error VARCHAR(1000) NULL,
    last_synced_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_google_merchant_jobs_queue (status, available_at),
    CONSTRAINT fk_google_merchant_job_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

