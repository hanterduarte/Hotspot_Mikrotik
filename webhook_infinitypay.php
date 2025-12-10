<?php
// webhook_infinitypay.php - Processa notificações da InfinitePay e salva credenciais de acesso

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

        // 3. Buscar transação APENAS da tabela transactions (Evita falha por JOIN de FK)
        $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ? AND payment_status = 'pending'");
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch();

        if (!$transaction) {
            http_response_code(200);
            jsonResponse(true, 'Transação já processada ou inválida.');
            $db->rollBack();
            return;
        }

        // 🚨 VERIFICAÇÃO DE CHAVE ESTRANGEIRA (Customer ID)
        if (empty($transaction['customer_id']) || !is_numeric($transaction['customer_id'])) {
            $db->rollBack();
            logEvent('webhook_error', "Transação $transactionId não possui um customer_id válido.");
            http_response_code(500);
            jsonResponse(false, 'Erro interno: ID do cliente ausente na transação. A transação deve ser refeita.');
            return;
        }

        // 4. Buscar detalhes do plano (mikrotik_profile e duration_seconds)
        $stmt_plan = $db->prepare("SELECT mikrotik_profile, duration_seconds FROM plans WHERE id = ?"); 
        $stmt_plan->execute([$transaction['plan_id']]);
        $plan = $stmt_plan->fetch();

        if (!$plan) {
            $db->rollBack();
            logEvent('webhook_error', "Plano ID {$transaction['plan_id']} não encontrado para TX: $transactionId");
            http_response_code(500);
            jsonResponse(false, 'Erro interno: Plano não configurado.');
            return;
        }
        
        // 5. ATIVAR CLIENTE (Criação de Usuário Hotspot no Mikrotik)
        $mt = new MikrotikAPI();
        $provisionResult = $mt->provisionHotspotUser(
            $transaction['plan_id'], 
            $transaction['client_ip'] 
        );

        if (!$provisionResult['success']) {
            $db->rollBack();
            logEvent('mikrotik_error', "Falha ao provisionar usuário no Mikrotik. TX: $transactionId. Erro: " . $provisionResult['message']);
            http_response_code(500); 
            jsonResponse(false, 'Pagamento aprovado, mas falha ao criar usuário.');
            return;
        }
        
        // ======================================================================
        // CÁLCULO E INSERÇÃO DE CREDENCIAIS (CORRIGIDO: Cálculo da Expiração)
        // ======================================================================
        $durationSeconds = intval($plan['duration_seconds']);
        $hasDuration = $durationSeconds > 0;

        $expiresAt = NULL;
        if ($hasDuration) {
            // Calcula a data e hora futura no PHP e formata para o MySQL (YYYY-MM-DD HH:MM:SS)
            $expiresAt = date('Y-m-d H:i:s', time() + $durationSeconds);
        }

        // COLUNAS: expires_at é agora sempre incluída na estrutura SQL
        $insertColumns = "transaction_id, plan_id, customer_id, username, password, mikrotik_profile, expires_at, created_at"; 
        $insertPlaceholders = "?, ?, ?, ?, ?, ?, ?, NOW()";

        $params = [
            $transactionId,
            $transaction['plan_id'],
            $transaction['customer_id'],
            $provisionResult['username'],
            $provisionResult['password'],
            $provisionResult['mikrotik_profile'],
            $expiresAt // <-- A data já calculada ou NULL
        ];
        
        // 6b. Salvar CREDENCIAIS no banco de dados (Tabela hotspot_users)
        $insertSql = "INSERT INTO hotspot_users ({$insertColumns}) VALUES ({$insertPlaceholders})";
        $stmt = $db->prepare($insertSql);
        $stmt->execute($params);

        // 6. ATUALIZAR STATUS DA TRANSAÇÃO (Usando a estrutura de backup)
        $stmt = $db->prepare("
            UPDATE transactions 
            SET payment_status = 'approved',
                infinitypay_order_id = ?,
                paid_at = NOW(),
                gateway = 'infinitepay_checkout',
                payment_method = ?,
                payment_id = ?,
                updated_at = NOW(),
                gateway_response = JSON_SET(COALESCE(gateway_response, '{}'), '$.transaction_nsu', ?)
            WHERE id = ?
        ");
        
        $stmt->execute([
            $transactionNsu,     // 1. infinitypay_order_id
            $captureMethod,      // 2. payment_method
            $invoiceSlug,        // 3. payment_id
            $transactionNsu,     // 4. JSON_SET
            $transactionId       // 5. WHERE id
        ]);
        
        // 7. REMOVER BYPASS TEMPORÁRIO (Se aplicável)

        $db->commit();
        logEvent('webhook_success', "Pagamento e Provisionamento SUCESSO para TX: $transactionId. Usuário: {$provisionResult['username']}");

        http_response_code(200);
        jsonResponse(true, 'Pagamento processado com sucesso e usuário criado.');

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
// =====================================================================
} else {
    // Lógica para outros status (cancelado, falha, etc.)
    if (in_array($paymentStatus, ['cancelled', 'failed', 'expired'])) {
         try {
            $db->beginTransaction();
            $stmt = $db->prepare("UPDATE transactions SET payment_status = ?, updated_at = NOW() WHERE id = ? AND payment_status = 'pending'");
            $stmt->execute([$paymentStatus, $transactionId]);
            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            logEvent('webhook_error', "Erro ao atualizar status '$paymentStatus': {$e->getMessage()} | TX: $transactionId");
        }
    }
    
    http_response_code(200);
    jsonResponse(true, "Status '$paymentStatus' recebido. Nenhuma ação de ativação necessária.");
}
?>