CREATE TABLE IF NOT EXISTS product_gift_box_options (
    product_id BIGINT UNSIGNED PRIMARY KEY,
    gift_box_template_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_product_gift_box_template (gift_box_template_id),
    CONSTRAINT fk_product_gift_box_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_product_gift_box_template FOREIGN KEY (gift_box_template_id) REFERENCES gift_box_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
