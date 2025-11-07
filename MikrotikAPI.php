<?php
// MikrotikAPI.php - VERSÃO FINAL COM BYPASS VIA ADDRESS LIST

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

        if ($this->api->connect($this->host, $this->user, $this->pass)) {
            $this->connected = true;
            return true;
        } else {
            logEvent('mikrotik_error', "Falha ao conectar na API do MikroTik: {$this->host}:{$this->port}");
            return false;
        }
    }

    // ======================================================================
    // NOVA LÓGICA DE BYPASS: Address List (PAGAMENTO_PENDENTE)
    // ======================================================================
    public function addClientBypass(int $transactionId): array {
        // 1. Conectar e obter o IP da transação no banco de dados
        if (!$this->connect()) {
            return ['success' => false, 'message' => 'Falha na conexão com o MikroTik.'];
        }

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT client_ip FROM transactions WHERE id = ?");
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch();

        if (!$transaction || $transaction['client_ip'] === '0.0.0.0') {
            return ['success' => false, 'message' => 'IP do cliente não encontrado para a transação.'];
        }
        
        $clientIp = $transaction['client_ip'];
        $addressList = 'PAGAMENTO_PENDENTE';
        $timeout = '00:02:00'; // 2 minutos conforme solicitado
        $comment = "TX: $transactionId - Checkout InfinitePay";

        // 2. Adicionar IP à Address List (Acesso liberado via Firewall)
        $response = $this->api->comm('/ip/firewall/address-list/add', [
            'list' => $addressList,
            'address' => $clientIp,
            'timeout' => $timeout,
            'comment' => $comment
        ]);

        if (isset($response['!trap'])) {
            $error = $response['!trap']['message'] ?? 'Erro RouterOS ao adicionar à Address List.';
            logEvent('mikrotik_error', "Falha ao adicionar Address List. IP: $clientIp. Erro: " . $error);
            return ['success' => false, 'message' => $error];
        }
        
        // Retorna o ID da Address List (ou o IP como fallback)
        $bypassId = $response[0]['.id'] ?? $clientIp; 

        return [
            'success' => true,
            'message' => "Bypass (Address List) adicionado para $clientIp.",
            'bypass_id' => $bypassId 
        ];
    }
    
    public function removeClientBypass(int $transactionId): array {
        if (!$this->connect()) {
            return ['success' => false, 'message' => 'Falha na conexão com o MikroTik.'];
        }
        
        $addressList = 'PAGAMENTO_PENDENTE';
        $commentPattern = "TX: $transactionId - Checkout InfinitePay"; // Usar o mesmo padrão de comentário

        // 1. Procurar o registro na Address List pelo comentário
        $find = $this->api->comm('/ip/firewall/address-list/find', [
            'comment' => $commentPattern
        ]);
        
        if (empty($find)) {
            logEvent('mikrotik_info', "Registro Address List não encontrado para remoção. TX: $transactionId");
            return ['success' => true, 'message' => 'Endereço não encontrado na lista para remoção.'];
        }
        
        $bypassId = $find[0]['.id'];
        
        // 2. Remover o registro
        $response = $this->api->comm('/ip/firewall/address-list/remove', [
            '.id' => $bypassId
        ]);

        if (isset($response['!trap'])) {
            $error = $response['!trap']['message'] ?? 'Erro RouterOS ao remover Address List.';
            logEvent('mikrotik_error', "Falha ao remover Address List. ID: $bypassId. Erro: " . $error);
            return ['success' => false, 'message' => $error];
        }
        
        return ['success' => true, 'message' => "Endereço removido da lista $addressList."];
    }

    // ======================================================================
    // CÓDIGO ANTIGO DE PROVISIONAMENTO (MANTIDO)
    // ======================================================================

    public function provisionHotspotUser(int $planId, string $clientIp): array {
        // ... (Mantenha sua lógica de provisionamento de usuário aqui) ...
        // Este código será chamado no Webhook APÓS o pagamento ser APROVADO
        // ...
        return [
            'success' => true,
            'message' => 'Usuário provisionado com sucesso.',
            'username' => 'exemplo_user',
            'password' => 'exemplo_pass',
            'mikrotik_profile' => 'exemplo_profile'
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
            $this->api->disconnect();
        }
    }
}

// Classe de API RouterOS (assumindo que está em routeros_api.class.php)
if (!class_exists('routeros_api')) {
    // Código para carregar a classe, se necessário.
}
?>