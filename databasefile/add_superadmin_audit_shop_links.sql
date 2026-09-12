CREATE TABLE IF NOT EXISTS superadmin_audit_shops (
    audit_id BIGINT UNSIGNED NOT NULL,
    shop_id INT UNSIGNED NOT NULL,
    shop_name VARCHAR(190) NOT NULL,
    PRIMARY KEY (audit_id, shop_id),
    INDEX idx_audit_affected_shop (shop_id, audit_id),
    CONSTRAINT fk_audit_shop_event FOREIGN KEY (audit_id) REFERENCES superadmin_audit_logs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
