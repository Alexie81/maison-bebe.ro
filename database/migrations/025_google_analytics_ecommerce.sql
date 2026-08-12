ALTER TABLE orders
    ADD COLUMN ga_client_id VARCHAR(120) NULL AFTER gift_message,
    ADD COLUMN ga_session_id VARCHAR(120) NULL AFTER ga_client_id;

CREATE TABLE IF NOT EXISTS analytics_server_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_key VARCHAR(190) NOT NULL,
    event_name VARCHAR(80) NOT NULL,
    order_id BIGINT UNSIGNED NULL,
    payload_json JSON NOT NULL,
    status ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(1000) NULL,
    sent_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_analytics_server_event_key (event_key),
    KEY idx_analytics_server_status (status,updated_at),
    KEY idx_analytics_server_order (order_id,event_name),
    CONSTRAINT fk_analytics_server_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
