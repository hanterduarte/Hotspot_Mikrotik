<?php
// config.php - Configuração principal do sistema

// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'hotspot_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configurações do Sistema
// MANTENDO A URL DO SEU AMBIENTE DE PRODUÇÃO:
define('BASE_URL', 'https://wifibarato.maiscoresed.com.br/hotspot');
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

// Função para buscar configurações do DB
function getSetting($key) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result['setting_value'] ?? null;
    } catch (Exception $e) {
        // Em caso de erro de DB, retorna nulo. O logEvent ainda não está disponível aqui.
        return null;
    }
}

// Função para logar eventos (simplificada para este arquivo)
if (!function_exists('logEvent')) {
    function logEvent($type, $message, $transactionId = null) {
        // Implementação completa da função de log (presume-se que exista)
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("INSERT INTO logs (log_type, message, transaction_id) VALUES (?, ?, ?)");
            $stmt->execute([$type, $message, $transactionId]);
        } catch (Exception $e) {
            // Falha silenciosa se o log falhar
            error_log("LOG FAILED [$type]: $message");
        }
    }
}

// Função para validar CPF
function validateCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', (string) $cpf);
    if (strlen($cpf) != 11) {
        return false;
    }

    // Verifica se todos os dígitos são iguais (ex: 111.111.111-11)
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

// Função para gerar UUIDs simples (Idempotency Key)
// Essencial para evitar o processamento duplicado de pagamentos na API.
if (!function_exists('generateUuid')) {
    function generateUuid() {
        return sprintf( '%04x%04x%04x%04x%04x%04x%04x%04x',
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0x0fff ) | 0x4000,
            mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }
}

// Tratamento de erros
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return;
    }

    $errorType = [
        E_ERROR => 'Fatal Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];

    $type = $errorType[$errno] ?? 'Unknown Error';
    $message = "[$type] $errstr in $errfile on line $errline";

    logEvent('php_error', $message);

    // Para erros fatais ou recuperáveis, pare a execução e informe o usuário (se não for AJAX)
    if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR])) {
        if (strpos($_SERVER['REQUEST_URI'], '.php') !== false) {
            // Em ambiente de produção, não mostrar detalhes do erro
            if (strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
                 jsonResponse(false, 'Um erro grave ocorreu. Tente novamente mais tarde.');
            } else {
                 http_response_code(500);
                 echo "<h1>Erro Crítico</h1><p>Ocorreu um erro inesperado no sistema. Por favor, tente novamente mais tarde.</p>";
            }
        }
    }
    return true; // Não executa o manipulador de erro padrão do PHP
});
?>