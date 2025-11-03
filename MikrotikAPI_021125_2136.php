<?php
// MikrotikAPI.php - CLASSE FINAL (Adicionadas funções de IP Bypass)

// Dependência de configuração e funções utilitárias
require_once 'config.php'; 
require_once 'routeros_api.class.php';
// A classe 'routeros_api' deve ser incluída (require_once 'routeros_api.class.php';) no ponto de entrada do script.
// Não a incluímos aqui para evitar erro de redeclaração se já tiver sido carregada.

class MikrotikAPI {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $api = null; 
    private $connected = false;
    private $configError = null; 
    
    public function __construct() {
        // 1. Obter e Validar Credenciais do DB
        $this->host = getSetting('mikrotik_host', null);
        $this->port = getSetting('mikrotik_port', 0); 
        $this->user = getSetting('mikrotik_user', null);
        $this->pass = getSetting('mikrotik_password', null);
        
        if (empty($this->host) || empty($this->user) || empty($this->pass) || $this->port == 0) {
            $this->configError = "Credenciais do MikroTik incompletas na tabela settings.";
            return;
        }
        
        // 2. Instanciar a RouterosAPI (Usando 'routeros_api' minúsculo)
        if (class_exists('routeros_api')) { 
            $this->api = new routeros_api(); 
            $this->api->port = $this->port;
            $this->api->debug = true; 
        } else {
             $this->configError = "A classe 'routeros_api' não foi carregada. Verifique se o arquivo foi incluído antes da MikrotikAPI.";
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
        
        $error_detail = $this->api->error_str ?: 'Erro de conexão/autenticação não detalhado.';
        $this->configError = $error_detail; 
        if (function_exists('logEvent')) {
             logEvent('mikrotik_error', "Falha na conexão com MikroTik: " . $error_detail);
        }
        return false;
    }
    
    // ======================================================================
    // >>> NOVAS FUNÇÕES DE BYPASS VIA IP BINDINGS
    // ======================================================================

    /**
     * Tenta obter o IP real do cliente que está acessando o script (prioriza IPv4).
     * @return string O endereço IP (IPv4) ou '0.0.0.0' em caso de falha.
     */
    public static function getClientIP(): string {
        $ip = '0.0.0.0';
        $ip_candidates = [];

        // Verifica headers comuns em ambientes com proxy/hotspot
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip_candidates[] = $_SERVER['HTTP_CLIENT_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Pode ser uma lista separada por vírgula. Pega o primeiro (mais próximo do cliente).
            $forwarded_ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip_candidates[] = trim($forwarded_ips[0]);
        }
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip_candidates[] = $_SERVER['REMOTE_ADDR'];
        }

        foreach ($ip_candidates as $candidate) {
            // Filtra e valida apenas IPv4
            if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $candidate; // Retorna o primeiro IPv4 válido encontrado
            }
        }
        
        return $ip;
    }
    
    /**
     * Adiciona o IP do cliente no ip-binding com status 'bypassed'.
     * @param int $transactionId ID da transação para o comentário.
     * @return array Resultado da operação.
     */
    public function addClientBypass(int $transactionId): array {
        // 1. Obter o IP do cliente
        $clientIP = self::getClientIP();
        if ($clientIP === '0.0.0.0') {
            return ['success' => false, 'message' => 'Não foi possível determinar o IP (IPv4) do cliente.', 'client_ip' => $clientIP];
        }

        // 2. Conexão com o MikroTik
        if (!$this->connect()) {
            return ['success' => false, 'message' => 'Falha na conexão com o MikroTik: ' . ($this->configError ?? 'Erro desconhecido.')];
        }

        // 3. Enviar comando para o MikroTik
        $comment = "Bypass Temporario. Transacao ID: " . $transactionId;

        // O profile "default" é o valor padrão para ip-binding do Hotspot.
        $mikrotikResult = $this->mikrotikCommand(
            '/ip/hotspot/ip-binding/add',
            [
                'address' => $clientIP,
                'server'  => 'all', // Aplica a todos os Hotspots
                'comment' => $comment,
                'mac-address' => '00:00:00:00:00:00', // A MAC será descoberta automaticamente pelo MikroTik
                'type'    => 'bypassed' // Permite o acesso sem login
            ]
        );

        if (!$mikrotikResult['success']) {
            return ['success' => false, 'message' => 'Falha ao adicionar IP Binding no MikroTik: ' . $mikrotikResult['message'], 'client_ip' => $clientIP];
        }

        // 4. Buscar o ID do item adicionado (necessário para a remoção).
        $searchResult = $this->mikrotikCommand(
            '/ip/hotspot/ip-binding/print',
            ['?address' => $clientIP, '?comment' => $comment]
        );
        
        $bypassId = null;
        if ($searchResult['success'] && !empty($searchResult['data'])) {
             // Pega o ID do primeiro resultado
            $bypassId = $searchResult['data'][0]['.id'] ?? null; 
        }
        
        if (empty($bypassId)) {
             // Caso a busca por IP+Comentário falhe, mas o ADD tenha dado sucesso
            return ['success' => false, 'message' => 'IP Binding adicionado, mas falha ao recuperar o ID (.id) para futura remoção.', 'client_ip' => $clientIP];
        }
        
        return [
            'success' => true, 
            'message' => 'Bypass de IP adicionado com sucesso.',
            'client_ip' => $clientIP,
            'bypass_id' => $bypassId // Retorna o ID interno do RouterOS
        ];
    }
    
    /**
     * Remove um item do ip-binding pelo ID interno do RouterOS.
     * @param string $bypassId O ID (e.g., "*10") retornado pelo MikroTik.
     * @return array Resultado da operação.
     */
    public function removeBypass(string $bypassId): array {
        if (!$this->connect()) {
            return ['success' => false, 'message' => 'Falha na conexão com o MikroTik: ' . ($this->configError ?? 'Erro desconhecido.')];
        }
        
        $mikrotikResult = $this->mikrotikCommand(
            '/ip/hotspot/ip-binding/remove',
            ['.id' => $bypassId]
        );

        // O comando remove retorna um array vazio em caso de sucesso.
        if ($mikrotikResult['success']) {
            return ['success' => true, 'message' => 'Bypass de IP removido com sucesso.'];
        }
        
        // Se falhar, o mikrotikCommand já captura o erro
        return $mikrotikResult; 
    }
    
    // ======================================================================
    // <<< FIM DAS NOVAS FUNÇÕES
    // ======================================================================
    
    /**
     * Cria um usuário Hotspot no MikroTik e o salva no DB.
     * @param array $transaction Dados da transação (id, customer_id, plan_id, etc.)
     * @return array Resultado da operação
     */
    public function createHotspotUser(array $transaction): array {
        // 1. Conexão com o MikroTik (se não estiver conectado)
        if (!$this->connect()) {
            return ['success' => false, 'message' => 'Falha na conexão com o MikroTik: ' . ($this->configError ?? 'Erro desconhecido.')];
        }

        // 2. Buscar dados do plano e do servidor
        $db = Database::getInstance()->getConnection();
        
        // Corrigido para buscar 'mikrotik_profile' da tabela plans
        $stmt = $db->prepare("SELECT mikrotik_profile FROM plans WHERE id = ?");
        $stmt->execute([$transaction['plan_id']]);
        $plan = $stmt->fetch();
        $hotspot_server = getSetting('hotspot_server', 'all'); 

        if (!$plan) {
            return ['success' => false, 'message' => 'Plano não encontrado.'];
        }

        // 3. Preparar credenciais e validade
        $username = generateUsername('user'); 
        $password = generatePassword(8); 
        // O valor do perfil é pego de 'mikrotik_profile' da tabela plans
        $profile = $plan['mikrotik_profile']; 
        $comment = "Venda ID: " . $transaction['id'];

        // 4. Enviar comando para o MikroTik
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
            return ['success' => false, 'message' => 'Falha ao criar usuário no MikroTik: ' . $mikrotikResult['message']];
        }
        
        // 5. Inserir o usuário e as credenciais no banco de dados
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
            $profile, // O valor do perfil MikroTik
        ]);

        if (!$insertSuccess) {
            if (function_exists('logEvent')) {
                 logEvent('mikrotik_error', 'Falha ao salvar credenciais no DB para Transação ID: ' . $transaction['id']);
            }
            return ['success' => false, 'message' => 'Falha ao salvar credenciais no DB. O usuário foi criado no MikroTik, mas o registro falhou.'];
        }

        // 6. Retorna o resultado
        return [
            'success' => true, 
            'message' => 'Usuário provisionado e salvo com sucesso.',
            'username' => $username,
            'password' => $password,
            'mikrotik_profile' => $plan['mikrotik_profile']
        ];
    }
    
    public function mikrotikCommand(string $command, array $args = []): array {
        if (!$this->connected || $this->api === null) {
            return ['success' => false, 'message' => 'API não conectada ou inicializada.']; 
        }
        
        $response = $this->api->comm($command, $args);

        if (isset($response['!trap'])) {
            $error = $response['!trap']['message'] ?? 'Erro RouterOS sem detalhes (!trap).';
            if (function_exists('logEvent')) {
                 logEvent('mikrotik_error', "Comando '$command' falhou. Erro: " . $error);
            }
            return ['success' => false, 'message' => $error];
        }

        return ['success' => true, 'data' => $response];
    }

    public function getError(): ?string { return $this->configError; }
    
    public function __destruct() {
        if ($this->connected && $this->api !== null) {
            $this->api->disconnect();
        }
    }
}