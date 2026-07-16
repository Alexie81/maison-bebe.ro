DELETE invalid_sender
FROM email_senders invalid_sender
INNER JOIN email_senders marketing_sender
    ON marketing_sender.purpose = 'marketing'
WHERE invalid_sender.purpose = '';

UPDATE email_senders
SET purpose = 'marketing',
    is_active = 0,
    health_status = 'not_tested',
    last_health_message = NULL,
    last_tested_at = NULL
WHERE purpose = '';
