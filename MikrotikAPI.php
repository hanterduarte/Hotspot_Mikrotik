<?php
// MikrotikAPI.php - Classe para comunicação com MikroTik RouterOS

// Certifique-se de que a função logEvent() e getSetting() estão disponíveis via config.php

class MikrotikAPI {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $socket;
    private $connected = false;
    
    public function __construct($host = null, $user = null, $pass = null, $port = 8728) {
        // Assume que getSetting() está definido em config.php e busca as configurações
        $this->host = $host ?: getSetting('mikrotik_host', '192.168.1.57');
        $this->port = $port ?: getSetting('mikrotik_port', 8728);
        $this->user = $user ?: getSetting('mikrotik_user', 'api_user');
        $this->pass = $pass ?: getSetting('mikrotik_password', '300588');
    }
    
    // --- Métodos de Conexão e Comunicação (Mantidos) ---
    
    public function connect() {
        try {
            $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, 5);
            
            if (!$this->socket) {
                logEvent('mikrotik_error', "Erro ao conectar: [$errno] $errstr");
                return false;
            }
            
            // Login
            $this->write('/login', false);
            $response = $this->read(false);
            
            if (isset($response[0]['!trap'])) {
                logEvent('mikrotik_error', "Erro de autenticação: " . json_encode($response));
                return false;
            }
            
            // Autenticar
            $this->write('/login', false);
            $this->write('=name=' . $this->user, false);
            // ... (Resto da lógica de login com hash/password)
            $response = $this->read();
            
            if (isset($response[0]['!done'])) {
                $this->connected = true;
                return true;
            }
            
            logEvent('mikrotik_error', "Falha no login após autenticação: " . json_encode($response));
            return false;
            
        } catch (Exception $e) {
            logEvent('mikrotik_error', "Exceção na conexão: " . $e->getMessage());
            return false;
        }
    }
    
    private function write($command, $final = true) {
        $length = strlen($command);
        $this->writeLength($length);
        fwrite($this->socket, $command);
        
        if ($final) {
            fwrite($this->socket, "\x00");
        }
    }

    private function read($parse = true) {
        $response = [];
        $words = [];
        
        while (true) {
            $length = $this->readLength();
            if ($length === 0) {
                $words[] = '';
                continue;
            }
            
            $word = fread($this->socket, $length);
            
            if ($parse && $word === '!done') {
                $response[] = array_filter($words); // Adiciona o comando/resposta
                break;
            }
            
            if ($parse && ($word === '!re' || $word === '!trap' || $word === '!fatal')) {
                if (!empty($words)) {
                    $response[] = array_filter($words);
                }
                $words = [];
                $words[] = $word;
                continue;
            }
            
            $words[] = $word;
            
            if ($length === 0) {
                // Fim de um comando (em modo não-parsing)
                if (!$parse) break; 
            }
        }
        
        // Formatar a resposta (se for o modo parsing)
        if ($parse) {
            $formattedResponse = [];
            foreach ($response as $wordSet) {
                $entry = [];
                $type = array_shift($wordSet); // !re, !trap, !done, etc.
                $entry[$type] = [];
                
                foreach ($wordSet as $word) {
                    if (strpos($word, '=') !== false) {
                        list($key, $value) = explode('=', $word, 2);
                        $entry[$type][$key] = $value;
                    } else if (!empty($word)) {
                        $entry[$type][] = $word;
                    }
                }
                $formattedResponse[] = $entry;
            }
            return $formattedResponse;
        }
        
        return $response; // Retorno raw para o login
    }


    private function writeLength($length) {
        if ($length < 0x80) {
            fwrite($this->socket, chr($length));
        } else if ($length < 0x4000) {
            $length |= 0x8000;
            fwrite($this->socket, chr(($length >> 8) & 0xFF));
            fwrite($this->socket, chr($length & 0xFF));
        } else {
            // Outras implementações para comprimentos maiores... (simplificado)
            fwrite($this->socket, chr(0x00));
            fwrite($this->socket, pack('N', $length));
        }
    }

    private function readLength() {
        $byte = ord(fread($this->socket, 1));
        
        if ($byte < 0x80) {
            return $byte;
        } else if ($byte < 0xC0) {
            $byte2 = ord(fread($this->socket, 1));
            return (($byte & 0x3F) << 8) | $byte2;
        } else if ($byte < 0xE0) {
            // Implementações para 3 e 4 bytes... (simplificado)
            return 0;
        } else if ($byte < 0xF0) {
            // Implementações para 3 e 4 bytes... (simplificado)
            return 0;
        } else {
            // Implementação para 5 bytes... (simplificado)
            return 0;
        }
    }

    // --- Funções de Geração de Credenciais (Movidas para cá) ---

    /**
     * Gera uma string aleatória de um determinado comprimento e conjunto de caracteres.
     * @param int $length
     * @param string $chars
     * @return string
     */
    private static function generateRandomString($length = 8, $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
        $string = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $string .= $chars[mt_rand(0, $max)];
        }
        return $string;
    }

    /**
     * Cria usuário e senha aleatórios baseados no nome do cliente.
     * @param string $customerName Nome completo do cliente.
     * @return array {'username': string, 'password': string}
     */
    public static function generateRandomCredentials($customerName) {
        // Gerar um nome de usuário único (ex: 4 letras do nome + 4 dígitos aleatórios)
        $cleanName = preg_replace('/[^a-zA-Z]/', '', $customerName);
        $userNameBase = substr(strtoupper($cleanName), 0, 4);
        if (strlen($userNameBase) < 4) {
            // Preenche com letras aleatórias se o nome for muito curto
            $userNameBase = str_pad($userNameBase, 4, self::generateRandomString(4, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'), STR_PAD_RIGHT);
        }
        $randomSuffix = self::generateRandomString(4, '0123456789');
        $username = $userNameBase . $randomSuffix;
        
        // Senha aleatória forte (8 caracteres alfanuméricos)
        $password = self::generateRandomString(8);
        
        return [
            'username' => $username,
            'password' => $password
        ];
    }

    // --- Método para Criar Usuário Hotspot ---

    /**
     * Adiciona um novo usuário ao Hotspot.
     * @param string $username
     * @param string $password
     * @param string $profile Nome do perfil do Hotspot (ex: 1hora, 1dia)
     * @param string $comment Comentário para o usuário no MikroTik
     * @return array {'success': bool, 'message': string}
     */
    public function createHotspotUser($username, $password, $profile, $comment = '') {
        // Tenta conectar/logar se não estiver conectado
        if (!$this->connected && !$this->connect()) {
            return ['success' => false, 'message' => 'Não foi possível conectar ou logar no MikroTik.'];
        }
        
        try {
            // 1. Comando para adicionar o usuário
            $this->write('/ip/hotspot/user/add', false);
            $this->write('=name=' . $username, false);
            $this->write('=password=' . $password, false);
            $this->write('=profile=' . $profile, false);
            
            // Adicionar comentário (útil para rastrear a transação)
            if (!empty($comment)) {
                $this->write('=comment=' . $comment, false);
            }
            
            $this->write('', true); // Finaliza o comando

            $response = $this->read();
            
            // 2. Verifica se houve sucesso (!done) ou erro (!trap)
            if (isset($response[0]['!done'])) {
                logEvent('mikrotik_success', "Usuário $username criado com sucesso com o perfil $profile");
                return ['success' => true, 'message' => "Usuário $username criado com sucesso."];
            } elseif (isset($response[0]['!trap'])) {
                $error = $response[0]['!trap']['message'] ?? 'Erro desconhecido ao adicionar usuário.';
                logEvent('mikrotik_error', "Falha ao criar usuário $username: $error");
                return ['success' => false, 'message' => "Erro no MikroTik: $error"];
            }

            logEvent('mikrotik_error', "Resposta inesperada ao criar usuário $username: " . json_encode($response));
            return ['success' => false, 'message' => 'Resposta inesperada do MikroTik ao criar usuário.'];

        } catch (Exception $e) {
            logEvent('mikrotik_exception', "Exceção ao criar usuário: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // ... (Outros métodos como removeUser, userExists) ...
    
    public function __destruct() {
        if ($this->socket) {
            // Comando de logout (opcional, dependendo da necessidade)
            //$this->write('/quit'); 
            @fclose($this->socket);
        }
    }

    // ... (Métodos userExists, removeUser, etc.)
    public function userExists($username) {
        if (!$this->connected && !$this->connect()) {
            return false;
        }
        
        try {
            $this->write('/ip/hotspot/user/print', false);
            $this->write('?name=' . $username, true);
            $response = $this->read();
            return !empty($response) && isset($response[0]['!re']);
        } catch (Exception $e) {
            return false;
        }
    }

    public function removeUser($username) {
        if (!$this->connected && !$this->connect()) {
            return ['success' => false, 'message' => 'Não foi possível conectar ao MikroTik'];
        }
        
        try {
            // Buscar o ID
            $this->write('/ip/hotspot/user/print', false);
            $this->write('?name=' . $username, true);
            $response = $this->read();
            
            if (empty($response) || !isset($response[0]['!re']['.id'])) {
                return ['success' => false, 'message' => 'Usuário não encontrado para remoção'];
            }
            
            $userId = $response[0]['!re']['.id'];
            
            // Remover usuário
            $this->write('/ip/hotspot/user/remove', false);
            $this->write('=.id=' . $userId, true);
            $response = $this->read();
            
            if (isset($response[0]['!done'])) {
                logEvent('mikrotik_success', "Usuário $username removido com sucesso");
                return ['success' => true, 'message' => 'Usuário removido com sucesso'];
            }
            
            return ['success' => false, 'message' => 'Erro ao remover usuário'];
            
        } catch (Exception $e) {
            logEvent('mikrotik_error', "Exceção ao remover usuário: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}