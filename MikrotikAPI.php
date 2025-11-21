<?php
// MikrotikAPI.php - VERSÃO FINAL COM LÓGICA DE PROVISIONAMENTO

require_once 'config.php'; 
require_once 'routeros_api.class.php';

class MikrotikAPI {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $api = null; 
    private $connected = false;
    private $configError = null; 
    
    public function __construct() {
        $this->host = getSetting('mikrotik_host', null);
        $this->port = getSetting('mikrotik_port', 0); 
        $this->user = getSetting('mikrotik_user', null);
        $this->pass = getSetting('mikrotik_password', null);
        
        if (empty($this->host) || empty($this->user) || empty($this->pass) || $this->port == 0) {
            $this->configError = "Credenciais do MikroTik incompletas na tabela settings.";
            return;
        }
        
        if (class_exists('routeros_api')) { 
            $this->api = new routeros_api(); 
            $this->api->port = $this->port;
        } else {
             $this->configError = "A classe 'routeros_api' não foi carregada.";
        }
    }

    public function connect(): bool {
        if ($this->configError !== null || $this->api === null) {
            return false;
        }

        if ($this->connected) {
            return true;
        }
        
        if ($this->api->connect($this->host, $this->user, $this->pass)) {
            $this->connected = true;
            return true;
        } else {
            $this->configError = "Falha ao conectar ao MikroTik em {$this->host}:{$this->port}.";
            return false;
        }
    }

    // ======================================================================
    // PROVISIONAMENTO DE USUÁRIO HOTSPOT
    // ======================================================================
    public function provisionHotspotUser(int $planId, string $clientIp): array {
        // 1. Conecta ao Mikrotik (Se ainda não estiver conectado)
        if (!$this->connect()) {
            return ['success' => false, 'message' => $this->getError() ?? 'Falha na conexão ao MikroTik.'];
        }

        // 2. Busca detalhes do plano no DB
        $db = Database::getInstance()->getConnection();
        // CORREÇÃO: Usando 'mikrotik_profile' e 'duration_seconds' (finalmente resolvendo o erro)
        $stmt_plan = $db->prepare("SELECT mikrotik_profile, duration_seconds FROM plans WHERE id = ?"); 
        $stmt_plan->execute([$planId]);
        $plan = $stmt_plan->fetch();

        if (!$plan) {
            logEvent('mikrotik_api_error', "Plano ID $planId não encontrado no DB.");
            return ['success' => false, 'message' => 'Plano não encontrado no sistema.'];
        }

        $profile = $plan['mikrotik_profile'] ?? 'default'; 
        $durationSeconds = intval($plan['duration_seconds']);
        
        // Define o limite de tempo no formato RouterOS (ex: '3600s', ou '0' para ilimitado)
        $uptimeLimit = $durationSeconds > 0 ? $durationSeconds . 's' : ''; 

        // 3. Gerar Username e Password
        $username = 'u' . substr(md5(uniqid(mt_rand(), true)), 0, 8);
        $password = substr(md5(uniqid(mt_rand(), true)), 0, 6);

        // 4. Adicionar Usuário Hotspot no Mikrotik
        $response = $this->api->comm('/ip/hotspot/user/add', [
            'name' => $username,
            'password' => $password,
            'profile' => $profile,
            'limit-uptime' => $uptimeLimit, 
            'comment' => "Plano ID: $planId - Cliente IP: $clientIp" // IP pode ser vazio
        ]);

        if (isset($response['!trap'])) {
            $error = $response['!trap']['message'] ?? 'Erro RouterOS ao adicionar usuário Hotspot.';
            logEvent('mikrotik_error', "Falha ao adicionar usuário. Username: $username. Erro: " . $error);
            return ['success' => false, 'message' => $error];
        }
        
        return [
            'success' => true,
            'message' => 'Usuário provisionado com sucesso.',
            'username' => $username,
            'password' => $password,
            'mikrotik_profile' => $profile // Retorna o perfil usado para persistência no DB
        ];
    }
    
    // ======================================================================
    // COMANDO GENÉRICO MIKROTIK
    // ======================================================================
    
    public function mikrotikCommand(string $command, array $args = []): array {
        // ... (Mantenha sua função genérica aqui) ...
        return ['success' => true, 'data' => []];
    }

    public function getError(): ?string { 
        return $this->configError; 
    }
    
    public function __destruct() {
        if ($this->connected && $this->api !== null) {
            // $this->api->disconnect(); // Descomente se sua API RouterOS exigir
        }
    }
}

// Classe de API RouterOS (assumindo que está em routeros_api.class.php)
if (!class_exists('routeros_api')) {
    // Código para carregar a classe, se necessário.
}
?>