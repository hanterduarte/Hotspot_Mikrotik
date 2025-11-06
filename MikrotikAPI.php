<?php
// MikrotikAPI.php - VERSÃO FINAL (Lê IP/MAC da tabela transactions)

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
        }
        
        $error_detail = $this->api->error_str ?: 'Erro de conexão não detalhado.';
        $this->configError = $error_detail; 
        if (function_exists('logEvent')) {
             logEvent('mikrotik_error', "Falha na conexão: " . $error_detail);
        }
        return false;
    }
    
    // ======================================================================
    // FUNÇÕES DE BYPASS VIA IP BINDINGS
    // ======================================================================

    /**
     * Adiciona o IP do cliente no ip-binding com status 'bypassed'.
     * ATUALIZADO: Lê IP e MAC da tabela transactions
     * 
     * @param int $transactionId ID da transação
     * @return array Resultado da operação com bypass_id
     */
    public function addClientBypass(int $transactionId): array {
        // 1. Buscar IP e MAC da tabela transactions
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT client_ip, client_mac FROM transactions WHERE id = ?");
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch();
        
        if (!$transaction) {
            return [
                'success' => false,
                'message' => "Transação ID $transactionId não encontrada no banco de dados."
            ];
        }
        
        $clientIP = $transaction['client_ip'] ?? '0.0.0.0';
        $clientMAC = $transaction['client_mac'] ?? '00:00:00:00:00:00';
        
        // Validar IP
        if (!filter_var($clientIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || $clientIP === '0.0.0.0') {
            return [
                'success' => false,
                'message' => "IP inválido na transação: $clientIP",
                'client_ip' => $clientIP
            ];
        }
        
        // Validar MAC (formato básico)
        if (!preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $clientMAC)) {
            // Se MAC for inválido, usa o padrão (MikroTik descobrirá)
            $clientMAC = '00:00:00:00:00:00';
            logEvent('mikrotik_warning', "MAC inválido na transação $transactionId. Usando padrão.");
        }

        logEvent('mikrotik_info', "Adicionando bypass. Transaction: $transactionId | IP: $clientIP | MAC: $clientMAC");

        // 2. Conectar ao MikroTik
        if (!$this->connect()) {
            return [
                'success' => false,
                'message' => 'Falha na conexão com o MikroTik: ' . ($this->configError ?? 'Erro desconhecido.')
            ];
        }

        // 3. Adicionar IP Binding no MikroTik
        $comment = "Bypass Temporario - TX: " . $transactionId;

        $mikrotikResult = $this->mikrotikCommand(
            '/ip/hotspot/ip-binding/add',
            [
                'address' => $clientIP,
                'server'  => 'all',
                'comment' => $comment,
                'mac-address' => $clientMAC,
                'type'    => 'bypassed'
            ]
        );

        if (!$mikrotikResult['success']) {
            return [
                'success' => false,
                'message' => 'Falha ao adicionar IP Binding: ' . $mikrotikResult['message'],
                'client_ip' => $clientIP,
                'client_mac' => $clientMAC
            ];
        }

        // 4. Buscar o ID (.id) do binding recém-criado
        $searchResult = $this->mikrotikCommand(
            '/ip/hotspot/ip-binding/print',
            ['?address' => $clientIP, '?comment' => $comment]
        );
        
        $bypassId = null;
        if ($searchResult['success'] && !empty($searchResult['data'])) {
            $bypassId = $searchResult['data'][0]['.id'] ?? null;
        }
        
        if (empty($bypassId)) {
            return [
                'success' => false,
                'message' => 'IP Binding adicionado, mas falha ao recuperar o ID (.id).',
                'client_ip' => $clientIP
            ];
        }
        
        logEvent('mikrotik_success', "Bypass criado. ID: $bypassId | IP: $clientIP");
        
        return [
            'success' => true,
            'message' => 'Bypass de IP adicionado com sucesso.',
            'bypass_id' => $bypassId,
            'client_ip' => $clientIP,
            'client_mac' => $clientMAC
        ];
    }
    
    /**
     * Remove um item do ip-binding pelo ID interno do RouterOS.
     * @param string $bypassId O ID do binding (ex: "*10")
     * @return array Resultado da operação
     */
    public function removeBypass(string $bypassId): array {
        if (empty($bypassId)) {
            return [
                'success' => false,
                'message' => 'ID do bypass não fornecido.'
            ];
        }

        if (!$this->connect()) {
            return [
                'success' => false,
                'message' => 'Falha na conexão com o MikroTik: ' . ($this->configError ?? 'Erro desconhecido.')
            ];
        }
        
        logEvent('mikrotik_info', "Removendo bypass ID: $bypassId");
        
        $mikrotikResult = $this->mikrotikCommand(
            '/ip/hotspot/ip-binding/remove',
            ['.id' => $bypassId]
        );

        if ($mikrotikResult['success']) {
            logEvent('mikrotik_success', "Bypass ID $bypassId removido com sucesso.");
            return ['success' => true, 'message' => 'Bypass removido com sucesso.'];
        }
        
        return [
            'success' => false,
            'message' => 'Falha ao remover bypass: ' . $mikrotikResult['message']
        ];
    }
    
    // ======================================================================
    // FUNÇÕES DE USUÁRIO HOTSPOT
    // ======================================================================
    
    /**
     * Cria um usuário Hotspot no MikroTik e o salva no DB.
     * @param array $transaction Dados da transação
     * @return array Resultado da operação
     */
    public function createHotspotUser(array $transaction): array {
        if (!$this->connect()) {
            return [
                'success' => false,
                'message' => 'Falha na conexão com o MikroTik: ' . ($this->configError ?? 'Erro desconhecido.')
            ];
        }

        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT mikrotik_profile FROM plans WHERE id = ?");
        $stmt->execute([$transaction['plan_id']]);
        $plan = $stmt->fetch();
        $hotspot_server = getSetting('hotspot_server', 'all'); 

        if (!$plan) {
            return ['success' => false, 'message' => 'Plano não encontrado.'];
        }

        $username = generateUsername('user'); 
        $password = generatePassword(8); 
        $profile = $plan['mikrotik_profile']; 
        $comment = "Venda ID: " . $transaction['id'];

        $mikrotikResult = $this->mikrotikCommand(
            '/ip/hotspot/user/add',
            [
                'name'     => $username,
                'password' => $password,
                'profile'  => $profile, 
                'comment'  => $comment,
                'server'   => $hotspot_server
            ]
        );

        if (!$mikrotikResult['success']) {
            return [
                'success' => false,
                'message' => 'Falha ao criar usuário no MikroTik: ' . $mikrotikResult['message']
            ];
        }
        
        $stmt = $db->prepare("
            INSERT INTO hotspot_users (transaction_id, customer_id, plan_id, username, password, mikrotik_profile)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $insertSuccess = $stmt->execute([
            $transaction['id'],
            $transaction['customer_id'],
            $transaction['plan_id'],
            $username,
            $password,
            $profile,
        ]);

        if (!$insertSuccess) {
            if (function_exists('logEvent')) {
                 logEvent('mikrotik_error', 'Falha ao salvar credenciais no DB. TX: ' . $transaction['id']);
            }
            return [
                'success' => false,
                'message' => 'Usuário criado no MikroTik, mas falha ao salvar no DB.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Usuário provisionado com sucesso.',
            'username' => $username,
            'password' => $password,
            'mikrotik_profile' => $plan['mikrotik_profile']
        ];
    }
    
    // ======================================================================
    // COMANDO GENÉRICO MIKROTIK
    // ======================================================================
    
    public function mikrotikCommand(string $command, array $args = []): array {
        if (!$this->connected || $this->api === null) {
            return ['success' => false, 'message' => 'API não conectada.']; 
        }
        
        $response = $this->api->comm($command, $args);

        if (isset($response['!trap'])) {
            $error = $response['!trap']['message'] ?? 'Erro RouterOS sem detalhes.';
            if (function_exists('logEvent')) {
                 logEvent('mikrotik_error', "Comando '$command' falhou: " . $error);
            }
            return ['success' => false, 'message' => $error];
        }

        return ['success' => true, 'data' => $response];
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
?>