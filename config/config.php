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

// Tratamento de erros global (agora usa a função de helpers.php)
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (function_exists('logEvent')) {
        logEvent('error', "Error [$errno]: $errstr in $errfile on line $errline");
    }
});

set_exception_handler(function($exception) {
    if (function_exists('logEvent')) {
        logEvent('exception', $exception->getMessage());
    }

    // Evita quebrar em ambiente CLI e exibe um erro genérico na web
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Ocorreu um erro interno. Por favor, tente novamente.']);
    }
});