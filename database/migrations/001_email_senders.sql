CREATE TABLE IF NOT EXISTS email_senders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purpose ENUM('general','orders','invoices','account') NOT NULL,
    from_email VARCHAR(190) NOT NULL,
    from_name VARCHAR(190) NOT NULL,
    reply_to_email VARCHAR(190) NULL,
    smtp_host VARCHAR(255) NOT NULL,
    smtp_port SMALLINT UNSIGNED NOT NULL DEFAULT 465,
    smtp_encryption ENUM('ssl','tls','none') NOT NULL DEFAULT 'ssl',
    smtp_username VARCHAR(190) NOT NULL,
    encrypted_password TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    health_status ENUM('not_tested','healthy','error') NOT NULL DEFAULT 'not_tested',
    last_health_message VARCHAR(500) NULL,
    last_tested_at DATETIME NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_email_sender_purpose (purpose),
    CONSTRAINT fk_email_senders_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

