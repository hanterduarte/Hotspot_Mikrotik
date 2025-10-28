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
$paymentStatus = sanitizeInput(strtolower($data['status'] ?? ''));

if (!$paymentStatus) {
    http_response_code(400);
    jsonResponse(false, 'Status do pagamento não encontrado no webhook.');
}

$db = Database::getInstance()->getConnection();

if ($paymentStatus === 'paid' || $paymentStatus === 'approved') {

    try {
        $db->beginTransaction();

        // a. Buscar transação pelo ID interno (order_nsu)
        $stmt = $db->prepare("
            SELECT t.*, c.name as customer_name, c.email, p.name as plan_name, p.duration_seconds
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

        // b. Atualizar status da transação
        $stmt = $db->prepare("UPDATE transactions SET payment_status = 'approved', gateway_response = ? WHERE id = ?");
        $stmt->execute([$payload, $transactionId]);

        // c. Criar usuário no MikroTik
        $mt = new MikrotikAPI();
        $userCreationResult = createHotspotUser($db, $mt, $transaction, $transaction['duration_seconds']);

        if ($userCreationResult['success']) {
            // Enviar email com as credenciais
            $emailSubject = "Seu acesso WiFi foi liberado!";
            $emailBody = "
                <h1>Olá, {$transaction['customer_name']}!</h1>
                <p>Seu pagamento foi aprovado e seu acesso à internet já está disponível.</p>
                <h3>Suas credenciais para o plano {$transaction['plan_name']}:</h3>
                <ul>
                    <li><strong>Usuário:</strong> {$userCreationResult['username']}</li>
                    <li><strong>Senha:</strong> {$userCreationResult['password']}</li>
                </ul>
                <p>Para usar, basta se conectar à nossa rede WiFi e inserir os dados acima.</p>
                <p>Obrigado por escolher nossos serviços!</p>
            ";
            sendEmail($transaction['email'], $emailSubject, $emailBody);

            $db->commit();
            logEvent('webhook_success', "Pagamento $invoiceSlug aprovado. Usuário criado e email enviado.", $transactionId);
            http_response_code(200);
            jsonResponse(true, 'Pagamento processado com sucesso.');

        } else {
            $db->rollBack();
            logEvent('webhook_error', "Pagamento $invoiceSlug aprovado, mas falha ao criar usuário MikroTik: " . $userCreationResult['message'], $transactionId);
            http_response_code(500);
            jsonResponse(false, 'Falha interna ao criar usuário no hotspot.');
        }

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        logEvent('webhook_exception', "Exceção no webhook InfinitePay: " . $e->getMessage(), $transactionId);
        http_response_code(500);
        jsonResponse(false, 'Erro interno do servidor durante o processamento do webhook.');
    }
} else {
    // Para outros status (Ex: pending, cancelled, failed).
    $stmt = $db->prepare("UPDATE transactions SET payment_status = ? WHERE id = ?");
    $stmt->execute([$paymentStatus, $transactionId]);

    logEvent('webhook_info', "Status InfinitePay recebido: $paymentStatus. Transação atualizada.", $transactionId);
    http_response_code(200);
    jsonResponse(true, 'Status recebido e atualizado.');
}
?>