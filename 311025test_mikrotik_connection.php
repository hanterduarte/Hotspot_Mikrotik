<?php
// mikrotik_admin_tool.php - Ferramenta de Administração e Teste da API MikroTik

// --- 1. CONFIGURAÇÃO MANUAL DO MIKROTIK ---
$mikrotik_host = '192.168.1.57';
$mikrotik_port = 8728;
$api_user = 'api_user'; 
$api_pass = '123456'; 
$test_profile = "2h_acesso"; 
$hotspot_server = "hotspot1"; 

// --- 2. INCLUSÃO DA CLASSE DE API ---
// GARANTA QUE ESTE ARQUIVO EXISTA NO MESMO DIRETÓRIO
require_once 'routeros_api.class.php';

// --- 3. PARÂMETROS DE TESTE ---
$test_username = 'teste_sucesso_' . time(); 
$test_password = 'senha' . rand(100, 999);
$test_comment = "Teste de conexao SUCESSO - " . date("Y-m-d H:i:s");

$log_messages = [];

// Função de log que armazena mensagens para exibição
function logOutput($type, $message) {
    global $log_messages;
    $log_messages[] = ['type' => $type, 'message' => $message];
}

// Função para exibir o log na tela
function displayLog() {
    global $log_messages;
    echo "<h2>⚠️ Saída de Debug da API (<<< Comandos enviados, >>> Respostas recebidas)</h2>";
    echo "<div style='background: #eee; padding: 10px; border: 1px solid #ccc; max-height: 400px; overflow-y: scroll;'>";
    foreach ($log_messages as $log) {
        $color = 'black';
        switch ($log['type']) {
            case 'SUCCESS': $color = 'green'; break;
            case 'ERROR': $color = 'red'; break;
            case 'INFO': $color = 'blue'; break;
            case 'WAIT': $color = 'purple'; break;
        }
        echo "<p style='color:{$color}; margin: 0;'><strong>[{$log['type']}]</strong> {$log['message']}</p>";
    }
    echo "</div><hr>";
}

// --- LÓGICA DE CONEXÃO E TESTE ---
$api = new routeros_api();
$api->port = $mikrotik_port;
$api->debug = true; 
$users = [];
$status_message = null;

try {
    // Tenta Conectar e Autenticar
    logOutput('WAIT', "Tentando conectar e autenticar com SHA1...");

    // Redireciona a saída do debug para o logOutput
    $api->debug_handler = function($text) {
        global $log_messages;
        $log_messages[] = ['type' => 'DEBUG', 'message' => htmlspecialchars($text)];
    };

    if (!$api->connect($mikrotik_host, $api_user, $api_pass)) {
        logOutput('ERROR', "❌ FALHA NA CONEXÃO E AUTENTICAÇÃO.");
        logOutput('ERROR', "Detalhe (API Error): " . ($api->error_str ?: 'Verifique o IP, porta e credenciais.'));
    } else {
        logOutput('SUCCESS', "✅ **Conexão e Login Estabelecidos com Sucesso!**");

        // --- 5. LÓGICA DE DELEÇÃO MANUAL (POST) ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id_to_delete'])) {
            $userIdToDelete = htmlspecialchars($_POST['user_id_to_delete']);

            logOutput('INFO', "Recebida requisição para deletar usuário com ID: {$userIdToDelete}");
            
            // Comando de remoção
            $api->comm('/ip/hotspot/user/remove', ['.id' => $userIdToDelete]);
            
            $status_message = "Usuário Hotspot com ID **{$userIdToDelete}** removido com sucesso.";

            // Depois da remoção, a lista de usuários será atualizada.
        }

        // --- 4.3 Tenta criar o usuário Hotspot (Apenas se não for um POST de deleção) ---
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['user_id_to_delete'])) {
            logOutput('INFO', "Tentando criar usuário Hotspot (Perfil: {$test_profile}, Servidor: {$hotspot_server})...");

            $command = '/ip/hotspot/user/add';
            $args = [
                'name'     => $test_username,
                'password' => $test_password,
                'profile'  => $test_profile,
                'comment'  => $test_comment,
                'server'   => $hotspot_server 
            ];

            $response = $api->comm($command, $args);

            if (isset($response['!trap'])) {
                $error = $response['!trap']['message'] ?? 'Erro RouterOS: Mensagem !trap recebida.';
                logOutput('ERROR', "❌ FALHA AO CRIAR USUÁRIO NO MIKROTIK.");
                logOutput('ERROR', "Mensagem de Erro (RouterOS): **{$error}**");
            } else {
                logOutput('SUCCESS', "✅ **Usuário '{$test_username}' criado com sucesso no Hotspot!**");
                $status_message = "Usuário de teste **{$test_username}** criado com sucesso e adicionado à lista.";
            }
        }
        
        // --- 6. BUSCAR E LISTAR TODOS OS USUÁRIOS ---
        logOutput('INFO', "Buscando todos os usuários Hotspot para exibição...");
        // O comando 'print' sem argumentos retorna todos os campos de todos os usuários
        $users = $api->comm('/ip/hotspot/user/print');
        logOutput('SUCCESS', "Foram encontrados " . count($users) . " usuários Hotspot.");
    }

} catch (Throwable $e) {
    logOutput('ERROR', "ERRO CRÍTICO (Exceção PHP): " . $e->getMessage());
    logOutput('ERROR', "Linha: " . $e->getLine());
} finally {
    if (isset($api) && $api->connected) {
        $api->disconnect();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MikroTik Admin Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .log-container { border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; background-color: #f9f9f9; }
        .log-message { margin: 5px 0; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        .wait { color: purple; }
        .debug { color: gray; font-size: 0.9em; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .btn-delete { background-color: #f44336; color: white; border: none; padding: 5px 10px; cursor: pointer; border-radius: 4px; }
        .status-box { padding: 10px; margin-bottom: 20px; border-radius: 4px; }
        .status-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>
    <h1>MikroTik Hotspot Admin Tool</h1>
    <p>Este script cria um usuário de teste (sempre que a página é carregada) e lista todos os usuários do Hotspot para depuração.</p>

    <?php if ($status_message): ?>
        <div class="status-box status-success">
            <strong>SUCESSO:</strong> <?php echo $status_message; ?>
        </div>
    <?php endif; ?>

    <?php displayLog(); ?>

    <hr>
    <h2>Usuários Hotspot no MikroTik (Total: <?php echo count($users); ?>)</h2>

    <?php if (!empty($users)): ?>
    <table>
        <thead>
            <tr>
                <th>Ação</th>
                <th>.ID</th>
                <th>Nome</th>
                <th>Senha</th>
                <th>Perfil</th>
                <th>Servidor</th>
                <th>Comentário</th>
                <th>Desabilitado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td>
                    <form method="POST" style="margin: 0;" onsubmit="return confirm('Tem certeza que deseja DELETAR o usuário <?php echo htmlspecialchars($user['name'] ?? 'N/A'); ?> (ID: <?php echo htmlspecialchars($user['.id'] ?? 'N/A'); ?>)?');">
                        <input type="hidden" name="user_id_to_delete" value="<?php echo htmlspecialchars($user['.id'] ?? ''); ?>">
                        <button type="submit" class="btn-delete" title="Deletar este usuário">Deletar</button>
                    </form>
                </td>
                <td><?php echo htmlspecialchars($user['.id'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($user['name'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($user['password'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($user['profile'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($user['server'] ?? 'all'); ?></td>
                <td><?php echo htmlspecialchars($user['comment'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($user['disabled'] ?? 'no'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p>Nenhum usuário Hotspot encontrado ou falha na conexão/busca.</p>
    <?php endif; ?>

</body>
</html>