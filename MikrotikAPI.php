<?php
// MikrotikAPI.php - Classe para comunicação com MikroTik RouterOS

class MikrotikAPI {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $socket;
    private $connected = false;
    
    public function __construct($host = null, $user = null, $pass = null, $port = 8728) {
        $this->host = $host ?: getSetting('mikrotik_host', '192.168.88.1');
        $this->port = $port ?: getSetting('mikrotik_port', 8728);
        $this->user = $user ?: getSetting('mikrotik_user', 'admin');
        $this->pass = $pass ?: getSetting('mikrotik_password', '');
    }
    
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
            $this->write('=password=' . $this->pass);
            
            $response = $this->read(false);
            
            if (isset($response[0]['!trap'])) {
                logEvent('mikrotik_error', "Falha na autenticação");
                return false;
            }
            
            $this->connected = true;
            logEvent('mikrotik_success', "Conectado ao MikroTik com sucesso");
            return true;
            
        } catch (Exception $e) {
            logEvent('mikrotik_error', "Exceção ao conectar: " . $e->getMessage());
            return false;
        }
    }
    
    public function disconnect() {
        if ($this->socket) {
            fclose($this->socket);
            $this->connected = false;
        }
    }
    
    private function write($command, $param = true) {
        if ($param) {
            $command = str_replace('=', "\n=", $command);
            $commands = explode("\n", $command);
            foreach ($commands as $com) {
                $this->writeWord($com);
            }
            $this->writeWord('');
        } else {
            $this->writeWord($command);
        }
    }
    
    private function writeWord($word) {
        $len = strlen($word);
        if ($len < 0x80) {
            fwrite($this->socket, chr($len));
        } elseif ($len < 0x4000) {
            $len |= 0x8000;
            fwrite($this->socket, chr(($len >> 8) & 0xFF));
            fwrite($this->socket, chr($len & 0xFF));
        } elseif ($len < 0x200000) {
            $len |= 0xC00000;
            fwrite($this->socket, chr(($len >> 16) & 0xFF));
            fwrite($this->socket, chr(($len >> 8) & 0xFF));
            fwrite($this->socket, chr($len & 0xFF));
        } elseif ($len < 0x10000000) {
            $len |= 0xE0000000;
            fwrite($this->socket, chr(($len >> 24) & 0xFF));
            fwrite($this->socket, chr(($len >> 16) & 0xFF));
            fwrite($this->socket, chr(($len >> 8) & 0xFF));
            fwrite($this->socket, chr($len & 0xFF));
        } else {
            fwrite($this->socket, chr(0xF0));
            fwrite($this->socket, chr(($len >> 24) & 0xFF));
            fwrite($this->socket, chr(($len >> 16) & 0xFF));
            fwrite($this->socket, chr(($len >> 8) & 0xFF));
            fwrite($this->socket, chr($len & 0xFF));
        }
        fwrite($this->socket, $word);
    }
    
    private function read($parse = true) {
        $response = [];
        while (true) {
            $word = $this->readWord();
            if ($word == '') {
                break;
            }
            $response[] = $word;
        }
        
        if ($parse) {
            return $this->parseResponse($response);
        }
        return $response;
    }
    
    private function readWord() {
        $len = ord(fread($this->socket, 1));
        
        if (($len & 0x80) == 0x00) {
            return fread($this->socket, $len);
        } elseif (($len & 0xC0) == 0x80) {
            $len &= ~0xC0;
            $len <<= 8;
            $len += ord(fread($this->socket, 1));
        } elseif (($len & 0xE0) == 0xC0) {
            $len &= ~0xE0;
            $len <<= 8;
            $len += ord(fread($this->socket, 1));
            $len <<= 8;
            $len += ord(fread($this->socket, 1));
        } elseif (($len & 0xF0) == 0xE0) {
            $len &= ~0xF0;
            $len <<= 8;
            $len += ord(fread($this->socket, 1));
            $len <<= 8;
            $len += ord(fread($this->socket, 1));
            $len <<= 8;
            $len += ord(fread($this->socket, 1));
        } elseif (($len & 0xF8) == 0xF0) {
            $len = ord(fread($this->socket, 1));
            $len <<= 8;
            $len += ord(fread($this->socket, 1));
            $len <<= 8;
            $len += ord(fread($this->socket, 1));
            $len <<= 8;
            $len += ord(fread($this->socket, 1));
        }
        
        return fread($this->socket, $len);
    }
    
    private function parseResponse($response) {
        $parsed = [];
        $current = [];
        
        foreach ($response as $word) {
            if ($word == '!done' || $word == '!trap' || $word == '!re') {
                if (!empty($current)) {
                    $parsed[] = $current;
                }
                $current = [$word => true];
            } else {
                if (strpos($word, '=') !== false) {
                    list($key, $value) = explode('=', $word, 2);
                    $current[$key] = $value;
                }
            }
        }
        
        if (!empty($current)) {
            $parsed[] = $current;
        }
        
        return $parsed;
    }
    
    // Criar usuário no hotspot
    public function createHotspotUser($username, $password, $profile = 'default', $limitUptime = null) {
        if (!$this->connected && !$this->connect()) {
            return ['success' => false, 'message' => 'Não foi possível conectar ao MikroTik'];
        }
        
        try {
            $command = '/ip/hotspot/user/add'
                     . '=name=' . $username
                     . '=password=' . $password
                     . '=profile=' . $profile;
            
            if ($limitUptime) {
                $command .= '=limit-uptime=' . $limitUptime;
            }
            
            $this->write($command);
            $response = $this->read();
            
            if (isset($response[0]['!done'])) {
                logEvent('mikrotik_success', "Usuário $username criado com sucesso");
                return ['success' => true, 'message' => 'Usuário criado com sucesso'];
            } else {
                logEvent('mikrotik_error', "Erro ao criar usuário: " . json_encode($response));
                return ['success' => false, 'message' => 'Erro ao criar usuário', 'response' => $response];
            }
            
        } catch (Exception $e) {
            logEvent('mikrotik_error', "Exceção ao criar usuário: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // Remover usuário do hotspot
    public function removeHotspotUser($username) {
        if (!$this->connected && !$this->connect()) {
            return ['success' => false, 'message' => 'Não foi possível conectar ao MikroTik'];
        }
        
        try {
            // Buscar ID do usuário
            $this->write('/ip/hotspot/user/print=?name=' . $username);
            $response = $this->read();
            
            if (empty($response) || !isset($response[0]['.id'])) {
                return ['success' => false, 'message' => 'Usuário não encontrado'];
            }
            
            $userId = $response[0]['.id'];
            
            // Remover usuário
            $this->write('/ip/hotspot/user/remove=.id=' . $userId);
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
    
    // Verificar se usuário existe
    public function userExists($username) {
        if (!$this->connected && !$this->connect()) {
            return false;
        }
        
        try {
            $this->write('/ip/hotspot/user/print=?name=' . $username);
            $response = $this->read();
            return !empty($response) && isset($response[0]['.id']);
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function __destruct() {
        $this->disconnect();
    }
}
?>