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

// Carrega a classe de conexão com o banco de dados
require_once ROOT_PATH . '/app/models/Database.php';

// Carrega funções auxiliares
require_once ROOT_PATH . '/app/helpers.php';

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


// ----------------------------------------------------------------------
// FUNÇÕES DE SERVIÇO (EMAIL)
// ----------------------------------------------------------------------


/**
 * Função placeholder para envio de e-mail. 
 * Recomenda-se usar PHPMailer ou um serviço de SMTP externo.
 */
function sendEmail($to, $subject, $body) {
    // Configuração base (ajuste 'seudominio.com.br' nas settings)
    $domain = getSetting('base_domain', 'wifibarato.maiscoresed.com.br');
    $headers = "From: WiFi Barato <noreply@$domain>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    
    // A função mail() nativa precisa de um servidor de e-mail configurado no PHP
    if (getSetting('enable_email_sending', 'false') === 'true' && @mail($to, $subject, $body, $headers)) {
        logEvent('email_success', "Email de credenciais enviado para $to");
        return true;
    } else {
        logEvent('email_info', "Email de credenciais não enviado (Função mail() não usada ou desabilitada).");
        return false;
    }
}

/**
 * Função para formatar e enviar o email com as credenciais.
 */
function sendHotspotCredentialsEmail($email, $username, $password, $expiresAt, $planName) {
    $subject = "Suas Credenciais WiFi - Pagamento Aprovado!";
    $body = "
        <html>
        <head>
            <title>$subject</title>
        </head>
        <body>
            <h1>Acesso WiFi Liberado!</h1>
            <p>Seu pagamento foi aprovado e seu acesso ao plano <strong>$planName</strong> está ativo.</p>
            <p>Use as credenciais abaixo para se conectar à nossa rede:</p>
            
            <div style='background: #f4f4f4; padding: 15px; border-radius: 5px; border: 1px solid #ddd; max-width: 400px;'>
                <p><strong>Usuário:</strong> $username</p>
                <p><strong>Senha:</strong> $password</p>
                <p><strong>Expira em:</strong> " . date('d/m/Y H:i:s', strtotime($expiresAt)) . "</p>
            </div>
            
            <p>Obrigado!</p>
        </body>
        </html>
    ";
    
    return sendEmail($email, $subject, $body);
}

?>