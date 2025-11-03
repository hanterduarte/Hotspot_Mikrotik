<?php
// MikrotikAPI.php - CLASSE FINAL (Corrigido o nome da coluna DB e a Instanciação da API)

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
