<?php
// test_mikrotik_connection.php - Testa a conexão com o MikroTik e a criação de usuário

// Inclui os arquivos essenciais
require_once 'config.php';
require_once 'MikrotikAPI.php';

// Configurações para teste
$test_customer_name = "Teste Integracao";
$test_profile = "default"; // Altere para um perfil válido no seu MikroTik, ex: 1hora, 1dia

echo "<html><head><title>Teste de Conexão MikroTik</title></head><body>";
echo "<h1>Teste de Conexão e Inserção no MikroTik</h1>";

// 1. BUSCA DAS CONFIGURAÇÕES
echo "<h2>1. Configurações Buscadas da Tabela 'settings':</h2>";

$mikrotik_host = getSetting('mikrotik_host', '192.168.1.1');
$mikrotik_port = getSetting('mikrotik_port', '8728');
$mikrotik_user = getSetting('mikrotik_user', 'api_user');
$mikrotik_pass = getSetting('mikrotik_password', 'sua_senha'); // Senha API (visível agora)

echo "<ul>";
echo "<li><strong>Host:</strong> " . htmlspecialchars($mikrotik_host) . "</li>";
echo "<li><strong>Porta:</strong> " . htmlspecialchars($mikrotik_port) . "</li>";
echo "<li><strong>Usuário API:</strong> " . htmlspecialchars($mikrotik_user) . "</li>";
// AJUSTE REALIZADO AQUI: Exibir a senha em texto claro
echo "<li><strong>Senha API:</strong> <span style='color:blue;'><strong>" . htmlspecialchars($mikrotik_pass) . "</strong></span></li>";
echo "<li><strong>Perfil de Teste:</strong> " . htmlspecialchars($test_profile) . "</li>";
echo "</ul>";

echo "<hr>";


// 2. GERAÇÃO DE CREDENCIAIS DE TESTE
echo "<h2>2. Geração de Credenciais de Teste:</h2>";

try {
    // Usando a função estática da MikrotikAPI
    $credentials = MikrotikAPI::generateRandomCredentials($test_customer_name);
    $test_username = $credentials['username'];
    $test_password = $credentials['password'];
    $test_comment = "Teste - " . date("YmdHis");
    
    echo "<ul>";
    echo "<li><strong>Usuário Gerado:</strong> " . htmlspecialchars($test_username) . "</li>";
    echo "<li><strong>Senha Gerada:</strong> " . htmlspecialchars($test_password) . "</li>";
    echo "<li><strong>Comentário:</strong> " . htmlspecialchars($test_comment) . "</li>";
    echo "</ul>";
} catch (Throwable $e) {
    echo "<p style='color:red;'><strong>ERRO:</strong> Falha ao gerar credenciais. Certifique-se de que a função `generateRandomCredentials` na `MikrotikAPI.php` está correta.</p>";
    echo "<p>Detalhe: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

echo "<hr>";


// 3. TENTATIVA DE CONEXÃO E INSERÇÃO NO MIKROTIK
echo "<h2>3. Tentativa de Conexão e Criação de Usuário:</h2>";

try {
    // Instancia a classe de API
    $mikrotikApi = new MikrotikAPI($mikrotik_host, $mikrotik_user, $mikrotik_pass, $mikrotik_port);

    // Tenta a conexão
    if (!$mikrotikApi->connect()) {
        echo "<p style='color:red;'><strong>FALHA NA CONEXÃO.</strong> Verifique se o Host/Porta e o Usuário/Senha API (acima) estão corretos e configurados no MikroTik.</p>";
        // logEvent já deve ter registrado o erro de login
        exit;
    }

    echo "<p style='color:green;'>✅ **Conexão estabelecida com sucesso!**</p>";

    // Tenta criar o usuário
    $result = $mikrotikApi->createHotspotUser($test_username, $test_password, $test_profile, $test_comment);

    if ($result['success']) {
        echo "<p style='color:green;'>✅ **Usuário criado com sucesso no Hotspot!**</p>";
        echo "<p>Usuário: <strong>" . htmlspecialchars($test_username) . "</strong></p>";
        echo "<p>Senha: <strong>" . htmlspecialchars($test_password) . "</strong></p>";
        
        // 4. LIMPEZA (OPCIONAL)
        // Tentativa de remover o usuário de teste para evitar lixo
        $removeResult = $mikrotikApi->removeUser($test_username);
        if ($removeResult['success']) {
             echo "<p style='color:blue;'>ℹ️ Usuário de teste <strong>removido</strong> com sucesso após o teste.</p>";
        } else {
             echo "<p style='color:orange;'>⚠️ Não foi possível remover o usuário de teste. Remova manualmente: " . htmlspecialchars($removeResult['message']) . "</p>";
        }
        
    } else {
        echo "<p style='color:red;'>❌ **FALHA AO CRIAR USUÁRIO NO MIKROTIK.**</p>";
        echo "<p>Mensagem de Erro: " . htmlspecialchars($result['message']) . "</p>";
        echo "<p>Verifique se o perfil <strong>" . htmlspecialchars($test_profile) . "</strong> existe no seu Hotspot.</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:red;'><strong>ERRO DE EXECUÇÃO:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Linha: " . $e->getLine() . "</p>";
}

echo "<hr>Fim do Teste.</body></html>";
?>