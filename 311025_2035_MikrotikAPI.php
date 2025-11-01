<?php
// MikrotikAPI.php - Classe para comunicação Hotspot com MikroTik RouterOS (usando routeros_api.class.php)

// IMPORTANTE:
// 1. Você deve garantir que o arquivo 'routeros_api.class.php' exista no mesmo diretório.
// 2. O config.php é necessário para a conexão, log e funções utilitárias (getSetting, generatePassword, etc.).
require_once 'routeros_api.class.php'; 
require_once 'config.php'; 

class MikrotikAPI {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $api; // Instância da classe RouterosAPI
    private $connected = false;
    private $configError = null; // Propriedade para armazenar erro de configuração
    
    /**
     * Construtor, obtém credenciais do config.php (tabela settings).
     * Usa NULL/0 como fallback e armazena erro se credenciais ausentes.
     */
    public function __construct() {
        // Valores default agora são NULL ou 0 (para porta), forçando o uso do DB por questões de segurança.
        $this->host = getSetting('mikrotik_host', null);
        $this->port = getSetting('mikrotik_port', 0); 
        $this->user = getSetting('mikrotik_user', null);
        $this->pass = getSetting('mikrotik_password', null);
        
        // 1. Validação de Credenciais
        if (empty($this->host) || empty($this->user) || empty($this->pass) || $this->port == 0) {
            $this->configError = "As configurações do MikroTik (Host, Porta, Usuário, Senha) não foram encontradas no banco de dados. Por favor, parametrize-as na tabela 'settings' do sistema.";
            logEvent('mikrotik_fatal_error', $this->configError);
            return; 
        }

        // 2. Inicializa a classe RouterosAPI
        $this->api = new RouterosAPI();
        $this->api->debug = false; 
    }

    /**
     * Retorna o erro de configuração, se houver.
     * @return string|null
     */
    public function getConfigError() {
        return $this->configError;
    }
    
    /**
     * Tenta conectar e autenticar no MikroTik.
     * @return bool
     */
    public function connect() {
        // Retorna o erro de configuração imediatamente se as credenciais estiverem faltando
        if ($this->configError !== null) {
            return false;
        }

        if ($this->connected) {
            return true;
        }
        
        try {
            if ($this->api->connect($this->host, $this->user, $this->pass, $this->port)) {
                $this->connected = true;
                return true;
            } else {
                logEvent('mikrotik_error', "Falha de conexão/autenticação no MikroTik em {$this->host}: Verifique credenciais de acesso ou problemas de rede.");
                return false;
            }
        } catch (Exception $e) {
            logEvent('mikrotik_error', "Exceção na conexão MikroTik: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Envia o comando para criar um novo usuário Hotspot no RouterOS.
     * @param string $username
     * @param string $password
     * @param string $profile
     * @param string $hotspotServer O nome do servidor hotspot onde o usuário deve ser criado.
     * @param string $comment O texto de comentário que será gravado no MikroTik.
     * @return array ['success' => bool, 'message' => string]
     */
    private function createRouterOSHotspotUser($username, $password, $profile, $hotspotServer, $comment) {
        // Verifica se a conexão está ativa na API
        if (!$this->api->connected) {
             return ['success' => false, 'message' => 'Conexão com MikroTik perdida.'];
        }
        
        try {
            $add_command = [
                '/ip/hotspot/user/add',
                '=name=' . $username,
                '=password=' . $password,
                '=profile=' . $profile,
                '=server=' . $hotspotServer,
                '=comment=' . $comment // AJUSTADO: Usando o valor de $comment passado
            ];
            
            $response = $this->api->comm($add_command);
            
            // Sucesso (array vazio ou array com o ID)
            if (empty($response) || (isset($response[0]['!re']) && isset($response[0]['.id']))) {
                return ['success' => true, 'message' => 'Usuário Hotspot criado com sucesso no RouterOS.'];
            }
            
            // Trata erro (!trap)
            if (isset($response[0]['!trap'])) {
                $errorMessage = $response[0]['!trap'][0]['message'] ?? 'Erro desconhecido ao adicionar usuário.';
                logEvent('mikrotik_error', "Falha ao criar usuário: " . $errorMessage);
                return ['success' => false, 'message' => "Erro do MikroTik: " . $errorMessage];
            }
            
            return ['success' => false, 'message' => 'Resposta inesperada do MikroTik ao criar usuário.'];
            
        } catch (Exception $e) {
            logEvent('mikrotik_error', "Exceção ao criar usuário: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Provisiona o usuário no Hotspot, busca o perfil correto e salva as credenciais no DB.
     * @param PDO $db Conexão ativa com o banco de dados.
     * @param array $transaction Dados da transação (incluindo id e plan_id).
     * @return array Resultado da operação, incluindo credenciais em caso de sucesso.
     */
    public function createHotspotUserMikrotik($db, $transaction) {
        
        // 0. Verifica Erro de Configuração antes de tentar conectar
        if ($this->getConfigError() !== null) {
            return ['success' => false, 'message' => $this->getConfigError()];
        }

        // 0. Tenta conectar
        if (!$this->connect()) {
            return ['success' => false, 'message' => 'Falha na conexão/autenticação com o MikroTik.'];
        }

        // 1. Buscar Profile e Duração na tabela PLANS
        $stmt = $db->prepare("SELECT mikrotik_profile, duration_seconds, name as plan_name FROM plans WHERE id = ?");
        $stmt->execute([$transaction['plan_id']]);
        $plan = $stmt->fetch();

        if (!$plan) {
            logEvent('mikrotik_error', 'Profile do MikroTik não configurado para o Plan ID: ' . $transaction['plan_id']);
            return ['success' => false, 'message' => 'Erro: Perfil do MikroTik não encontrado/configurado para este plano.'];
        }

        $profile = $plan['mikrotik_profile'];
        $durationSeconds = intval($plan['duration_seconds']);
        
        // 2. Obter configurações do Hotspot
        $hotspotServer = getSetting('hotspot_server', null);
        $commentText = getSetting('coment_user_hotspot', 'Provisionamento Automatico'); // NOVO: Pega o comentário da settings
        
        // Verifica se o servidor hotspot está configurado
        if (empty($hotspotServer)) {
            $message = 'Erro de configuração: O parâmetro "hotspot_server" não está definido na tabela "settings".';
            logEvent('mikrotik_error', $message);
            return ['success' => false, 'message' => $message];
        }

        // 3. Gerar credenciais aleatórias (chamando funções utilitárias do config.php)
        $username = generateUsername('cliente');
        $password = generatePassword(10); 
        $expiresAt = date('Y-m-d H:i:s', time() + $durationSeconds);
        
        // 4. Criar o usuário no MikroTik (Chamando o método RouterOS)
        $mikrotikResult = $this->createRouterOSHotspotUser(
            $username, 
            $password, 
            $profile, 
            $hotspotServer, 
            $commentText // PASSANDO O TEXTO DE COMENTÁRIO DA SETTINGS
        );

        if (!$mikrotikResult['success']) {
            return ['success' => false, 'message' => 'Falha ao criar usuário no MikroTik: ' . $mikrotikResult['message']];
        }
        
        // 5. Inserir o usuário e as credenciais no banco de dados
        $stmt = $db->prepare("
            INSERT INTO hotspot_users (transaction_id, customer_id, plan_id, username, password, profile, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $insertSuccess = $stmt->execute([
            $transaction['id'],
            $transaction['customer_id'],
            $transaction['plan_id'],
            $username,
            $password,
            $profile,
            $expiresAt
        ]);

        if (!$insertSuccess) {
            logEvent('mikrotik_error', 'Falha ao salvar credenciais no DB para Transação ID: ' . $transaction['id']);
            return ['success' => false, 'message' => 'Falha ao salvar credenciais no DB. O usuário foi criado no MikroTik, mas o registro falhou.'];
        }

        // 6. Retorna as credenciais e o nome do plano
        return [
            'success' => true, 
            'message' => 'Usuário provisionado e salvo com sucesso.',
            'username' => $username,
            'password' => $password,
            'expires_at' => $expiresAt,
            'plan_name' => $plan['plan_name']
        ];
    }
    
    public function __destruct() {
        if ($this->connected) {
            $this->api->disconnect();
        }
    }
}
?>