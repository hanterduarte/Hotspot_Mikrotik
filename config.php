<?php
// config.php - Configuração principal do sistema

// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'hotspot_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configurações do Sistema
define('BASE_URL', 'https://wifibarato.maiscoresed.com.br');  // Ajuste conforme seu ambiente
define('TIMEZONE', 'America/Recife');

// Configurar timezone
date_default_timezone_set(TIMEZONE);

// Configurações do MikroTik
define('MIKROTIK_HOST', '192.168.88.1');
define('MIKROTIK_USER', 'admin');
define('MIKROTIK_PASS', ''); // Insira sua senha do MikroTik aqui

// Configurações de Email (SMTP)
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_USER', 'user@example.com');
define('SMTP_PASS', ''); // Insira sua senha de email aqui
define('SMTP_PORT', 587);
define('SMTP_FROM_EMAIL', 'contato@wifibarato.com.br');
define('SMTP_FROM_NAME', 'WiFi Barato');

// Configurações de Sessão
ini_set('session.cookie_httponly', 1);
session_start();

// Classe de Conexão com Banco de Dados
class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->conn = new PDO($dsn, DB_USER, DB_PASS);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            die("Erro de conexão: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    // Prevenir clonagem
    private function __clone() {}

    // Prevenir unserialize
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Função para obter configurações do banco
function getSetting($key, $default = null) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch(Exception $e) {
        return $default;
    }
}

// Função para salvar configurações
function saveSetting($key, $value) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        return $stmt->execute([$key, $value, $value]);
    } catch(Exception $e) {
        return false;
    }
}

// Função para registrar logs
function logEvent($type, $message, $related_id = null) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO logs (log_type, log_message, related_id) VALUES (?, ?, ?)");
        return $stmt->execute([$type, $message, $related_id]);
    } catch(Exception $e) {
        error_log("Erro ao registrar log: " . $e->getMessage());
        return false;
    }
}

// Função para gerar username único
function generateUsername($prefix = 'user') {
    return $prefix . '_' . date('YmdHis') . rand(100, 999);
}

// Função para gerar senha aleatória
function generatePassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}

// Função para validar CPF
function validateCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);

    if (strlen($cpf) != 11) {
        return false;
    }

    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }

    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    return true;
}

// Função para validar email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Função para sanitizar dados
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Função para formatar moeda
function formatMoney($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

// Função para enviar resposta JSON
function jsonResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Função para criar ou buscar cliente
function createOrGetCustomer($db, $customerData) {
    $name = $customerData['name'];
    $email = $customerData['email'];
    $phone = $customerData['phone'];
    $cpf = $customerData['cpf'];

    // Verificar se cliente já existe
    $stmt = $db->prepare("SELECT id FROM customers WHERE email = ?");
    $stmt->execute([$email]);
    $customer = $stmt->fetch();

    if ($customer) {
        $customerId = $customer['id'];

        // Atualizar dados do cliente
        $stmt = $db->prepare("UPDATE customers SET name = ?, phone = ?, cpf = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $cpf, $customerId]);
    } else {
        // Criar novo cliente
        $stmt = $db->prepare("INSERT INTO customers (name, email, phone, cpf) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $phone, $cpf]);
        $customerId = $db->lastInsertId();
    }

    return $customerId;
}

// Função para criar usuário no MikroTik e salvar no banco
function createHotspotUser($db, $mt, $transaction, $durationInSeconds) {
    // 1. Gerar credenciais
    $username = generateUsername('user');
    $password = generatePassword(8);

    // 2. Formatar o tempo de duração para o MikroTik (ex: 1d, 30d)
    $limitUptime = $durationInSeconds . 's';

    // 3. Tentar criar o usuário no MikroTik
    $result = $mt->createHotspotUser($username, $password, 'default', $limitUptime);

    if ($result['success']) {
        // 4. Se sucesso, salvar no banco de dados
        try {
            $stmt = $db->prepare("
                UPDATE transactions
                SET hotspot_user = ?, hotspot_password = ?
                WHERE id = ?
            ");
            $stmt->execute([$username, $password, $transaction['id']]);

            return [
                'success' => true,
                'username' => $username,
                'password' => $password
            ];

        } catch (Exception $e) {
            // Se falhar ao salvar, tenta remover o usuário criado no MikroTik para evitar inconsistência
            $mt->removeHotspotUser($username);
            logEvent('mikrotik_error', "Usuário $username criado no MikroTik, mas falhou ao salvar no DB. Usuário revertido.", $transaction['id']);
            return ['success' => false, 'message' => 'Falha ao salvar credenciais no banco de dados.'];
        }

    } else {
        // 5. Se falhar, retornar o erro
        return ['success' => false, 'message' => $result['message']];
    }
}

// Função para enviar email
function sendEmail($to, $subject, $body) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>' . "\r\n";

    // Para usar um SMTP externo, você precisará de uma biblioteca como o PHPMailer.
    // Esta é uma implementação básica usando a função mail() do PHP.
    // Pode ser necessário configurar o `php.ini` no seu servidor.

    try {
        if (mail($to, $subject, $body, $headers)) {
            logEvent('email_success', "Email enviado para $to. Assunto: $subject");
            return true;
        } else {
            logEvent('email_error', "Falha ao enviar email para $to. (Função mail() retornou false)");
            return false;
        }
    } catch (Exception $e) {
        logEvent('email_exception', "Exceção ao enviar email: " . $e->getMessage());
        return false;
    }
}


// Tratamento de erros
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    logEvent('error', "Error [$errno]: $errstr in $file on line $errline");
});

set_exception_handler(function($exception) {
    logEvent('exception', $exception->getMessage());
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        echo json_encode(['error' => 'Ocorreu um erro interno. Por favor, tente novamente.']);
    }
});
?>