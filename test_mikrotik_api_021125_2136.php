<?php
// test_mikrotik_api.php - SIMULADOR DO FLUXO COMPLETO DE PAGAMENTO/MIKROTIK
// Objetivo: Testar o fluxo process_payment -> addBypass -> webhook -> removeBypass em uma única execução.

// 1. INCLUSÃO CRÍTICA: Carrega as dependências
require_once 'config.php'; 
require_once 'routeros_api.class.php'; 
require_once 'MikrotikAPI.php'; // Contém as funções de bypass

// Headers e Estilos
echo "<!DOCTYPE html>
<html>
<head>
    <title>Teste de Fluxo Completo MikrotikAPI</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f9f9f9; }
        h1 { color: #007bff; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
        h2 { color: #333; border-bottom: 1px solid #ddd; margin-top: 20px; padding-bottom: 5px; }
        .block { background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .success { color: green; font-weight: bold; }
        .failure { color: red; font-weight: bold; }
        .info { background: #e6f7ff; padding: 10px; border-left: 5px solid #007bff; }
        .warning { color: orange; font-weight: bold; }
        .debug { font-size: 0.85em; color: #555; background: #f0f0f0; padding: 8px; border-radius: 4px; margin-top: 5px; }
    </style>
</head>
<body>";

echo "<h1>Teste de Fluxo Completo MikrotikAPI (Processo de Pagamento)</h1>";

$db = Database::getInstance()->getConnection();
$testTransactionId = 0;
$testCustomerId = 0;
$planIdToUse = 1; // ID de um PLANO existente (Ajuste se necessário)

try {
    // Busca um plano para o teste
    $stmt = $db->prepare("SELECT * FROM plans WHERE id = ? AND active = 1");
    $stmt->execute([$planIdToUse]);
    $plan = $stmt->fetch();

    if (!$plan) {
        throw new Exception("Plano ID $planIdToUse não encontrado ou inativo no DB. Ajuste o \$planIdToUse.");
    }
    
    // ======================================================================
    // 1. SIMULAÇÃO: process_payment_infinity.php (CRIAÇÃO DA TRANSAÇÃO E ADD BYPASS)
    // ======================================================================
    echo "<h2>1. Simulação: process_payment_infinity.php</h2>";
    echo "<div class='block'>";
    
    // --- 1.1 Cria Cliente e Transação (PENDENTE) no DB ---
    $customerData = [
        'name' => 'Teste Bypass ' . time(),
        'email' => 'teste' . time() . '@bypass.com',
        'phone' => '99999999999',
        'cpf' => '00000000000',
    ];
    // Assumindo que a função createOrGetCustomer existe
    $testCustomerId = createOrGetCustomer($db, $customerData); 

    $db->beginTransaction();
    $stmt = $db->prepare("
        INSERT INTO transactions (customer_id, plan_id, amount, payment_method, payment_status)
        VALUES (?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$testCustomerId, $planIdToUse, $plan['price'], 'test_mikrotik_api']);
    $testTransactionId = $db->lastInsertId();
    $db->commit();
    echo "<p class='success'>✅ Transação PENDENTE criada com sucesso. ID: <strong>$testTransactionId</strong></p>";

    // --- 1.2 Obtém e Exibe o IP (Substitui a lógica do 'getmac') ---
    $phpClientIP = MikrotikAPI::getClientIP(); // Chama a função estática
    echo "<p class='info'>IP do Cliente Visto pelo PHP: <strong>" . htmlspecialchars($phpClientIP) . "</strong></p>";
    
    if ($phpClientIP === '0.0.0.0' || !filter_var($phpClientIP, FILTER_VALIDATE_IP)) {
        echo "<p class='failure'>❌ **FALHA CRÍTICA DE IP:** O IP do cliente é inválido ou '0.0.0.0'. O Bypass NÃO FUNCIONARÁ.</p>";
        throw new Exception("IP do cliente não detectado corretamente. Verifique a configuração do seu servidor web/proxy.");
    }
    
    // --- 1.3 Adiciona o Bypass no MikroTik ---
    echo "<h3>Chamada: addClientBypass($testTransactionId)</h3>";
    $mikrotikResult = (new MikrotikAPI())->addClientBypass($testTransactionId);
    
    $clientIP = $mikrotikResult['client_ip'] ?? null;
    $mikrotikBypassId = $mikrotikResult['bypass_id'] ?? null;

    if ($mikrotikResult['success']) {
        echo "<p class='success'>✅ **BYPASS ADICIONADO:** ID <strong>$mikrotikBypassId</strong> para IP <strong>$clientIP</strong>.</p>";
    } else {
        echo "<p class='failure'>❌ **FALHA NO ADD BYPASS:** " . htmlspecialchars($mikrotikResult['message']) . "</p>";
        echo "<p class='warning'>⚠️ **Atenção:** Se falhar aqui, verifique as credenciais do MikroTik na tabela `settings` e a regra de Firewall de Acesso à API.</p>";
        throw new Exception("Falha Crítica no addClientBypass: " . $mikrotikResult['message']);
    }

    // --- 1.4 Atualiza a Transação com o ID do Bypass (Necessário para a remoção no webhook) ---
    $db->beginTransaction();
    $stmt = $db->prepare("
        UPDATE transactions 
        SET client_ip = ?, mikrotik_bypass_id = ?
        WHERE id = ?
    ");
    $stmt->execute([$clientIP, $mikrotikBypassId, $testTransactionId]);
    $db->commit();
    echo "<p class='debug'>Transação atualizada no DB com IP e ID do Bypass. </p>";

    echo "</div>";

    // ======================================================================
    // 2. SIMULAÇÃO: webhook_infinitypay.php (PROCESSAMENTO E REMOÇÃO DO BYPASS)
    // ======================================================================
    echo "<h2>2. Simulação: webhook_infinitypay.php</h2>";
    echo "<div class='block'>";
    
    // --- 2.1 Busca a Transação (para obter o ID do Bypass) ---
    $stmt = $db->prepare("
        SELECT t.*, p.name as plan_name, p.duration_seconds
        FROM transactions t
        JOIN plans p ON t.plan_id = p.id
        WHERE t.id = ?
    ");
    $stmt->execute([$testTransactionId]);
    $transaction = $stmt->fetch();

    if (!$transaction) {
        throw new Exception("Falha ao buscar transação $testTransactionId para o Webhook.");
    }
    
    $mikrotikBypassIdFromDB = $transaction['mikrotik_bypass_id'] ?? null;
    
    // --- 2.2 Cria o Usuário Hotspot e Atualiza a Transação (Provisionamento) ---
    echo "<h3>Chamada: createHotspotUser()</h3>";
    $mt = new MikrotikAPI();
    $userCreationResult = $mt->createHotspotUser($transaction);
    
    if ($userCreationResult['success']) {
         // Simula a atualização da transação (status: success)
        $db->beginTransaction();
        $updateStmt = $db->prepare("UPDATE transactions SET payment_status = 'success', paid_at = NOW() WHERE id = ?");
        $updateStmt->execute([$testTransactionId]);
        $db->commit();
        echo "<p class='success'>✅ **USUÁRIO CRIADO:** Usuário Hotspot provisionado e status atualizado no DB.</p>";
    } else {
        echo "<p class='failure'>❌ **FALHA CRÍTICA NA CRIAÇÃO DO USUÁRIO:** " . htmlspecialchars($userCreationResult['message']) . "</p>";
        throw new Exception("Falha Crítica na criação do usuário: " . $userCreationResult['message']);
    }
    
    // --- 2.3 Remove o Bypass do MikroTik ---
    echo "<h3>Chamada: removeBypass($mikrotikBypassIdFromDB)</h3>";
    if (!empty($mikrotikBypassIdFromDB)) {
        $resultRemove = (new MikrotikAPI())->removeBypass($mikrotikBypassIdFromDB); 
        
        if ($resultRemove['success']) {
            echo "<p class='success'>✅ **BYPASS REMOVIDO:** ID <strong>$mikrotikBypassIdFromDB</strong> removido com sucesso!</p>";
        } else {
            echo "<p class='failure'>❌ **FALHA AO REMOVER BYPASS:** " . htmlspecialchars($resultRemove['message']) . "</p>";
        }
    } else {
         echo "<p class='warning'>⚠️ **AVISO:** ID de Bypass não encontrado na transação. Remoção ignorada.</p>";
    }
    
    echo "</div>";

    // ======================================================================
    // 3. FLUXO CONCLUÍDO
    // ======================================================================
    echo "<h2>3. Conclusão do Teste</h2>";
    echo "<p class='success'>🎉 **TESTE DE FLUXO CONCLUÍDO COM SUCESSO!**</p>";
    echo "<p class='info'>Se esta página não apresentou erros vermelhos, o fluxo de adição/remoção do bypass no MikroTik está funcionando corretamente.</p>";
    
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "<h2>❌ FALHA CRÍTICA NO FLUXO</h2>";
    echo "<p class='failure'>O fluxo foi interrompido! Motivo:</p>";
    echo "<div class='debug'>";
    echo "<strong>Mensagem de Erro:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<strong>Local da Falha:</strong> " . htmlspecialchars($e->getFile()) . " na linha " . htmlspecialchars($e->getLine());
    echo "</div>";
    
    echo "<h3>Diagnóstico Urgente:</h3>";
    echo "<p class='warning'>Verifique a tabela `settings` (Credenciais e Porta API) e a acessibilidade ao MikroTik.</p>";

} finally {
    // Limpeza (para não poluir o DB com transações de teste)
    if ($testTransactionId > 0 && $testCustomerId > 0) {
        $db->exec("DELETE FROM hotspot_users WHERE transaction_id = $testTransactionId");
        $db->exec("DELETE FROM transactions WHERE id = $testTransactionId");
        $db->exec("DELETE FROM customers WHERE id = $testCustomerId");
        echo "<p class='debug'>Limpeza do DB concluída.</p>";
    }
    echo "</body></html>";
}
?>