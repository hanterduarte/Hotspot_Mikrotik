<?php
// MikrotikAPI.php - Classe para comunicação com MikroTik RouterOS

require_once 'routeros_api.class.php'; 
// As funções logEvent() e getSetting() estão disponíveis via config.php

class MikrotikAPI {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $connected = false;
    private $api; // Usaremos a classe routeros_api internamente
    
    public function __construct($host = null, $user = null, $pass = null, $port = 8728) {
        // Assume que getSetting() está definido em config.php e busca as configurações
        $this->host = $host ?: getSetting('mikrotik_host', '192.168.1.57');
        $this->port = $port ?: getSetting('mikrotik_port', 8728);
        $this->user = $user ?: getSetting('mikrotik_user', 'api_user');
        $this->pass = $pass ?: getSetting('mikrotik_password', '300588');

        // Inicializa a classe routeros_api
        $this->api = new routeros_api();
        $this->api->port = $this->port;
    }
    
    public function connect() {
        if ($this->connected) {
            return true;
        }

        logEvent('mikrotik_info', "Tentando conectar em {$this->host}:{$this->port}");
        
        if ($this->api->connect($this->host, $this->user, $this->pass)) {
            $this->connected = true;
            return true;
        } else {
            logEvent('mikrotik_error', "Falha na conexão ou autenticação: " . $this->api->error_str);
            return false;
        }
    }
    
    // Método de comunicação reescrito para usar a classe comm() da routeros_api
    public function comm($command, $args = []) {
        if (!$this->connected && !$this->connect()) {
            throw new Exception('Não foi possível conectar ao MikroTik.');
        }
        return $this->api->comm($command, $args);
    }
    
    // --- MÉTODO: Criação de Usuário Hotspot ---
    
    /**
     * Cria um usuário Hotspot no MikroTik
     * @param string $username
     * @param string $password
     * @param string $profile Nome do profile Hotspot (ex: 2h_acesso)
     * @param string $server Nome do Hotspot Server (ex: hotspot1)
     * @param string $comment Comentário para o usuário
     * @return array Resultado da operação
     */
    public function createHotspotUser($username, $password, $profile, $server, $comment) {
        if (!$this->connected && !$this->connect()) {
            return ['success' => false, 'message' => 'Não foi possível conectar ao MikroTik'];
        }

        try {
            $command = '/ip/hotspot/user/add';
            $args = [
                'name'     => $username,
                'password' => $password,
                'profile'  => $profile,
                'server'   => $server,
                'comment'  => $comment
            ];

            $response = $this->comm($command, $args);

            if (isset($response['!trap'])) {
                $message = $response['!trap']['message'] ?? 'Erro desconhecido ao criar usuário';
                logEvent('mikrotik_error', "Falha ao criar usuário $username: $message");
                return ['success' => false, 'message' => $message];
            }

            logEvent('mikrotik_success', "Usuário Hotspot $username criado com sucesso (Perfil: $profile)");
            return ['success' => true];

        } catch (Exception $e) {
            logEvent('mikrotik_exception', "Exceção ao criar usuário: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // --- Métodos Existentes ---

    public function removeUser($username) {
        if (!$this->connected && !$this->connect()) {
            return ['success' => false, 'message' => 'Não foi possível conectar ao MikroTik'];
        }
        
        try {
            // Buscar o ID
            $response = $this->comm('/ip/hotspot/user/print', ['?name' => $username, '.proplist' => '.id']);
            
            if (empty($response) || !isset($response[0]['.id'])) {
                return ['success' => false, 'message' => 'Usuário não encontrado para remoção'];
            }
            
            $userId = $response[0]['.id'];
            
            // Remover usuário
            $this->comm('/ip/hotspot/user/remove', ['.id' => $userId]);
            
            logEvent('mikrotik_success', "Usuário $username removido com sucesso (ID: $userId)");
            return ['success' => true, 'message' => 'Usuário removido com sucesso'];
            
        } catch (Exception $e) {
            logEvent('mikrotik_error', "Exceção ao remover usuário: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function userExists($username) {
        if (!$this->connected && !$this->connect()) {
            return false;
        }
        
        try {
            $response = $this->comm('/ip/hotspot/user/print', ['?name' => $username, '.proplist' => '.id']);
            return !empty($response) && isset($response[0]['.id']);
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function __destruct() {
        if ($this->connected) {
            $this->api->disconnect();
        }
    }
}