CREATE TABLE IF NOT EXISTS coupon_collections (
    coupon_id BIGINT UNSIGNED NOT NULL,
    collection_id BIGINT UNSIGNED NOT NULL,
    mode ENUM('include','exclude') NOT NULL DEFAULT 'include',
    PRIMARY KEY (coupon_id, collection_id),
    CONSTRAINT fk_coupon_collections_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
    CONSTRAINT fk_coupon_collections_collection FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
