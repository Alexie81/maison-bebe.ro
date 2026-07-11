ALTER TABLE email_senders
    MODIFY purpose ENUM('general','orders','invoices','account','recovery') NOT NULL;

INSERT INTO email_senders (
    purpose,from_email,from_name,reply_to_email,smtp_host,smtp_port,smtp_encryption,
    smtp_username,encrypted_password,is_active,health_status,last_health_message,last_tested_at
)
SELECT
    'recovery',from_email,'Maison Bébé · Recuperare cont',reply_to_email,smtp_host,smtp_port,
    smtp_encryption,smtp_username,encrypted_password,0,'not_tested',NULL,NULL
FROM email_senders
WHERE purpose IN ('account','general')
ORDER BY FIELD(purpose,'account','general')
LIMIT 1
ON DUPLICATE KEY UPDATE purpose=VALUES(purpose);