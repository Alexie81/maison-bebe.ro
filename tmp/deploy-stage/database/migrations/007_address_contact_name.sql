ALTER TABLE user_addresses
    ADD COLUMN IF NOT EXISTS contact_first_name VARCHAR(120) NULL AFTER name,
    ADD COLUMN IF NOT EXISTS contact_last_name VARCHAR(120) NULL AFTER contact_first_name;

UPDATE user_addresses a
JOIN users u ON u.id = a.user_id
SET a.contact_first_name = COALESCE(NULLIF(a.contact_first_name, ''), u.first_name),
    a.contact_last_name = COALESCE(NULLIF(a.contact_last_name, ''), u.last_name)
WHERE a.contact_first_name IS NULL
   OR a.contact_first_name = ''
   OR a.contact_last_name IS NULL
   OR a.contact_last_name = '';