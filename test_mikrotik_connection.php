<?php
// test_mikrotik_connection.php - Script de diagnóstico para API MikroTik

require_once 'config.php';
require_once 'MikrotikAPI.php'; // Sua classe de comunicação com o Mikrotik

header('Content-Type: text/plain');

// Função de log simplificada para o teste
function logTest($type, $message) {
    echo "[$type] " . $message . "\n";
    if (function_exists('logEvent')) {
        logEvent("mikrotik_test_$type", $message);
    }
}

// --- Parâmetros de Teste (Usuário, Senha, Perfil) ---
$testUsername = 'teste_hotspot_' . time(); // Usuário único a cada teste
$testPassword = 'senhateste123';
// Importante: Altere para um perfil VÁLIDO no seu Mikrotik
$testProfile = '2h_acesso'; 

logTest('INFO', "Iniciando teste de conexão e criação de usuário.");
logTest('INFO', "Usuário de teste: $testUsername | Perfil: $testProfile");

try {
    // 1. Inicializar a API
    $mt = new MikrotikAPI();
    logTest('INFO', "Classe MikrotikAPI inicializada.");

    // 2. Tentar Conectar
    if (!$mt->connect()) {
        logTest('ERROR', "Falha ao conectar e autenticar no MikroTik. Verifique as configurações (host/user/pass/port) no DB ou no MikrotikAPI.php.");
        exit;
    }
    logTest('SUCCESS', "Conexão e autenticação no MikroTik OK.");

    // 3. Simular a Criação do Usuário
    
    // ATENÇÃO: Os comandos abaixo são a base do que o createHotspotUser deve fazer.
    // Eles precisam de um perfil VÁLIDO. Se você não tem '2h', use um que exista.
    $command = [
        '/ip/hotspot/user/add',
        '=name=' . $testUsername,
        '=password=' . $testPassword,
        '=profile=' . $testProfile
        // Adicione outras flags se for necessário (ex: '=server=hotspot1')
    ];
    
    // Executar o comando de criação
    $mt->write($command);
    $response = $mt->read();
    
    // O Mikrotik retorna uma resposta vazia ou !done em caso de sucesso
    if (isset($response[0]['!trap'])) {
        logTest('ERROR', "Erro RouterOS ao criar usuário: " . json_encode($response));
    } else {
        logTest('SUCCESS', "Usuário $testUsername criado com sucesso no MikroTik.");
        
        // 4. Testar a Remoção do Usuário (Limpeza)
        // Isso verifica a funcionalidade de remove e limpa o Mikrotik
        $mt->write('/ip/hotspot/user/remove', false);
        $mt->write('=.id=' . $testUsername, false);
        $mt->read(); // Apenas lê a resposta
        
        // Como o 'remove' usa o 'name' em vez do '.id' (que é mais seguro), 
        // é melhor usar o método userExists() ou um comando 'print' para verificar.
        
        // Chamada direta para um método de remoção mais seguro (se você o tiver)
        // $removeResult = $mt->removeUser($testUsername); 
        // logTest('INFO', "Tentativa de remoção: " . ($removeResult['success'] ? 'SUCESSO' : 'FALHA'));
    }

} catch (Exception $e) {
    logTest('FATAL_ERROR', "Exceção não tratada no PHP: " . $e->getMessage());
}

if (isset($mt)) {
    $mt->disconnect();
}
logTest('INFO', "Teste finalizado.");

?>