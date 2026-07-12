ALTER TABLE email_senders
    MODIFY purpose ENUM('general','orders','invoices','account','recovery','marketing') NOT NULL;

INSERT INTO email_senders (
    purpose,from_email,from_name,reply_to_email,smtp_host,smtp_port,smtp_encryption,
    smtp_username,encrypted_password,is_active,health_status,last_health_message,last_tested_at
)
SELECT
    'marketing',from_email,'Maison Bébé · Scrisori din Atelier',reply_to_email,smtp_host,smtp_port,
    smtp_encryption,smtp_username,encrypted_password,0,'not_tested',NULL,NULL
FROM email_senders
WHERE purpose='general'
LIMIT 1
ON DUPLICATE KEY UPDATE purpose=VALUES(purpose);

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    email VARCHAR(190) NOT NULL,
    product_updates TINYINT(1) NOT NULL DEFAULT 1,
    article_updates TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('active','unsubscribed') NOT NULL DEFAULT 'active',
    unsubscribe_token CHAR(64) NOT NULL,
    subscribed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    unsubscribed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_newsletter_email (email),
    UNIQUE KEY uq_newsletter_token (unsubscribe_token),
    KEY idx_newsletter_active (status,product_updates,article_updates),
    KEY idx_newsletter_user (user_id),
    CONSTRAINT fk_newsletter_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;