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
        // Em caso de erro (ex: tabela settings não existe), retorna o default
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
        // Garante que a mensagem não exceda o limite da coluna (ex: 65535 para TEXT)
        $message = substr($message, 0, 65500); 
        $stmt = $db->prepare("INSERT INTO logs (log_type, log_message, related_id) VALUES (?, ?, ?)");
        return $stmt->execute([$type, $message, $related_id]);
    } catch(Exception $e) {
        error_log("Erro ao registrar log: " . $e->getMessage());
        return false;
    }
}

// --- FUNÇÃO ADICIONADA: Envio de Credenciais por E-mail ---
function sendCredentialsEmail($toEmail, $userName, $password, $planName) {
    // !!! IMPORTANTE: SUBSTITUA ESTA FUNÇÃO PELA SUA LÓGICA REAL DE ENVIO DE E-MAIL (PHPMailer, etc.) !!!
    
    $subject = 'Suas Credenciais de Acesso ao WiFi Hotspot (' . $planName . ')';
    $message = "
        Olá,
        
        Seu pagamento foi confirmado e seu acesso liberado!
        
        Aqui estão suas credenciais para acessar o nosso WiFi ($planName):
        Usuário: $userName
        Senha: $password
        
        Guarde esta informação em local seguro.
        Obrigado!
    ";
    
    // Simulação do envio (Loga o evento para confirmação)
    logEvent('email_info', "Email de credenciais simulado enviado para $toEmail. Usuário: $userName");

    // Se quiser usar a função mail() nativa do PHP, descomente e configure seu servidor:
    // return mail($toEmail, $subject, $message, 'From: Suporte Hotspot <nao-responda@seusite.com>');

    return true; 
}
// --- Fim da Função Adicionada ---

// Função para criar ou obter um cliente
function createOrGetCustomer($db, $customerData) {
    // 1. Tenta encontrar o cliente pelo email
    $stmt = $db->prepare("SELECT id FROM customers WHERE email = ?");
    $stmt->execute([$customerData['email']]);
    $customer = $stmt->fetch();

    if ($customer) {
        // Cliente encontrado, retorna o ID
        return $customer['id'];
    } else {
        // 2. Cliente não encontrado, cria um novo
        $stmt = $db->prepare("
            INSERT INTO customers (name, email, phone, cpf) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $customerData['name'],
            $customerData['email'],
            $customerData['phone'],
            $customerData['cpf']
        ]);
        // Retorna o ID do novo cliente
        return $db->lastInsertId();
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
             // Se não for uma requisição JSON, mostra uma página de erro simples
             echo "<h1>Erro 500</h1><p>Ocorreu um erro interno. Verifique os logs para detalhes.</p>";
        }
    }
});
?>