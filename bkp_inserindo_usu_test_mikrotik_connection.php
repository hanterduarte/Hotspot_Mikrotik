<?php
// test_mikrotik_connection.php - Teste FINAL de Conexão e Criação de Usuário MikroTik

// --- 1. CONFIGURAÇÃO MANUAL DO MIKROTIK ---
// ALTO RISCO DE SEGURANÇA: Mantenha este arquivo apenas para testes de DEPURAGEM.
$mikrotik_host = '192.168.1.57';
$mikrotik_port = 8728;
$api_user = 'api_user'; 
$api_pass = '123456'; 
$test_profile = "2h_acesso"; 

// --- 2. INCLUSÃO DA CLASSE DE API ---
// É CRUCIAL que este arquivo exista e esteja correto.
require_once 'routeros_api.class.php';

// --- 3. PARÂMETROS DE TESTE ---
$test_username = 'teste_sucesso_' . time(); 
$test_password = 'senha' . rand(100, 999);
$test_comment = "Teste de conexao SUCESSO - " . date("Y-m-d H:i:s");

// Função de log simplificada
function logOutput($type, $message) {
    $color = '';
    switch ($type) {
        case 'SUCCESS': $color = 'green'; break;
        case 'ERROR': $color = 'red'; break;
        case 'INFO': $color = 'blue'; break;
        case 'WAIT': $color = 'purple'; break;
        default: $color = 'black';
    }
    echo "<p style='color:{$color};'><strong>[{$type}]</strong> {$message}</p>";
    flush(); 
}

echo "<html><head><title>Teste MikroTik Final</title></head><body>";
echo "<h1>Teste Final de Conexão MikroTik (AJUSTE DE PARÂMETRO)</h1>";
echo "<h2>⚠️ Saída de Debug da API:</h2>";
echo "<pre style='background: #eee; padding: 10px; border: 1px solid #ccc;'>";

// Exibe as credenciais
logOutput('INFO', "Credenciais de Conexão:");
echo "<ul>";
echo "<li><strong>Host:</strong> {$mikrotik_host}:{$mikrotik_port}</li>";
echo "<li><strong>Usuário API:</strong> {$api_user}</li>";
echo "<li><strong>Senha API:</strong> {$api_pass}</li>";
echo "</ul>";
logOutput('INFO', "Usuário de Teste: <strong>{$test_username}</strong> | Perfil: <strong>{$test_profile}</strong>");
echo "<hr>";

// --- 4. LÓGICA DE CONEXÃO E TESTE ---
try {
    // 4.1 Inicializa e configura a API
    $api = new routeros_api();
    $api->port = $mikrotik_port;
    $api->debug = true; 
    
    logOutput('WAIT', "Tentando conectar e autenticar com SHA1...");

    if (!$api->connect($mikrotik_host, $api_user, $api_pass)) {
        logOutput('ERROR', "❌ FALHA NA CONEXÃO E AUTENTICAÇÃO.");
        logOutput('ERROR', "Detalhe (API Error): " . ($api->error_str ?: 'Verifique o IP e credenciais.'));
        exit;
    }

    logOutput('SUCCESS', "✅ **Conexão e Login Estabelecidos com Sucesso!**");
    echo "</pre><hr>";

    // 4.3 Tenta criar o usuário Hotspot
    logOutput('INFO', "Tentando criar usuário Hotspot (Perfil: {$test_profile})...");

    $command = '/ip/hotspot/user/add';
    
    // **** AJUSTE FINAL: Usando chaves sem prefixo ('name', 'password') ****
    // Isso força a biblioteca a formatar o comando corretamente para ADD.
    $args = [
        'name'     => $test_username,
        'password' => $test_password,
        'profile'  => $test_profile,
        'comment'  => $test_comment
    ];

    echo "<pre style='background: #eee; padding: 10px; border: 1px solid #ccc;'>";
    $response = $api->comm($command, $args);
    echo "</pre>";

    if (isset($response['!trap'])) {
        $error = $response['!trap']['message'] ?? 'Erro RouterOS: Mensagem !trap recebida, mas vazia.';
        
        logOutput('ERROR', "❌ FALHA AO CRIAR USUÁRIO NO MIKROTIK.");
        logOutput('ERROR', "Mensagem de Erro (RouterOS): **{$error}**");
        logOutput('ERROR', "Se o erro persistir, verifique se o perfil '{$test_profile}' existe EXATAMENTE como escrito.");
    } else {
        logOutput('SUCCESS', "✅ **Usuário '{$test_username}' criado com sucesso no Hotspot!**");
        
        // 4.4 Limpeza (Remoção do usuário de teste)
        logOutput('INFO', "Tentando remover usuário de teste...");
        
        // A remoção é complexa, usamos 'print' para encontrar o .id e remover
        $find_response = $api->comm('/ip/hotspot/user/print', ['?name' => $test_username, '=.proplist' => '.id']);
        if (!empty($find_response) && isset($find_response[0]['.id'])) {
             $userId = $find_response[0]['.id'];
             $api->comm('/ip/hotspot/user/remove', ['=.id' => $userId]);
             logOutput('SUCCESS', "Usuário de teste removido com sucesso.");
        } else {
             logOutput('WARNING', "Usuário de teste não encontrado para remoção.");
        }
    }

} catch (Throwable $e) {
    logOutput('ERROR', "ERRO CRÍTICO (Exceção PHP): " . $e->getMessage());
    logOutput('ERROR', "Linha: " . $e->getLine());
} finally {
    if (isset($api) && $api->connected) {
        $api->disconnect();
    }
}

echo "</body></html>";
?>