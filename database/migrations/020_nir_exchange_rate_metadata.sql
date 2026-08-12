ALTER TABLE supplier_invoices
    ADD COLUMN exchange_rate_date DATE NULL AFTER exchange_rate,
    ADD COLUMN exchange_rate_source VARCHAR(80) NULL AFTER exchange_rate_date;
