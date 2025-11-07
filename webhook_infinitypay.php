<?php
// webhook_infinitypay.php - Processa notificações da InfinitePay e remove bypass
// VERSÃO COM REMOÇÃO DO BYPASS COMENTADA PARA TESTES DE TIMEOUT

require_once 'config.php';
require_once 'MikrotikAPI.php'; //

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

        // 3. Buscar transação
        $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ? AND payment_status = 'pending'");
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch();

        if (!$transaction) {
            $db->rollBack();
            logEvent('webhook_warning', "Transação ID $transactionId não encontrada ou status não é 'pending'.");
            http_response_code(200);
            jsonResponse(true, 'Transação já processada ou inválida.');
            return;
        }

        // 4. ATUALIZAR STATUS DA TRANSAÇÃO
        $stmt = $db->prepare("
            UPDATE transactions 
            SET payment_status = ?, updated_at = NOW(), transaction_nsu = ?, capture_method = ?
            WHERE id = ?
        ");
        $stmt->execute([$paymentStatus, $transactionNsu, $captureMethod, $transactionId]);

        // 5. ATIVAR CLIENTE (Criação de Usuário Hotspot ou IP Binding final)
        $mt = new MikrotikAPI(); //
        $provisionResult = $mt->provisionHotspotUser(
            $transaction['plan_id'], 
            $transaction['client_ip']
        );

        if (!$provisionResult['success']) {
            $db->rollBack();
            logEvent('mikrotik_error', "Falha ao provisionar usuário. TX: $transactionId. Erro: " . $provisionResult['message']);
            jsonResponse(false, 'Pagamento aprovado, mas falha ao liberar internet.');
            return;
        }

        // 6. REMOVER BYPASS TEMPORÁRIO (Address List)
        /* COMENTADO PARA TESTES: Deixando o MikroTik remover via 'timeout' (2 minutos)
        if ($transaction['mikrotik_bypass_id']) {
            $mt = new MikrotikAPI();
            $removeResult = $mt->removeClientBypass($transactionId);
            if (!$removeResult['success']) {
                logEvent('mikrotik_warning', "Falha ao remover bypass Address List. TX: $transactionId. " . $removeResult['message']);
            } else {
                logEvent('mikrotik_info', "Bypass temporário removido com sucesso. TX: $transactionId");
            }
        }
        */

        $db->commit();
        logEvent('webhook_success', "Pagamento e Provisionamento SUCESSO para TX: $transactionId");

        http_response_code(200);
        jsonResponse(true, 'Pagamento processado com sucesso e internet liberada.', [
            'transaction_id' => $transactionId,
            'provision_details' => $provisionResult
        ]);

    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        logEvent('webhook_exception', "Exceção no processamento do webhook: {$e->getMessage()} | TX: $transactionId");
        http_response_code(500);
        jsonResponse(false, 'Erro interno do servidor: ' . $e->getMessage());
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