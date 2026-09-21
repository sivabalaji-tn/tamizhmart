CREATE TABLE IF NOT EXISTS owner_debug_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shop_id INT UNSIGNED NOT NULL,
    owner_id INT UNSIGNED NOT NULL,
    action VARCHAR(40) NOT NULL,
    request_key CHAR(32) NOT NULL,
    summary VARCHAR(500) NOT NULL,
    details LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_debug_request (shop_id, request_key),
    INDEX idx_debug_shop (shop_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
