UPDATE company_profiles
SET vat_status = 'plătitor',
    vat_code = 'RO26283407',
    updated_at = CURRENT_TIMESTAMP
WHERE REPLACE(REPLACE(UPPER(tax_id), 'RO', ''), ' ', '') = '26283407';