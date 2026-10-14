-- Tabelas BASE. Cada responsável de módulo acrescenta as suas
-- (consents, stock_movements, stock_monthly, loyalty_ledger, audit_log...).

CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE flavors (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  description TEXT NOT NULL,
  price       DECIMAL(8,2) NOT NULL,
  stock_qty   INT NOT NULL DEFAULT 0,
  min_stock   INT NOT NULL DEFAULT 5,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,   -- sabor do mês
  is_active   TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE coupons (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code      VARCHAR(40) NOT NULL UNIQUE,
  type      ENUM('percent','fixed') NOT NULL,
  value     DECIMAL(8,2) NOT NULL,
  uses      INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE orders (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  status         ENUM('reserved','paid','expired','cancelled') NOT NULL DEFAULT 'reserved',
  total          DECIMAL(10,2) NOT NULL,
  coupon_id      INT UNSIGNED NULL,
  reserved_until DATETIME NOT NULL,            -- prazo da reserva
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (coupon_id) REFERENCES coupons(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_items (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id   INT UNSIGNED NOT NULL,
  flavor_id  INT UNSIGNED NOT NULL,
  qty        INT NOT NULL,
  unit_price DECIMAL(8,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id),
  FOREIGN KEY (flavor_id) REFERENCES flavors(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id            INT UNSIGNED NOT NULL,
  provider_payment_id VARCHAR(40) NOT NULL UNIQUE,   -- id que o Mercado Pago devolve
  status              ENUM('pending','approved','cancelled') NOT NULL DEFAULT 'pending',
  FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
