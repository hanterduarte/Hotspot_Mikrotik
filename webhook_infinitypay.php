<?php
// webhook_infinitypay.php - Processa notificações da InfinitePay e remove bypass
// VERSÃO COMPLETA COM TRATAMENTO ROBUSTO

require_once 'config.php';
require_once 'MikrotikAPI.php';

header('Content-Type: application/json');

// 1. Receber o corpo da requisição
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

logEvent('infinitypay_webhook', "Webhook Recebido: " . $payload);

// 2. Validação de dados essenciais
if (empty($data) || !isset($data['order_nsu']) || !isset($data['invoice_slug'])) {
    http_response_code(400);
    jsonResponse(false, 'Dados do webhook inválidos (order_nsu ou invoice_slug ausentes).');
}

$transactionId = intval($data['order_nsu']);
$invoiceSlug = sanitizeInput($data['invoice_slug']);
$transactionNsu = sanitizeInput($data['transaction_nsu'] ?? '');
$captureMethod = sanitizeInput($data['capture_method'] ?? 'infinitepay_checkout');
$paymentStatus = sanitizeInput(strtolower($data['status'] ?? 'paid'));

$db = Database::getInstance()->getConnection();

// ======================================================================
// PROCESSAR PAGAMENTO APROVADO
// ======================================================================
if ($paymentStatus === 'paid' || $paymentStatus === 'approved') {
    
    try {
        $db->beginTransaction();
        
        // a. Buscar transação completa (incluindo mikrotik_bypass_id e client_ip)
        $stmt = $db->prepare("
            SELECT 
                t.*,
                c.name as customer_name,
                c.email,
                p.duration_seconds,
                p.name as plan_name
            FROM transactions t
            JOIN customers c ON t.customer_id = c.id
            JOIN plans p ON t.plan_id = p.id
            WHERE t.id = ? AND t.payment_status = 'pending'
        ");
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch();

        if (!$transaction) {
             logEvent('webhook_info', "Transação ID $transactionId não encontrada ou já processada.", $transactionId);
             http_response_code(200);
             jsonResponse(true, 'Transação já processada ou não encontrada.');
             return;
        }

        // b. Atualizar status da transação para 'success'
        $updateStmt = $db->prepare("
            UPDATE transactions
            SET 
                payment_status = 'success',
                infinitypay_order_id = ?,
                paid_at = NOW(),
                gateway = ?,
                payment_method = ?,
                payment_id = ?,
                updated_at = NOW(),
                gateway_response = JSON_SET(COALESCE(gateway_response, '{}'), '$.transaction_nsu', ?)
            WHERE id = ?
        ");
        $updateStmt->execute([
            $transactionNsu,
            'infinitepay_checkout',
            $captureMethod,
            $invoiceSlug,
            $transactionNsu,
            $transactionId
        ]);
        
        // ======================================================================
        // MIKROTIK: Provisionar usuário e REMOVER bypass
        // ======================================================================
        
        $mt = new MikrotikAPI();
        $userCreationResult = $mt->createHotspotUser($transaction);
        
        if ($userCreationResult['success']) {
            
            // ======================================================================
            // REMOVER BYPASS - Com tratamento robusto
            // ======================================================================
            $mikrotikBypassId = $transaction['mikrotik_bypass_id'] ?? null;
            $clientIp = $transaction['client_ip'] ?? 'N/A';
            
            if (!empty($mikrotikBypassId)) {
                logEvent('webhook_info', "Iniciando remoção de bypass. ID: $mikrotikBypassId | IP: $clientIp | TX: $transactionId");
                
                $removeResult = $mt->removeBypass($mikrotikBypassId);
                
                if ($removeResult['success']) {
                    logEvent('mikrotik_success', "✅ Bypass removido com sucesso. ID: $mikrotikBypassId | IP: $clientIp | TX: $transactionId");
                    
                    // Limpar o campo na transação após remoção bem-sucedida
                    $stmt = $db->prepare("UPDATE transactions SET mikrotik_bypass_id = NULL WHERE id = ?");
                    $stmt->execute([$transactionId]);
                    
                } else {
                    // IMPORTANTE: Não reverte a venda, apenas loga o erro
                    logEvent('mikrotik_warning', "⚠️ Falha ao remover bypass ID $mikrotikBypassId: {$removeResult['message']} | IP: $clientIp | TX: $transactionId");
                    
                    // Adiciona nota na resposta do gateway para auditoria
                    $stmt = $db->prepare("
                        UPDATE transactions 
                        SET gateway_response = JSON_SET(
                            COALESCE(gateway_response, '{}'), 
                            '$.bypass_removal_error', 
                            ?
                        )
                        WHERE id = ?
                    ");
                    $stmt->execute([$removeResult['message'], $transactionId]);
                }
            } else {
                logEvent('mikrotik_warning', "⚠️ Transação $transactionId sem mikrotik_bypass_id para remover. IP: $clientIp");
            }
            // ======================================================================

            // Commit da transação (venda confirmada)
            $db->commit();
            
            // Enviar credenciais por e-mail
            sendHotspotCredentialsEmail(
                $transaction['email'], 
                $userCreationResult['username'], 
                $userCreationResult['password'], 
                $userCreationResult['expires_at'] ?? 'Não Definido', 
                $transaction['plan_name']
            );

            logEvent('webhook_success', "✅ Pagamento aprovado e processado. Usuário: {$userCreationResult['username']} | TX: $transactionId");
            http_response_code(200);
            
            jsonResponse(true, 'Pagamento aprovado e usuário criado com sucesso.', [
                'transaction_id' => $transactionId,
                'username' => $userCreationResult['username'],
                'password' => $userCreationResult['password'],
                'bypass_removed' => !empty($mikrotikBypassId) && ($removeResult['success'] ?? false)
            ]);
            
        } else {
            // Falha na criação do usuário - REVERTE a transação
            $db->rollBack();
            
            logEvent('webhook_error', "❌ Pagamento aprovado mas falha ao criar usuário: {$userCreationResult['message']} | TX: $transactionId");
            http_response_code(500);
            
            jsonResponse(false, 'Erro ao provisionar acesso. A transação será reprocessada.', [
                'transaction_id' => $transactionId,
                'error' => $userCreationResult['message']
            ]);
        }

    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        logEvent('webhook_exception', "❌ Exceção no webhook: {$e->getMessage()} | TX: " . ($transactionId ?? 0) . " | Trace: " . $e->getTraceAsString());
        http_response_code(500);
        
        jsonResponse(false, 'Erro interno no processamento do webhook.', [
            'transaction_id' => $transactionId ?? 0,
            'error' => $e->getMessage()
        ]);
    }
    
// ======================================================================
// PROCESSAR OUTROS STATUS (pending, cancelled, failed, etc)
// ======================================================================
} else {
    logEvent('webhook_info', "Status recebido: $paymentStatus. Nenhuma ação de ativação tomada. TX: $transactionId");
    
    // Se for cancelamento, registra mas não falha
    if (in_array($paymentStatus, ['cancelled', 'failed', 'expired'])) {
        try {
            $db->beginTransaction();
            
            // Atualizar status da transação
            $stmt = $db->prepare("
                UPDATE transactions 
                SET payment_status = ?, updated_at = NOW()
                WHERE id = ? AND payment_status = 'pending'
            ");
            $stmt->execute([$paymentStatus, $transactionId]);
            
            $db->commit();
            
            logEvent('webhook_info', "Status '$paymentStatus' registrado para TX: $transactionId");
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            logEvent('webhook_error', "Erro ao atualizar status '$paymentStatus': {$e->getMessage()} | TX: $transactionId");
        }
    }
    
    http_response_code(200);
    jsonResponse(true, "Status '$paymentStatus' recebido. Nenhuma ação de ativação necessária.", [
        'transaction_id' => $transactionId,
        'status' => $paymentStatus
    ]);
}
?>