ALTER TABLE collections
    ADD COLUMN IF NOT EXISTS canonical_url VARCHAR(500) NULL AFTER seo_description;
