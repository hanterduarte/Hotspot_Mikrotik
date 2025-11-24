<?php
// testefluxovenda.php - Simula o fluxo de venda completo (Plano -> Cliente -> Checkout -> Webhook)
require_once 'config.php'; 
require_once 'MikrotikAPI.php'; // Garanta que este arquivo exista

// O arquivo 'config.php' agora é responsável por garantir que a sessão esteja ativa.
$db = Database::getInstance()->getConnection();
$message = '';
$insertMessage = ''; // Variável para a mensagem do teste DB

// Variáveis de sessão (mantidas para compatibilidade com o fluxo de teste original)
// Mantenha estas variáveis se você usa o fluxo de passos 1, 2, 3, 4, etc.
$transactionId = $_SESSION['test_transaction_id'] ?? null;
$step = $_SESSION['test_step'] ?? 1;
$selectedPlan = $_SESSION['selected_plan'] ?? null;
$clientData = $_SESSION['client_data'] ?? [];
$fictitiousPayload = $_SESSION['fictitious_payload'] ?? null;

// ==========================================================================
// TESTE DE INSERÇÃO DIRETA NO DB (CHAPS BINÁRIOS)
// ==========================================================================
// O usuário solicitou um teste de inserção direta para confirmar VARBINARY.
if (isset($_POST['test_db_insert'])) {
    
    // Dados para o teste (DO SEU PAYLOAD FINAL CORRETO)
    $testData = [
        "plan_id" => "1",
        "client_ip" => "192.168.10.252",
        "client_mac" => "44:E5:17:F8:A5:B1",
        "name" => "Joana hj DB TEST - ". time(), // Adicionar timestamp para unicidade
        "email" => "joana_dbtest_". time() ."@hotmail.com", 
        "phone" => "81998181680",
        "cpf" => "12345678901",
        "link_login_only" => "http://wifibarato.net/login",
        "link_orig" => "http://www.msftconnecttest.com/redirect",
        // Estes são os hashes BINÁRIOS corretos (em Base64)
        "chap_id" => "bw==",
        "chap_challenge" => "NWl0qtAwbBVXlau843QQPw=="
    ];

    try {
        $db->beginTransaction();

        // 1. Simular: Encontrar/Criar Cliente (Necessário para customer_id)
        $cpf = preg_replace('/\D/', '', $testData['cpf']);
        $stmt = $db->prepare("SELECT id FROM customers WHERE cpf = ?");
        $stmt->execute([$cpf]);
        $customerId = $stmt->fetchColumn();

        if (!$customerId) {
            $stmt = $db->prepare("INSERT INTO customers (name, email, phone, cpf) VALUES (?, ?, ?, ?)");
            // Usamos email e nome únicos por causa do timestamp
            $stmt->execute([$testData['name'], $testData['email'], $testData['phone'], $cpf]);
            $customerId = $db->lastInsertId();
        }

        // 2. Inserir a Transação (Incluindo os campos VARBINARY)
        $insertTx = "
            INSERT INTO transactions (
                customer_id, plan_id, amount, created_at, 
                client_ip, client_mac, mikrotik_link_login_only, mikrotik_link_orig, 
                mikrotik_chap_id, mikrotik_chap_challenge, payment_status
            ) VALUES (
                ?, ?, ?, NOW(), 
                ?, ?, ?, ?, 
                ?, ?, 'pending'
            )
        ";
        
        // Simule o valor do plano (plan_id=1, usar R$1.00 para teste)
        $amount = 1.00; 

        $params = [
            $customerId,
            (int)$testData['plan_id'],
            $amount,
            $testData['client_ip'],
            $testData['client_mac'],
            $testData['link_login_only'],
            $testData['link_orig'],
            // DECODIFICAÇÃO CRÍTICA DE BASE64 PARA BINÁRIO AQUI:
            base64_decode($testData['chap_id']),
            base64_decode($testData['chap_challenge'])
        ];

        $stmt = $db->prepare($insertTx);
        $stmt->execute($params);
        $transactionId = $db->lastInsertId();
        
        $db->commit();
        
        $insertMessage = "✅ **TESTE DE INSERÇÃO NO BANCO DE DADOS CONCLUÍDO COM SUCESSO!**";
        $insertMessage .= "<br>ID da Transação Criada: <strong>#$transactionId</strong>";
        $insertMessage .= "<br>Isso **confirma** que suas colunas **VARBINARY** estão configuradas corretamente para receber os hashes do Mikrotik.";

    } catch (PDOException $e) {
        $db->rollBack();
        $insertMessage = "❌ **ERRO FATAL DE BANCO DE DADOS DETECTADO DURANTE A INSERÇÃO:**";
        $insertMessage .= "<br><pre style='color:red;'>" . htmlspecialchars($e->getMessage()) . "</pre>";
        $insertMessage .= "<br>O erro acima indica um problema com o tipo/tamanho da coluna (`VARBINARY`) ou nome incorreto das colunas na tabela `transactions`.";
    } catch (Exception $e) {
        $insertMessage = "❌ **ERRO GERAL:** " . htmlspecialchars($e->getMessage());
    }
}
// ==========================================================================
// FIM DO TESTE DE INSERÇÃO DIRETA
// ==========================================================================


// HTML para exibir o formulário de teste e o resultado
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Fluxo de Venda - Debug</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .debug-box { border: 2px solid #ccc; padding: 15px; margin-bottom: 20px; border-radius: 8px; }
    </style>
</head>
<body>

    <h1>Ferramenta de Debug - Teste de Inserção DB</h1>

    <div class="debug-box" style="border-color: #007bff;">
        <h2>Status Atual do Problema Principal:</h2>
        <p>O problema mais provável no seu **`process_payment_infinity.php`** é o erro fatal: 
        <strong class="error">`Call to undefined function inputGet()`</strong>.</p>
        <p>Enquanto este erro não for corrigido, o script **não avança** para inserir no banco ou chamar a InfinitePay. A correção é a prioridade.</p>
    </div>

    <div class="debug-box" style="border-color: <?php echo !empty($insertMessage) ? (strpos($insertMessage, '✅') !== false ? 'green' : 'red') : '#f8f9fa'; ?>;">
        <h2>1. Teste de Inserção Direta de Chaps Binários no DB</h2>
        
        <?php 
            if (!empty($insertMessage)) {
                echo "<p>$insertMessage</p>";
            } else {
                echo "<p>Clique no botão abaixo para simular a inserção do payload Mikrotik diretamente no seu DB. Se funcionar, a coluna **VARBINARY** está OK.</p>";
            }
        ?>

        <form method="POST" style="margin-top: 15px;">
            <button type="submit" name="test_db_insert" style="padding: 10px 20px; background-color: #007bff; color: white; border: none; cursor: pointer; border-radius: 5px;">Executar Teste de DB</button>
        </form>
    </div>

    </body>
</html>