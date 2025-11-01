<?php
// test_mikrotik_api.php - SIMULADOR DE WEBHOOK E TESTE ISOLADO DA MikrotikAPI
// Objetivo: 1. Simular a chamada POST para o Webhook. 2. Testar a classe MikrotikAPI diretamente.
// NOVIDADE: Adicionado Bloco C para simular a lógica completa do Webhook.

// 1. INCLUSÃO CRÍTICA: Carrega as dependências
require_once 'config.php'; 
// !!! CORREÇÃO CRÍTICA: Esta linha é essencial para MikrotikAPI funcionar !!!
require_once 'routeros_api.class.php'; 
require_once 'MikrotikAPI.php'; // Adicionado para teste direto

echo "<h1>Simulador e Teste Isolado MikrotikAPI</h1>";

$testTransactionIdA = 0; // IDs para Teste A/B
$testCustomerIdA = 0;
$testTransactionIdC = 0; // IDs para Teste C
$testCustomerIdC = 0;
$db = null;
$planIdToUse = 1; // ID de um PLANO existente (Ajuste se necessário)
$mikrotikProfile = null; // Variável para o perfil

/**
 * Cria um cliente e uma transação PENDENTE no DB para um teste específico.
 * @param int $planIdToUse O ID do plano a ser usado.
 * @param string $suffix Sufixo para e-mail/log de controle.
 * @return array Contendo ['transaction_id', 'customer_id', 'plan_name', 'email']
 */
function createTestTransaction($planIdToUse, $suffix) {
    global $db, $mikrotikProfile;
    $customerEmail = 'teste_webhook_' . $suffix . time() . '@exemplo.com'; 
    $db->exec("INSERT INTO customers (email, name) VALUES ('$customerEmail', 'Test User $suffix')");
    $customerId = $db->lastInsertId();
    
    $stmt = $db->prepare("
        INSERT INTO transactions (customer_id, plan_id, amount, payment_method, payment_status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([$customerId, $planIdToUse, 100.00, 'pix', 'pending']); 
    $transactionId = $db->lastInsertId();

    $stmtPlan = $db->prepare("SELECT name FROM plans WHERE id = ?");
    $stmtPlan->execute([$planIdToUse]);
    $plan = $stmtPlan->fetch();
    
    return [
        'id' => $transactionId, 
        'customer_id' => $customerId, 
        'plan_id' => $planIdToUse,
        'plan_name' => $plan['name'] ?? 'Plano Teste',
        'email' => $customerEmail
    ];
}


try {
    $db = Database::getInstance()->getConnection();
    
    // ----------------------------------------------------------------------
    // 0. SETUP INICIAL: Criar Transação PENDENTE no DB
    // ----------------------------------------------------------------------
    
    // --- DIAGNÓSTICO: Verificar se o PLANO 1 existe ---
    $stmtPlans = $db->query("SELECT id, mikrotik_profile, name FROM plans ORDER BY id ASC");
    $plans = $stmtPlans->fetchAll(PDO::FETCH_ASSOC);
    $planExists = false;
    $availablePlans = [];
    foreach ($plans as $plan) {
        $availablePlans[] = "ID: {$plan['id']} (Perfil: {$plan['mikrotik_profile']})";
        if ((int)$plan['id'] === $planIdToUse) {
            $planExists = true;
            $mikrotikProfile = $plan['mikrotik_profile'];
        }
    }
    
    echo "<h2>Diagnóstico de Plano</h2>";
    if (!$planExists) {
        throw new Exception("Plano ID $planIdToUse não encontrado. Ajuste \$planIdToUse no script.");
    } else {
        echo "<p style='color:green;'>✅ Plano com ID **$planIdToUse** encontrado. Perfil MikroTik esperado: **$mikrotikProfile**</p>";
    }
    
    // --- SETUP PARA TESTES A e B ---
    $testSetupA = createTestTransaction($planIdToUse, 'A');
    $testTransactionIdA = $testSetupA['id'];
    $testCustomerIdA = $testSetupA['customer_id'];
    $transactionForTestA = $testSetupA;

    echo "<h2>0. Configuração do Teste</h2>";
    echo "<p style='color:blue;'>ℹ️ Cliente (ID: **$testCustomerIdA**) e Transação (ID: **$testTransactionIdA**) PENDENTE criados no DB para Testes A/B.</p>";
    
    // ----------------------------------------------------------------------
    // ======================================================================
    // A. TESTE ISOLADO DIRETO DA MikrotikAPI (Conexão e Comando)
    // ======================================================================
    // ----------------------------------------------------------------------
    echo "<hr><h2>A. TESTE ISOLADO DIRETO DA MikrotikAPI</h2>";
    echo "<p>Este teste chama a função `createHotspotUser` **DIRETAMENTE** para ignorar o erro 500 do Webhook e encontrar a falha real.</p>";

    try {
        $mtAPI = new MikrotikAPI(); 
        // O método createHotspotUser já faz a conexão, criação no MikroTik e insert no DB.
        $userCreationResult = $mtAPI->createHotspotUser($transactionForTestA); 

        if ($userCreationResult['success']) {
            echo "<p style='color:green;'>🎉 **TESTE ISOLADO BEM-SUCEDIDO!**</p>";
            echo "<ul>";
            echo "<li>Usuário Criado (MikroTik): <strong>" . htmlspecialchars($userCreationResult['username']) . "</strong></li>";
            // CORREÇÃO: Usar mikrotik_profile (que retorna da API) para display
            echo "<li>Perfil Usado: <strong>" . htmlspecialchars($userCreationResult['mikrotik_profile'] ?? 'N/A') . "</strong></li>"; 
            echo "<li>Mensagem: " . htmlspecialchars($userCreationResult['message']) . "</li>";
            echo "</ul>";
        } else {
            echo "<p style='color:red;'>❌ **FALHA NO TESTE ISOLADO DA MikrotikAPI!**</p>";
            echo "<div style='background: #ffe6e6; padding: 15px; border: 1px solid #ffcccc;'>";
            echo "<strong>ERRO REAL ENCONTRADO:</strong> " . htmlspecialchars($userCreationResult['message']) . "<br>";
            echo "</div>";
        }

    } catch (Throwable $e) {
        echo "<p style='color:red;'>❌ **ERRO CRÍTICO NA CLASSE MikrotikAPI!**</p>";
        echo "<div style='background: #ffe6e6; padding: 15px; border: 1px solid #ffcccc;'>";
        echo "<strong>Erro na Inicialização/Dependência:</strong> " . $e->getMessage() . "<br>";
        echo "</div>";
    }
    
    // ----------------------------------------------------------------------
    // ======================================================================
    // C. TESTE ISOLADO (Simulação Completa da Lógica Pós-Webhook)
    // ======================================================================
    // ----------------------------------------------------------------------
    
    // --- SETUP PARA TESTE C ---
    $testSetupC = createTestTransaction($planIdToUse, 'C');
    $testTransactionIdC = $testSetupC['id'];
    $testCustomerIdC = $testSetupC['customer_id'];
    $transactionForTestC = $testSetupC;
    $transactionForTestC['payment_status'] = 'pending'; // Simular o status antes do webhook
    $transactionForTestC['transaction_nsu'] = 'sim-nsu-' . time(); 
    $transactionForTestC['invoice_slug'] = 'sim-slug-' . time();
    $transactionForTestC['capture_method'] = 'pix';
    $transactionForTestC['payment_id'] = $transactionForTestC['invoice_slug'];

    echo "<hr><h2>C. TESTE ISOLADO DA LÓGICA DE PROVISIONAMENTO COMPLETA</h2>";
    echo "<p>Este teste simula o **bloco completo de sucesso** do `webhook_infinitypay.php` (Conexão > API > Update DB > E-mail).</p>";
    echo "<p style='color:blue;'>ℹ️ Transação de teste C (ID: **$testTransactionIdC**) PENDENTE criada no DB.</p>";

    try {
        $db->beginTransaction();

        // 1. CHAMA O MIKROTIK (Mesmo teste do bloco A, mas dentro de uma transação DB)
        $mt = new MikrotikAPI();
        $userCreationResultC = $mt->createHotspotUser($transactionForTestC); 

        if (!$userCreationResultC['success']) {
            throw new Exception("Falha no Provisionamento MikroTik/DB: " . $userCreationResultC['message']);
        }

        // 2. ATUALIZAR STATUS DA TRANSAÇÃO PARA 'success' (Lógica do Webhook)
        $updateStmt = $db->prepare("
            UPDATE transactions
            SET 
                payment_status = 'success',
                infinitypay_order_id = ?,
                paid_at = NOW(),
                gateway = 'infinitepay_checkout',
                payment_method = ?,
                payment_id = ?,
                updated_at = NOW()
            WHERE id = ? AND payment_status = 'pending'
        ");
        $updateSuccess = $updateStmt->execute([
            $transactionForTestC['transaction_nsu'],
            $transactionForTestC['capture_method'],
            $transactionForTestC['invoice_slug'],
            $testTransactionIdC
        ]);

        if (!$updateSuccess || $updateStmt->rowCount() === 0) {
            throw new Exception("Falha ao atualizar o status da Transação ID $testTransactionIdC para 'success'.");
        }
        
        // 3. ENVIAR E-MAIL (Lógica do Webhook)
        // ATENÇÃO: Se esta função não existir, é o ponto de falha do 500!
        if (function_exists('sendHotspotCredentialsEmail')) {
             // O webhook espera que a função de e-mail exista.
             sendHotspotCredentialsEmail(
                $transactionForTestC['email'], 
                $userCreationResultC['username'], 
                $userCreationResultC['password'], 
                $userCreationResultC['expires_at'] ?? 'Não Definido', 
                $transactionForTestC['plan_name']
            );
             $email_msg = 'E-mail simulado de sucesso enviado.';
        } else {
             $email_msg = "AVISO: Função 'sendHotspotCredentialsEmail' não encontrada (Pode ser a causa do HTTP 500 no Webhook se for chamada).";
        }


        $db->commit();
        echo "<p style='color:green;'>🎉 **TESTE ISOLADO C BEM-SUCEDIDO!**</p>";
        echo "<ul>";
        echo "<li>Provisionamento MikroTik e Insert DB: ✅</li>";
        echo "<li>Update Status Transação: ✅</li>";
        echo "<li>E-mail: <strong>" . $email_msg . "</strong></li>";
        echo "</ul>";


    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        echo "<p style='color:red;'>❌ **FALHA NO TESTE ISOLADO DA LÓGICA COMPLETA!**</p>";
        echo "<div style='background: #ffe6e6; padding: 15px; border: 1px solid #ffcccc;'>";
        echo "<strong>ERRO CRÍTICO ENCONTRADO:</strong> " . $e->getMessage() . "<br>";
        echo "<strong>Local da Falha:</strong> " . $e->getFile() . " na linha " . $e->getLine();
        echo "</div>";
    }

    // ----------------------------------------------------------------------
    // ======================================================================
    // B. SIMULAÇÃO DO WEBHOOK (Fluxo original)
    // ======================================================================
    // ----------------------------------------------------------------------
    echo "<hr><h2>B. SIMULAÇÃO DO WEBHOOK (Requisição Externa)</h2>";
    //... (O código do Bloco B permanece o mesmo) ...

    $payloadArray = [
        "invoice_slug" => "2CV0m9AlY7",
        "amount" => 100,
        "paid_amount" => 100,
        "installments" => 1,
        "capture_method" => "pix",
        "transaction_nsu" => "e0ca83f1-" . time(), 
        "order_nsu" => (string)$testTransactionIdA, // CHAVE CRÍTICA (Usa o ID do setup A)
        "receipt_url" => "https://recibo.infinitepay.io/teste",
        "status" => "paid", 
        "items" => [
            ["quantity" => 1, "price" => 100, "description" => "2 Horas - Acesso Hotspot"]
        ]
    ];
    $jsonPayload = json_encode($payloadArray);
    $webhookUrl = BASE_URL . '/webhook_infinitypay.php';

    echo "<h3>1. Enviando Requisição POST (Simulação)</h3>";
    echo "<p>URL de Destino: <strong>" . htmlspecialchars($webhookUrl) . "</strong></p>";
    
    if (!function_exists('curl_init')) {
         throw new Exception("A extensão PHP cURL não está habilitada. Ative-a no seu php.ini.");
    }
    
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonPayload)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    echo "<h3>2. Resultado do Webhook</h3>";
    echo "<ul>";
    echo "<li>Código HTTP de Resposta: <strong>$httpCode</strong></li>";
    if ($curlError) {
        echo "<li style='color:red;'>Erro cURL: $curlError</li>";
    }
    echo "<li>Resposta Bruta: " . htmlspecialchars($response) . "</li>";
    echo "</ul>";

    $webhookResult = json_decode($response, true);
    
    if ($httpCode === 200 && isset($webhookResult['success']) && $webhookResult['success']) {
        echo "<p style='color:green;'>🎉 **SUCESSO TOTAL!** O webhook processou o pagamento e retornou as credenciais.</p>";
    } elseif ($httpCode === 500) {
         echo "<p style='color:red;'>❌ **ERRO:** Webhook retornou HTTP 500 (Erro Interno). **O erro real está no bloco A acima.**</p>";
    } else {
        $errorMessage = $webhookResult['message'] ?? 'Resposta JSON inválida ou inesperada.';
        echo "<p style='color:red;'>❌ **ERRO CRÍTICO:** O teste falhou!</p>";
        echo "<p>Falha no Webhook. HTTP Code: $httpCode. Mensagem: " . $errorMessage . "</p>";
    }
    

} catch (Throwable $e) {
    echo "<p style='color:red;'>❌ **ERRO CRÍTICO:** O teste falhou!</p>";
    echo "<div style='background: #ffe6e6; padding: 15px; border: 1px solid #ffcccc;'>";
    echo "<strong>Mensagem de Erro:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>Local da Falha:</strong> " . $e->getFile() . " na linha " . $e->getLine();
    echo "</div>";

} finally {
    
    // Limpeza de TODAS as transações de setup
    if ($db) {
         $db->exec("DELETE FROM hotspot_users WHERE transaction_id IN ($testTransactionIdA, $testTransactionIdC)");
         $db->exec("DELETE FROM transactions WHERE id IN ($testTransactionIdA, $testTransactionIdC)");
         $db->exec("DELETE FROM customers WHERE id IN ($testCustomerIdA, $testCustomerIdC)");
         echo "<p style='color:blue;'>ℹ️ Transações (IDs: $testTransactionIdA, $testTransactionIdC) e Clientes (IDs: $testCustomerIdA, $testCustomerIdC) removidos do DB.</p>";
    }
    echo "<hr>Teste Finalizado.";
}
?>