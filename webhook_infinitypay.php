<?php
// webhook_infinitypay.php - Recebe e processa as notificações da InfinitePay

require_once 'config.php';
require_once 'MikrotikAPI.php'; // Inclui as funções de provisionamento e bypass

header('Content-Type: application/json');

// 1. Receber o corpo da requisição
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

logEvent('infinitypay_webhook', "Webhook Recebido: " . $payload);

// 2. Validação de dados essenciais
if (empty($data) || !isset($data['order_nsu']) || !isset($data['invoice_slug'])) {
    http_response_code(400);
    jsonResponse(false, 'Dados do webhook inválidos (order_nsu ou invoice_slug faltando).');
}

$transactionId = intval($data['order_nsu']); // Seu ID interno
$invoiceSlug = sanitizeInput($data['invoice_slug']);

// Capturar o transaction_nsu (ID único do pagamento na IP)
$transactionNsu = sanitizeInput($data['transaction_nsu'] ?? ''); 
// Capturar o método de pagamento/captura (pix, credit_card, etc.)
$captureMethod = sanitizeInput($data['capture_method'] ?? 'infinitepay_checkout'); 

// O status no webhook da InfinitePay deve ser 'paid' ou 'approved' para processamento
$paymentStatus = sanitizeInput(strtolower($data['status'] ?? 'paid')); 

$db = Database::getInstance()->getConnection();

if ($paymentStatus === 'paid' || $paymentStatus === 'approved') {
    
    try {
        $db->beginTransaction();
        
        // a. Buscar transação pelo ID interno (order_nsu)
        $stmt = $db->prepare("
            SELECT t.*, c.name as customer_name, c.email, p.duration_seconds, p.name as plan_name
            FROM transactions t
            JOIN customers c ON t.customer_id = c.id
            JOIN plans p ON t.plan_id = p.id /* Necessário para createHotspotUser e email */
            WHERE t.id = ? AND t.payment_status = 'pending'
        ");
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch();

        // Verificar se a transação existe e ainda está pendente
        if (!$transaction) {
             // Pode ser uma re-notificação de um pagamento já processada
             logEvent('webhook_info', "Transação ID $transactionId não encontrada ou já processada.", $transactionId);
             http_response_code(200); // Responder OK para evitar reenvio
             jsonResponse(true, 'Transação já processada.');
             return;
        }

        // ----------------------------------------------------
        // ATUALIZAR: Atualizar a transação no banco de dados
        // ----------------------------------------------------
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
        
        // ----------------------------------------------------
        // B. LÓGICA DO MIKROTIK (Provisionamento de Usuário)
        // ----------------------------------------------------
        
        $mt = new MikrotikAPI(); // Classe MikrotikAPI
        $userCreationResult = $mt->createHotspotUser($transaction);
        
        if ($userCreationResult['success']) {
            
            // ======================================================================
            // REMOVER Bypass de IP após provisionamento
            // ======================================================================
            $mikrotikBypassId = $transaction['mikrotik_bypass_id'] ?? null;
            
            if (!empty($mikrotikBypassId)) {
                $removeResult = $mt->removeBypass($mikrotikBypassId); // Remove o ID obtido no process_payment
                
                if (!$removeResult['success']) {
                     // Loga o erro, mas não reverte a transação de venda.
                     logEvent('mikrotik_warning', "Falha ao remover IP Bypass ID $mikrotikBypassId. Erro: " . $removeResult['message']);
                } else {
                     logEvent('mikrotik_info', "IP Bypass ID $mikrotikBypassId removido com sucesso.");
                }
            } else {
                logEvent('mikrotik_warning', "Transação $transactionId não tinha mikrotik_bypass_id para remoção.");
            }
            // ======================================================================

            // Sucesso total, comitar a transação e as credenciais
            $db->commit();
            
            // Envio de E-mail com as credenciais
            sendHotspotCredentialsEmail(
                $transaction['email'], 
                $userCreationResult['username'], 
                $userCreationResult['password'], 
                $userCreationResult['expires_at'] ?? 'Não Definido', 
                $transaction['plan_name']
            );

            logEvent('webhook_success', "Pagamento $invoiceSlug aprovado. Usuário criado e e-mail enviado.", $transactionId);
            http_response_code(200);
            
            // Retorna as credenciais na resposta JSON
            jsonResponse(true, 'Pagamento aprovado e usuário criado.', [
                'username' => $userCreationResult['username'],
                'password' => $userCreationResult['password']
            ]);
        } else {
            // Falha na criação/salvamento do usuário, reverter a transação
            $db->rollBack();
            logEvent('webhook_error', "Pagamento $invoiceSlug aprovado, mas falha ao criar usuário MikroTik/DB: " . $userCreationResult['message'], $transactionId);
            http_response_code(500); 
            jsonResponse(false, 'Falha interna ao criar usuário MikroTik: ' . $userCreationResult['message']);
        }


    } catch (Throwable $e) { 
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        logEvent('webhook_exception', "Exceção no webhook InfinitePay: " . $e->getMessage(), $transactionId ?? 0);
        http_response_code(500); 
        jsonResponse(false, 'Ocorreu um erro interno. Por favor, tente novamente.');
    }
} else {
    // Para outros status (Ex: pending, cancelled).
    logEvent('webhook_info', "Status InfinitePay recebido: $paymentStatus. Nenhuma ação de ativação tomada.", $transactionId);
    http_response_code(200); 
    jsonResponse(true, 'Status recebido, nenhuma ação de ativação necessária.');
}
?>