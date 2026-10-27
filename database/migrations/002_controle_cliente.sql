-- Migração 002 — Controle de vendas do cliente (gestor)
-- Rode UMA vez no banco que já existe (phpMyAdmin > Importar). É seguro repetir: usa IF NOT EXISTS e INSERT IGNORE.
-- Faça um backup (Exportar) antes de rodar em produção.
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Controle de vendas (módulo do gestor, baseado no sistema atual do cliente)
-- Tabelas com prefixo ctl_ para não se misturar com a loja online.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ctl_locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    color CHAR(7) NOT NULL DEFAULT '#335b3d',
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_ctl_locations_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ctl_flavors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_ctl_flavors_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ctl_sizes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL,
    volume_ml INT UNSIGNED NOT NULL DEFAULT 0,
    packaging_cost DECIMAL(8,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_ctl_sizes_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- location_id = 0 significa "preço global" (vale para todos os locais sem preço próprio)
CREATE TABLE IF NOT EXISTS ctl_prices (
    flavor_id INT UNSIGNED NOT NULL,
    size_id INT UNSIGNED NOT NULL,
    location_id INT UNSIGNED NOT NULL DEFAULT 0,
    price DECIMAL(8,2) NOT NULL,
    PRIMARY KEY (flavor_id, size_id, location_id),
    CONSTRAINT fk_ctl_prices_flavor FOREIGN KEY (flavor_id) REFERENCES ctl_flavors(id) ON DELETE CASCADE,
    CONSTRAINT fk_ctl_prices_size FOREIGN KEY (size_id) REFERENCES ctl_sizes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ctl_clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ctl_clients_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada linha é um item de um pedido (vários itens compartilham o mesmo order_no)
CREATE TABLE IF NOT EXISTS ctl_sales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_no INT UNSIGNED NOT NULL,
    sale_date DATE NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    location_id INT UNSIGNED NOT NULL,
    flavor_id INT UNSIGNED NOT NULL,
    size_id INT UNSIGNED NOT NULL,
    qty INT UNSIGNED NOT NULL,
    unit_price DECIMAL(8,2) NOT NULL DEFAULT 0,
    status ENUM('a_fazer', 'a_entregar', 'entregue', 'pago', 'cancelado', 'resgate') NOT NULL DEFAULT 'a_fazer',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ctl_sales_client FOREIGN KEY (client_id) REFERENCES ctl_clients(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ctl_sales_location FOREIGN KEY (location_id) REFERENCES ctl_locations(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ctl_sales_flavor FOREIGN KEY (flavor_id) REFERENCES ctl_flavors(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ctl_sales_size FOREIGN KEY (size_id) REFERENCES ctl_sizes(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ctl_sales_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CHECK (qty > 0),
    KEY idx_ctl_sales_date (sale_date),
    KEY idx_ctl_sales_order (order_no),
    KEY idx_ctl_sales_client (client_id, status),
    KEY idx_ctl_sales_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Saldo por local, sabor e tamanho (pode ficar negativo quando se vende sem produção registrada)
CREATE TABLE IF NOT EXISTS ctl_stock (
    location_id INT UNSIGNED NOT NULL,
    flavor_id INT UNSIGNED NOT NULL,
    size_id INT UNSIGNED NOT NULL,
    qty INT NOT NULL DEFAULT 0,
    unit_value DECIMAL(10,4) NOT NULL DEFAULT 0,
    PRIMARY KEY (location_id, flavor_id, size_id),
    CONSTRAINT fk_ctl_stock_location FOREIGN KEY (location_id) REFERENCES ctl_locations(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ctl_stock_flavor FOREIGN KEY (flavor_id) REFERENCES ctl_flavors(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ctl_stock_size FOREIGN KEY (size_id) REFERENCES ctl_sizes(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Histórico: qty é sempre a quantidade movimentada; no ajuste, é o novo saldo (a observação guarda o saldo anterior)
CREATE TABLE IF NOT EXISTS ctl_stock_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    moved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    move_type ENUM('entrada', 'venda', 'estorno', 'transferencia', 'ajuste') NOT NULL,
    from_location_id INT UNSIGNED NULL,
    to_location_id INT UNSIGNED NULL,
    flavor_id INT UNSIGNED NOT NULL,
    size_id INT UNSIGNED NOT NULL,
    qty INT NOT NULL,
    note VARCHAR(200) NOT NULL DEFAULT '',
    sale_id BIGINT UNSIGNED NULL,
    order_no INT UNSIGNED NULL,
    batch_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    KEY idx_ctl_movements_date (moved_at),
    KEY idx_ctl_movements_item (flavor_id, size_id),
    CONSTRAINT fk_ctl_movements_flavor FOREIGN KEY (flavor_id) REFERENCES ctl_flavors(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ctl_movements_size FOREIGN KEY (size_id) REFERENCES ctl_sizes(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ctl_batches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_no INT UNSIGNED NOT NULL,
    batch_date DATE NOT NULL,
    location_id INT UNSIGNED NOT NULL,
    total_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    note VARCHAR(200) NOT NULL DEFAULT '',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ctl_batches_no (batch_no),
    CONSTRAINT fk_ctl_batches_location FOREIGN KEY (location_id) REFERENCES ctl_locations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ctl_batch_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id INT UNSIGNED NOT NULL,
    flavor_id INT UNSIGNED NOT NULL,
    size_id INT UNSIGNED NOT NULL,
    qty INT UNSIGNED NOT NULL,
    unit_price DECIMAL(8,2) NOT NULL DEFAULT 0,
    unit_cost DECIMAL(10,4) NULL,
    CONSTRAINT fk_ctl_batch_items_batch FOREIGN KEY (batch_id) REFERENCES ctl_batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_ctl_batch_items_flavor FOREIGN KEY (flavor_id) REFERENCES ctl_flavors(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ctl_batch_items_size FOREIGN KEY (size_id) REFERENCES ctl_sizes(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ctl_gifts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    voucher VARCHAR(20) NOT NULL,
    status ENUM('pendente', 'resgatado') NOT NULL DEFAULT 'pendente',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    redeemed_at DATETIME NULL,
    UNIQUE KEY uq_ctl_gifts_voucher (voucher),
    KEY idx_ctl_gifts_client (client_id),
    CONSTRAINT fk_ctl_gifts_client FOREIGN KEY (client_id) REFERENCES ctl_clients(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ctl_settings (
    setting_key VARCHAR(60) NOT NULL PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dados iniciais (equivalentes aos padrões do sistema do cliente); INSERT IGNORE permite rodar de novo sem duplicar
INSERT IGNORE INTO ctl_locations (name, color, is_default) VALUES
    ('Casa', '#335b3d', 1),
    ('Mercado Delicatto', '#b94e1c', 0),
    ('Eventos', '#125b80', 0);

INSERT IGNORE INTO ctl_flavors (name) VALUES
    ('Doce de Leite'), ('Doce de Leite com Coco'), ('Ovomaltine'), ('Cookies & Cream'), ('Cheesecake de Frutas Vermelhas'),
    ('Romeu & Julieta'), ('Strogonoff de Nozes'), ('Brigadeiro'), ('Pistache'), ('Paçoca');

INSERT IGNORE INTO ctl_sizes (name, volume_ml, packaging_cost) VALUES
    ('240 mL', 240, 0.80), ('480 mL', 480, 1.20), ('1,5 L', 1500, 2.50);

INSERT IGNORE INTO ctl_prices (flavor_id, size_id, location_id, price)
SELECT f.id, s.id, 0,
       CASE WHEN f.name IN ('Romeu & Julieta', 'Doce de Leite com Coco', 'Strogonoff de Nozes')
            THEN CASE s.name WHEN '240 mL' THEN 20.00 WHEN '480 mL' THEN 35.00 ELSE 100.00 END
            ELSE CASE s.name WHEN '240 mL' THEN 17.00 WHEN '480 mL' THEN 30.00 ELSE 85.00 END
       END
FROM ctl_flavors f CROSS JOIN ctl_sizes s;

INSERT IGNORE INTO ctl_settings (setting_key, setting_value) VALUES
    ('next_order_no', '1'), ('next_batch_no', '1'),
    ('fid_stamp_value', '15'), ('fid_stamps_per_gift', '10'), ('fid_period_type', 'days'), ('fid_window_days', '90');
