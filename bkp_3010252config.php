<?php
// config.php - Configuração principal do sistema

// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'hotspot_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configurações do Sistema
define('BASE_URL', 'https://wifibarato.maiscoresed.com.br'); // Ajuste conforme seu ambiente
define('TIMEZONE', 'America/Recife');

// Configurar timezone
date_default_timezone_set(TIMEZONE);

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
}

// Inicializa o banco de dados e obtém a conexão
$db = Database::getInstance()->getConnection();

// --- Funções de Log e Configuração ---

// Função simples para logar eventos (implemente a sua tabela 'logs' se não existir)
function logEvent($type, $message, $transactionId = null) {
    global $db;
    try {
        $stmt = $db->prepare("INSERT INTO logs (type, message, transaction_id, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$type, $message, $transactionId]);
    } catch (PDOException $e) {
        // Falha silenciosa no log se o DB estiver indisponível ou tabela logs inexistente
        // error_log("Log Event Error: " . $e->getMessage());
    }
}

// Função para buscar configurações do DB (tabela 'settings' deve existir)
function getSetting($key, $default = null) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = ? LIMIT 1");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result['value'] ?? $default;
    } catch (PDOException $e) {
        // Se a tabela settings não existir, retorna o default (útil para migração)
        return $default; 
    }
}

// --- Funções de Validação e Sanitização ---

// Função para criar ou obter ID do cliente (assumida a existência no seu sistema)
function createOrGetCustomer(PDO $db, $name, $email, $phone, $cpf) {
    // Implemente a lógica de busca/criação na sua tabela 'customers'
    // Exemplo Simples (Apenas busca o ID se existir):
    $stmt = $db->prepare("SELECT id FROM customers WHERE cpf = ?");
    $stmt->execute([$cpf]);
    $customer = $stmt->fetch();

    if ($customer) {
        return $customer['id'];
    }

    // Se não existir, insere e retorna o novo ID
    $stmt = $db->prepare("INSERT INTO customers (name, email, phone, cpf, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$name, $email, $phone, $cpf]);
    return $db->lastInsertId();
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

// --- NOVAS Funções de Hotspot (Implementadas e Corrigidas) ---

/**
 * Gera um username aleatório (ex: user12345678)
 */
function generateUsername() {
    return 'user' . mt_rand(10000000, 99999999);
}

/**
 * Gera uma senha numérica aleatória (ex: 6 dígitos)
 */
function generatePassword() {
    return strval(mt_rand(100000, 999999));
}

/**
 * Busca o nome do perfil Hotspot associado a um plano.
 */
function getPlanProfile(PDO $db, $planId) {
    $stmt = $db->prepare("SELECT mikrotik_profile FROM plans WHERE id = ?");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();
    return $plan['mikrotik_profile'] ?? null;
}

/**
 * Salva as credenciais do usuário Hotspot no banco de dados.
 * ATUALIZADA para sua estrutura de tabela, incluindo customer_id.
 */
function saveHotspotUser(PDO $db, $transactionId, $customerId, $planId, $username, $password, $expiresAt = null) {
    $stmt = $db->prepare("
        INSERT INTO hotspot_users (transaction_id, customer_id, plan_id, username, password, expires_at, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    // O expiresAt é passado como NULL por padrão, resolvendo o problema de campo NOT NULL.
    return $stmt->execute([$transactionId, $customerId, $planId, $username, $password, $expiresAt]);
}

// Tratamento de erros
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    logEvent('error', "Error [$errno]: $errstr in $errfile on line $errline");
});

set_exception_handler(function($exception) {
    logEvent('exception', $exception->getMessage());
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        // Exibe uma mensagem genérica de erro no formato JSON para requisições
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
             echo json_encode(['error' => 'Ocorreu um erro interno. Por favor, tente novamente.']);
        } else {
             // Exibe uma mensagem simples
             echo "<h1>Erro Interno do Servidor</h1><p>Ocorreu um erro. Por favor, tente novamente mais tarde.</p>";
        }
        exit;
    }
});