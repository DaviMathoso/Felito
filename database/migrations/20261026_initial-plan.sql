-- Execute once on existing databases after taking a backup.
-- New installations receive the same objects from database/schema.sql.
SET time_zone = '+00:00';

ALTER TABLE products ADD COLUMN image_path VARCHAR(255) NULL AFTER description;
ALTER TABLE orders
    MODIFY status ENUM('received','preparing','ready','delivered','cancelled','expired') NOT NULL DEFAULT 'received',
    ADD COLUMN reserved_until DATETIME NULL AFTER total;
-- Existing orders predate reservation expiry; grant them five days after the migration.
UPDATE orders SET reserved_until = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 DAY) WHERE reserved_until IS NULL;
ALTER TABLE orders MODIFY reserved_until DATETIME NOT NULL;

UPDATE products SET image_path = CASE
    WHEN name = 'Baunilha' THEN '/assets/products/baunilha.svg'
    WHEN name = 'Doce de Leite' THEN '/assets/products/doce-de-leite.svg'
    WHEN name = 'Romeu e Julieta' THEN '/assets/products/romeu-e-julieta.svg'
    ELSE image_path END;

CREATE TABLE consent_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    terms_version VARCHAR(40) NOT NULL,
    privacy_version VARCHAR(40) NOT NULL,
    accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_consent_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    KEY idx_consent_user (user_id, accepted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE privacy_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    request_type ENUM('access','correction','deletion','portability') NOT NULL,
    details VARCHAR(1000) NULL,
    status ENUM('open','completed','rejected') NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_privacy_request_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    KEY idx_privacy_requests_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE loyalty_settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    points_per_real DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CHECK (points_per_real >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO loyalty_settings (id, points_per_real) VALUES (1, 1.00);

CREATE TABLE loyalty_rewards (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NOT NULL DEFAULT '',
    points_cost INT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (points_cost > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE loyalty_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NULL,
    reward_id INT UNSIGNED NULL,
    points_delta INT NOT NULL,
    reason VARCHAR(200) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_loyalty_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_loyalty_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
    CONSTRAINT fk_loyalty_reward FOREIGN KEY (reward_id) REFERENCES loyalty_rewards(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_loyalty_order_award (order_id),
    KEY idx_loyalty_user_date (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE loyalty_redemptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    reward_id INT UNSIGNED NOT NULL,
    status ENUM('pending','delivered','cancelled') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_redemption_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_redemption_reward FOREIGN KEY (reward_id) REFERENCES loyalty_rewards(id) ON DELETE RESTRICT,
    KEY idx_redemption_status_date (status, created_at),
    KEY idx_redemption_user_date (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE low_stock_alerts (
    product_id INT UNSIGNED PRIMARY KEY,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    CONSTRAINT fk_low_alert_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE monthly_stock_closures (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    period_month CHAR(7) NOT NULL,
    opening_qty INT NOT NULL,
    entries_qty INT UNSIGNED NOT NULL,
    exits_qty INT UNSIGNED NOT NULL,
    closing_qty INT NOT NULL,
    closed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_monthly_stock_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_monthly_product (product_id, period_month),
    KEY idx_monthly_stock_month (period_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
