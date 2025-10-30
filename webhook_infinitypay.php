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

// Capturar o transaction_nsu (ID único do pagamento na IP)
$transactionNsu = sanitizeInput($data['transaction_nsu'] ?? ''); 
// Capturar o método de pagamento/captura (pix, credit_card, etc.)
$captureMethod = sanitizeInput($data['capture_method'] ?? 'infinitepay_checkout'); 

// O status no webhook da InfinitePay deve ser 'paid' ou 'approved' para processamento
$paymentStatus = sanitizeInput(strtolower($data['status'] ?? 'paid')); 

if ($paymentStatus === 'paid' || $paymentStatus === 'approved') {
    
    try {
        $db->beginTransaction();
        
        // a. Buscar transação pelo ID interno (order_nsu)
        $stmt = $db->prepare("
            SELECT t.*, c.name as customer_name, c.email as customer_email
            FROM transactions t
            JOIN customers c ON t.customer_id = c.id
            WHERE t.id = ? AND t.status IN ('pending', 'processing')
            LIMIT 1
        ");
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch();

        if (!$transaction) {
            $db->rollBack();
            logEvent('webhook_warning', "Transação $transactionId não encontrada ou status já processado.", $transactionId);
            http_response_code(200); // Retorna 200 para a InfinityPay para não re-enviar
            jsonResponse(true, 'Transação não encontrada ou já processada.');
        }

        // b. Atualizar o status da transação no DB
        $stmt = $db->prepare("
            UPDATE transactions 
            SET status = 'approved', 
                infinitypay_order_id = ?, 
                payment_method = ?, 
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$transactionNsu, $captureMethod, $transactionId]);

        
        // --- INÍCIO DA LÓGICA DE ATIVAÇÃO DO HOTSPOT ---

        // 1. Obter o perfil MikroTik do plano (Requer coluna 'mikrotik_profile' na tabela 'plans')
        $mikrotikProfile = getPlanProfile($db, $transaction['plan_id']);

        // Assumindo que você tem 'mikrotik_server' na tabela settings ou use um padrão
        $mikrotikServer = getSetting('mikrotik_server', 'hotspot1'); 
        $mikrotikComment = "Venda ID: {$transactionId} - Cliente: {$transaction['customer_name']}";

        if (empty($mikrotikProfile)) {
            $db->rollBack();
            logEvent('webhook_error', "Pagamento $invoiceSlug aprovado, mas perfil MikroTik não encontrado (Plan ID: {$transaction['plan_id']}).", $transactionId);
            http_response_code(400); 
            jsonResponse(false, 'Perfil Hotspot não configurado para o plano.');
        }

        // 2. Gerar credenciais
        $username = generateUsername();
        $password = generatePassword();

        // 3. Conectar e criar o usuário no MikroTik
        $mt = new MikrotikAPI(); 
        $userCreationResult = $mt->createHotspotUser(
            $username, 
            $password, 
            $mikrotikProfile, 
            $mikrotikServer, 
            $mikrotikComment
        );

        if ($userCreationResult['success']) {
            // 4. Salvar credenciais no DB (tabela hotspot_users)
            // PASSANDO customer_id e NULL para expires_at, conforme sua tabela.
            $saveResult = saveHotspotUser(
                $db, 
                $transactionId, 
                $transaction['customer_id'], // Novo parâmetro
                $transaction['plan_id'], 
                $username, 
                $password,
                null // expires_at é NULL, pois o Mikrotik a gerencia
            );

            if ($saveResult) {
                $db->commit(); // SUCESSO! Finaliza a transação do DB.
                logEvent('webhook_success', "Pagamento $invoiceSlug aprovado. Usuário {$username} criado e salvo.", $transactionId);
                
                // *** LOGICA DE ENVIO DE EMAIL/SMS AQUI ***
                
                http_response_code(200);
                jsonResponse(true, 'Pagamento aprovado e usuário criado.');
            } else {
                // Falha ao salvar no DB
                $db->rollBack(); 
                logEvent('webhook_error', "Falha ao salvar usuário $username no DB após criação no MikroTik. Transação: $transactionId", $transactionId);
                http_response_code(400);
                jsonResponse(false, 'Falha interna ao salvar credenciais.');
            }
        } else {
            // Falha na criação do MikroTik
            $db->rollBack();
            logEvent('webhook_error', "Pagamento $invoiceSlug aprovado, mas falha ao criar usuário MikroTik: " . $userCreationResult['message'], $transactionId);
            http_response_code(400); 
            jsonResponse(false, 'Falha interna ao criar usuário MikroTik.');
        }

        // --- FIM DA LÓGICA DE ATIVAÇÃO DO HOTSPOT ---


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
    jsonResponse(true, 'Status recebido. Nenhuma ação de ativação necessária.');
}