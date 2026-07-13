ALTER TABLE products ADD COLUMN IF NOT EXISTS stripe_product_id VARCHAR(100) NULL AFTER sku;
ALTER TABLE products ADD COLUMN IF NOT EXISTS stripe_synced_at DATETIME NULL AFTER stripe_product_id;
ALTER TABLE products ADD COLUMN IF NOT EXISTS stripe_sync_error VARCHAR(500) NULL AFTER stripe_synced_at;
ALTER TABLE product_variants ADD COLUMN IF NOT EXISTS stripe_price_id VARCHAR(100) NULL AFTER sku;
ALTER TABLE product_variants ADD COLUMN IF NOT EXISTS stripe_price_minor BIGINT NULL AFTER stripe_price_id;
ALTER TABLE product_variants ADD COLUMN IF NOT EXISTS stripe_synced_at DATETIME NULL AFTER stripe_price_minor;
ALTER TABLE product_variants ADD COLUMN IF NOT EXISTS stripe_sync_error VARCHAR(500) NULL AFTER stripe_synced_at;
