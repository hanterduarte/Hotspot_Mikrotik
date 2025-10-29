<?php
// check_payment_status.php - Verifica o status do pagamento via polling e age como fallback

require_once 'config.php';
require_once 'MikrotikAPI.php'; 
require_once 'InfinityPay.php'; 

header('Content-Type: application/json');

$transactionId = $_GET['payment_id'] ?? null; // Aqui, payment_id é o t.id

if (!$transactionId) {
    jsonResponse(false, 'Transaction ID não fornecido');
}

try {
    $db = Database::getInstance()->getConnection();
    
    // 1. Buscar transação e dados necessários (incluindo duração do plano)
    $stmt = $db->prepare("
        SELECT t.*, hu.username, hu.password as user_password, p.duration_seconds
        FROM transactions t
        LEFT JOIN hotspot_users hu ON t.id = hu.transaction_id
        JOIN plans p ON t.plan_id = p.id
        WHERE t.id = ?  
    ");
    $stmt->execute([$transactionId]);
    $transaction = $stmt->fetch();

    if (!$transaction) {
        jsonResponse(false, 'Transação não encontrada');
    }

    $currentStatus = $transaction['payment_status'];
    
    // Se SUCESSO e credenciais existem, retorna imediatamente
    if ($currentStatus === 'success' && $transaction['username']) {
        jsonResponse(true, 'Pagamento aprovado.', [
            'status' => 'success',
            'credentials' => [
                'username' => $transaction['username'],
                'password' => $transaction['user_password']
            ]
        ]);
        return;
    }
    
    // 2. LÓGICA DE FALLBACK (Se ainda está PENDENTE, verifica o status na InfinitePay)
    if ($currentStatus === 'pending') {
        
        $gatewayResponse = json_decode($transaction['gateway_response'], true);
        $invoiceSlug = $gatewayResponse['invoice_slug'] ?? null;
        
        if (!$invoiceSlug) {
            logEvent('fallback_error', "Transação ID $transactionId pendente, mas sem Invoice Slug para consulta.", $transactionId);
            jsonResponse(true, 'Aguardando webhook...', ['status' => 'pending']);
        }
        
        $ipApi = new InfinityPay();
        $apiResult = $ipApi->getInvoiceStatus($invoiceSlug); 

        if ($apiResult['success']) {
            $ipStatus = strtolower($apiResult['data']['status'] ?? 'pending'); 
            $transactionNsu = $apiResult['data']['transaction_nsu'] ?? $invoiceSlug;
            $captureMethod = $apiResult['data']['capture_method'] ?? 'checkout_link';

            // Se o status retornado pela API for de sucesso
            if ($ipStatus === 'paid' || $ipStatus === 'approved') {
                
                // --- EXECUÇÃO DA LÓGICA DO WEBHOOK ---
                $db->beginTransaction();
                
                // Atualizar a transação no DB
                $updateStmt = $db->prepare("
                    UPDATE transactions
                    SET 
                        payment_status = 'success',
                        infinitypay_order_id = ?,    
                        paid_at = NOW(),             
                        gateway = ?,                 
                        updated_at = NOW(),
                        gateway_response = JSON_SET(COALESCE(gateway_response, '{}'), '$.transaction_nsu', ?)
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $transactionNsu,  
                    $captureMethod,   
                    $transactionNsu,
                    $transactionId    
                ]);

                // Chamar a função de criação de usuário no MikroTik
                $mt = new MikrotikAPI();
                $userCreationResult = createHotspotUser($db, $mt, $transaction, $transaction['duration_seconds']); 
                
                if ($userCreationResult['success']) {
                    $db->commit();
                    logEvent('fallback_success', "Pagamento ID $transactionId aprovado por fallback. Usuário criado.", $transactionId);
                    
                    // Retornar 'success' e as credenciais
                    jsonResponse(true, 'Pagamento aprovado.', [
                        'status' => 'success', 
                        'credentials' => [
                            'username' => $userCreationResult['username'], 
                            'password' => $userCreationResult['password']
                        ]
                    ]);
                } else {
                    $db->rollBack();
                    logEvent('fallback_error', "Pagamento ID $transactionId aprovado, mas falha ao criar usuário Mikrotik: " . $userCreationResult['message'], $transactionId);
                    jsonResponse(true, 'Pagamento aprovado, mas falha na criação do usuário.', ['status' => 'error']);
                }

            } else {
                 // Pagamento ainda Pendente na API
                 jsonResponse(true, 'Aguardando pagamento.', ['status' => 'pending']);
            }
        }
    }
    
    // Se o status não é pending ou ocorreu erro na consulta, retorna o status atual.
    jsonResponse(true, 'Status atual.', ['status' => $currentStatus]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    logEvent('check_status_exception', "Exceção no check_payment_status: " . $e->getMessage(), $transactionId ?? 0);
    jsonResponse(false, 'Ocorreu um erro interno. Consulte o log.');
}