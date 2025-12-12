-- Criar banco de dados
CREATE DATABASE IF NOT EXISTS hotspot_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hotspot_system;

-- Tabela de planos
CREATE TABLE IF NOT EXISTS plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    duration VARCHAR(50) NOT NULL,
    duration_seconds INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    description TEXT,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Inserir planos padrão
INSERT INTO plans (name, duration, duration_seconds, price, description) VALUES
('2 Horas', '2h', 7200, 10.00, 'Ideal para navegação rápida, consultar e-mails ou enviar mensagens.'),
('6 Horas', '6h', 21600, 15.00, 'Perfeito para o dia todo de trabalho ou lazer.'),
('12 Horas', '12h', 43200, 25.00, 'Pensado para o uso prolongado com maior economia.'),
('30 Dias', '30d', 2592000, 50.00, 'Plano mais completo, ideal para uso constante.');

-- Tabela de clientes/usuários
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    cpf VARCHAR(14),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_email (email)
) ENGINE=InnoDB;

-- Tabela de transações/pagamentos
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    plan_id INT NOT NULL,
    payment_id VARCHAR(255),
    payment_method VARCHAR(50),
    payment_status VARCHAR(50) DEFAULT 'pending',
    amount DECIMAL(10,2) NOT NULL,
    gateway VARCHAR(50) NOT NULL,
    gateway_response TEXT,
    paid_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (plan_id) REFERENCES plans(id),
    INDEX idx_payment_id (payment_id),
    INDEX idx_payment_status (payment_status)
) ENGINE=InnoDB;

-- Tabela de usuários do hotspot (credenciais)
CREATE TABLE IF NOT EXISTS hotspot_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    customer_id INT NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    plan_id INT NOT NULL,
    expires_at DATETIME NOT NULL,
    active TINYINT(1) DEFAULT 1,
    mikrotik_synced TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at DATETIME NULL,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (plan_id) REFERENCES plans(id),
    INDEX idx_username (username),
    INDEX idx_active (active),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- Tabela de configurações
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Inserir configurações padrão
INSERT INTO settings (setting_key, setting_value, description) VALUES
('mikrotik_host', '192.168.88.1', 'IP do MikroTik'),
('mikrotik_port', '8728', 'Porta da API do MikroTik'),
('mikrotik_user', 'admin', 'Usuário do MikroTik'),
('mikrotik_password', '', 'Senha do MikroTik'),
('mercadopago_access_token', '', 'Access Token do Mercado Pago'),
('mercadopago_public_key', '', 'Public Key do Mercado Pago'),
('site_name', 'WiFi Barato', 'Nome do site'),
('support_phone', '(81) 99818-1680', 'Telefone de suporte'),
('support_email', 'hanter.duarte@gmail.com', 'Email de suporte');

-- Tabela de logs
CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_type VARCHAR(50) NOT NULL,
    log_message TEXT NOT NULL,
    related_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_type (log_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;