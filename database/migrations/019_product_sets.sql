CREATE TABLE IF NOT EXISTS product_sets (
    product_id BIGINT UNSIGNED PRIMARY KEY,
    allow_gift_box TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_product_sets_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_set_components (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    set_product_id BIGINT UNSIGNED NOT NULL,
    component_variant_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_product_set_component (set_product_id, component_variant_id),
    KEY idx_product_set_components_variant (component_variant_id),
    CONSTRAINT fk_product_set_components_set FOREIGN KEY (set_product_id) REFERENCES product_sets(product_id) ON DELETE CASCADE,
    CONSTRAINT fk_product_set_components_variant FOREIGN KEY (component_variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT,
    CONSTRAINT chk_product_set_component_quantity CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
