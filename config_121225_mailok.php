<?php
// config.php - Configuração principal do sistema
// =================================================================================
// 🟢 NOVO: Inclusão do PHPMailer para envio via SMTP
// (Ajuste os caminhos abaixo se a pasta não for 'includes/PHPMailer')
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// ⚠️ Mude o caminho abaixo para onde o PHPMailer está no seu projeto
require 'includes/PHPMailer/src/Exception.php';
require 'includes/PHPMailer/src/PHPMailer.php';
require 'includes/PHPMailer/src/SMTP.php';
// =================================================================================

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
        // Normalizar message: se for array/object, transformar em JSON
        if (is_array($message) || is_object($message)) {
            $messageToStore = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $messageToStore = (string)$message;
        }

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO logs (log_type, log_message, related_id, created_at) VALUES (?, ?, ?, NOW())");
        return $stmt->execute([$type, $messageToStore, $related_id]);
    } catch(Exception $e) {
        // Registrar no error_log do PHP se falhar para não quebrar fluxo
        error_log("Erro ao registrar log (logEvent): " . $e->getMessage());
        error_log("Original log was: type={$type} message=" . (is_scalar($message) ? $message : json_encode($message)));
        return false;
    }
}
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
function generatePassword($length = 10) { 
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
        echo json_encode(['error' => 'Ocorreu um erro interno. Por favor, tente novamente.']);
    }
});


// ======================================================================
// 🟢 NOVO: Função de Envio de E-mail via PHPMailer (SMTP)
// ======================================================================

/**
 * Função central para envio de emails via PHPMailer (SMTP).
 * * @param string $to O endereço de email do destinatário.
 * @param string $subject O assunto do email.
 * @param string $body O corpo do email (HTML é recomendado).
 * @return bool True se o envio foi bem-sucedido, False caso contrário.
 */
function sendEmail($to, $subject, $body) {
    // Verifica se o envio por email está ativado nas configurações do DB
    if (getSetting('enable_email_sending', 'false') !== 'true') {
        logEvent('email_info', "Email de credenciais não enviado (Desabilitado na tabela settings).");
        return false;
    }

    $mail = new PHPMailer(true); // O 'true' ativa exceções

    try {

        // 🟢 DEBUG TEMPORÁRIO ATIVADO
        //$mail->SMTPDebug = 2; // Remove após os testes
        //$mail->Debugoutput = function($str, $level) {
        //    logEvent('smtp_debug', "SMTP [$level]: $str");
        //};

        // Configurações do Servidor SMTP (Umbler)
        $mail->isSMTP();
        $mail->Host       = 'smtp.umbler.com';  // Seu servidor SMTP
        $mail->SMTPAuth   = true;
        $mail->Username   = 'wifibarato@maiscoresed.com.br'; // Seu Nome de Usuário (o próprio email)
        $mail->Password   = '300588HfdS@'; 
        
        // Criptografia TLS e Porta 587
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port       = 587;
        
        // Configurações do Remetente
        $mail->setFrom('wifibarato@maiscoresed.com.br', 'Wi-Fi Barato by Wi Guest Portal');
        $mail->addAddress($to);
        
        // Conteúdo do E-mail
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $body;
        
        $mail->send();
        logEvent('email_success', "Email de credenciais enviado para $to via SMTP.");
        return true;

    } catch (Exception $e) {
        logEvent('email_error', "Falha ao enviar e-mail. Destino: $to. Erro: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Função para formatar e enviar o email com as credenciais.
 */
function sendHotspotCredentialsEmail($email, $customer_name ,$username, $password, $expiresAt, $planName) {
    $subject = "Suas Credenciais WiFi - Pagamento Aprovado!";
    
    // Formata a data de expiração
    $expiresText = $expiresAt 
        ? date('d/m/Y H:i:s', strtotime($expiresAt)) 
        : 'Seu acesso é ilimitado.';

    // Monta o corpo em HTML
    $body = "
        <html>
        <head>
            <title>$subject</title>
        </head>
        <body>
            <h1>Acesso WiFi Liberado!</h1>
            <p>Olá $customer_name,</p>
            <p>Seja muito bem-vindo(a) à rede Wi-fi Barato !</p>
            <p>Seu pagamento foi aprovado e seu acesso ao plano <strong>$planName</strong> está ativo.</p>
            <p>Use as credenciais abaixo para se conectar à nossa rede:</p>
            
            <div style='background: #f4f4f4; padding: 15px; border-radius: 5px; border: 1px solid #ddd; max-width: 400px;'>
                <p><strong>Usuário:</strong> $username</p>
                <p><strong>Senha:</strong> $password</p>
                <p><strong>Expira em:</strong> $expiresText</p>
            </div>
            
            <p style='margin-top: 20px;'>Obrigado por utilizar nosso serviço!</p>
            <p>Atenciosamente,<br>Wi-Fi Barato by Wi Guest Portal</p>
        </body>
        </html>
    ";

    return sendEmail($email, $subject, $body);
}

?>