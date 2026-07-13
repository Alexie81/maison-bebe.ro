ALTER TABLE products ADD COLUMN IF NOT EXISTS stripe_test_product_id VARCHAR(100) NULL AFTER stripe_product_id;
ALTER TABLE products ADD COLUMN IF NOT EXISTS stripe_test_synced_at DATETIME NULL AFTER stripe_test_product_id;
ALTER TABLE products ADD COLUMN IF NOT EXISTS stripe_test_sync_error VARCHAR(500) NULL AFTER stripe_test_synced_at;

ALTER TABLE product_variants ADD COLUMN IF NOT EXISTS stripe_test_price_id VARCHAR(100) NULL AFTER stripe_price_id;
ALTER TABLE product_variants ADD COLUMN IF NOT EXISTS stripe_test_price_minor BIGINT NULL AFTER stripe_test_price_id;
ALTER TABLE product_variants ADD COLUMN IF NOT EXISTS stripe_test_synced_at DATETIME NULL AFTER stripe_test_price_minor;
ALTER TABLE product_variants ADD COLUMN IF NOT EXISTS stripe_test_sync_error VARCHAR(500) NULL AFTER stripe_test_synced_at;
