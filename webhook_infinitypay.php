<?php
// webhook_infinitypay.php - Recebe e processa as notificações da InfinitePay

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
    jsonResponse(false, 'Dados do webhook inválidos (order_nsu ou invoice_slug faltando).');
}

$transactionId = intval($data['order_nsu']); // Seu ID interno
$invoiceSlug = sanitizeInput($data['invoice_slug']);
// O status no webhook da InfinitePay deve ser 'paid' ou 'approved' para processamento
$paymentStatus = sanitizeInput(strtolower($data['status'] ?? 'paid')); 

$db = Database::getInstance()->getConnection();

if ($paymentStatus === 'paid' || $paymentStatus === 'approved') {
    
    try {
        $db->beginTransaction();
        
        // a. Buscar transação pelo ID interno (order_nsu)
        $stmt = $db->prepare("
            SELECT t.*, c.name as customer_name, c.email, p.duration, p.duration_seconds
            FROM transactions t
            JOIN customers c ON t.customer_id = c.id
            JOIN plans p ON t.plan_id = p.id
            WHERE t.id = ? AND t.payment_status != 'approved'
            FOR UPDATE
        ");
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch();
        
        if (!$transaction) {
            $db->rollBack();
            logEvent('webhook_info', "Transação ID $transactionId não encontrada ou já aprovada. Ignorando.");
            http_response_code(200); 
            jsonResponse(true, 'Transação não encontrada ou já processada.');
        }

        // b. Atualizar status e referências
        $stmt = $db->prepare("
            UPDATE transactions SET 
                payment_status = 'approved', 
                infinitypay_order_id = ?, 
                payment_id = ?, 
                gateway_response = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $transactionId, // order_nsu
            $invoiceSlug,   // invoice_slug
            $payload,       // Salva o payload completo
            $transactionId
        ]);
        
        // c. Criar usuário no MikroTik
        $mt = new MikrotikAPI();
        
        // *** IMPORTANTE: A função createHotspotUser precisa ser implementada
        // e ser capaz de criar e retornar as credenciais para o log/email
        $userCreationResult = createHotspotUser($db, $mt, $transaction, $transaction['duration_seconds']);
        
        if ($userCreationResult['success']) {
            $db->commit();
            logEvent('webhook_success', "Pagamento $invoiceSlug aprovado. Usuário criado.", $transactionId);
            http_response_code(200);
            jsonResponse(true, 'Pagamento aprovado e usuário criado.');
        } else {
            $db->rollBack();
            logEvent('webhook_error', "Pagamento $invoiceSlug aprovado, mas falha ao criar usuário MikroTik: " . $userCreationResult['message'], $transactionId);
            http_response_code(400); // Erro de negócio
            jsonResponse(false, 'Falha interna ao criar usuário MikroTik.');
        }

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        logEvent('webhook_exception', "Exceção no webhook InfinitePay: " . $e->getMessage(), $transactionId ?? 0);
        http_response_code(500); // Erro de servidor
        jsonResponse(false, 'Erro interno do servidor.');
    }
} else {
    // Para outros status (Ex: pending, cancelled).
    logEvent('webhook_info', "Status InfinitePay recebido: $paymentStatus. Nenhuma ação de ativação tomada.", $transactionId ?? 0);
    http_response_code(200);
    jsonResponse(true, 'Status recebido. Nenhuma ação necessária.');
}
?>