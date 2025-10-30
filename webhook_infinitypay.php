<?php
// webhook_infinitypay.php - Recebe e processa as notificações da InfinitePay

require_once 'config.php';
require_once 'MikrotikAPI.php';

// OBS: A função sendCredentialsEmail() deve estar definida em config.php.

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
$paymentStatus = sanitizeInput($data['status'] ?? 'pending'); 

if ($paymentStatus === 'paid' || $paymentStatus === 'approved') {
    try {
        $db = Database::getInstance()->getConnection();
        $db->beginTransaction();

        // 3. Atualizar o status da transação
        $stmt = $db->prepare("
            UPDATE transactions 
            SET 
                status = 'approved', 
                infinitypay_transaction_nsu = ?, 
                payment_method = ?, 
                updated_at = NOW() 
            WHERE id = ? AND status != 'approved'
        ");
        $stmt->execute([
            $transactionNsu, 
            $captureMethod, 
            $transactionId
        ]);
        
        $rowCount = $stmt->rowCount();

        // Se a transação já foi processada, apenas confirma OK para o webhook
        if ($rowCount === 0) {
            $db->commit();
            logEvent('webhook_info', "Transação ID $transactionId já estava aprovada. Webhook ignorado.", $transactionId);
            http_response_code(200);
            jsonResponse(true, 'Pagamento já processado.');
        }

        // 4. Buscar a transação e os dados do plano/cliente
        $stmt = $db->prepare("
            SELECT 
                t.id, t.plan_id, t.customer_id, 
                c.email as customer_email, c.name as customer_name, 
                p.mikrotik_profile_name, p.duration_days, p.duration_hours 
            FROM transactions t
            JOIN plans p ON t.plan_id = p.id
            JOIN customers c ON t.customer_id = c.id
            WHERE t.id = ?
        ");
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch();

        if (!$transaction) {
            $db->rollBack();
            logEvent('webhook_error', "Transação ID $transactionId não encontrada após aprovação.", $transactionId);
            http_response_code(404);
            jsonResponse(false, 'Transação não encontrada.');
        }

        // 5. Gerar Usuário e Senha Aleatórios usando o método STATIC da MikrotikAPI
        $credentials = MikrotikAPI::generateRandomCredentials($transaction['customer_name']);
        $username = $credentials['username'];
        $password = $credentials['password'];
        $profile = $transaction['mikrotik_profile_name'] ?? 'default'; // Nome do perfil do plano
        $comment = 'ID Transacao: ' . $transactionId . ' - Cliente: ' . $transaction['customer_name'];

        // 6. Criar o usuário no MikroTik
        $mikrotikApi = new MikrotikAPI();
        $userCreationResult = $mikrotikApi->createHotspotUser($username, $password, $profile, $comment);

        // Mesmo se a criação no MikroTik falhar, a transação no DB está "approved".
        // A falha no MikroTik é um erro de negócio que deve ser tratado via log.
        // Se a transação no DB estiver atualizada, a resposta deve ser 200 OK para o webhook.

        // 7. Salvar as credenciais no BD (Sempre salva se o pagamento foi aprovado)
        
        $duration = 0;
        $duration += ($transaction['duration_days'] ?? 0) * 24 * 3600; 
        $duration += ($transaction['duration_hours'] ?? 0) * 3600; 

        $expiresAt = ($duration > 0) ? date('Y-m-d H:i:s', time() + $duration) : null;
        $status = $userCreationResult['success'] ? 'active' : 'pending_mikrotik'; // Novo status: se falhou no MT, marca para re-tentativa

        $stmt = $db->prepare("
            INSERT INTO hotspot_users (transaction_id, plan_id, customer_id, username, password, profile_name, expires_at, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                username = VALUES(username), 
                password = VALUES(password), 
                profile_name = VALUES(profile_name), 
                expires_at = VALUES(expires_at), 
                status = VALUES(status), 
                updated_at = NOW()
        ");
        $stmt->execute([
            $transactionId, 
            $transaction['plan_id'], 
            $transaction['customer_id'], 
            $username, 
            $password, 
            $profile, 
            $expiresAt,
            $status
        ]);

        // 8. Enviar credenciais por e-mail (SE o usuário foi criado ou se a falha for pequena)
        if ($userCreationResult['success']) {
            sendCredentialsEmail(
                $transaction['customer_email'], 
                $username, 
                $password, 
                $transaction['mikrotik_profile_name']
            );
        }
        
        $db->commit();
        
        if ($userCreationResult['success']) {
            logEvent('webhook_success', "Pagamento $invoiceSlug aprovado. Usuário MikroTik $username criado.", $transactionId);
            jsonResponse(true, 'Pagamento aprovado, usuário MikroTik criado e transação atualizada.');
        } else {
            logEvent('webhook_warning', "Pagamento $invoiceSlug aprovado. Falha ao criar usuário MikroTik, marcado como 'pending_mikrotik'. Mensagem: " . $userCreationResult['message'], $transactionId);
            jsonResponse(true, 'Pagamento aprovado. Usuário no MikroTik falhou, mas foi agendado para re-tentativa.');
        }

        http_response_code(200); // SEMPRE RETORNA 200 OK PARA A INFINITE PAY

    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        logEvent('webhook_exception', "Exceção no webhook InfinitePay: " . $e->getMessage(), $transactionId ?? 0);
        http_response_code(500); // Erro de servidor (pode fazer a IP re-enviar)
        jsonResponse(false, 'Ocorreu um erro interno. Por favor, tente novamente.');
    }
} else {
    // Para outros status (Ex: pending, cancelled).
    logEvent('webhook_info', "Status InfinitePay recebido: $paymentStatus. Nenhuma ação de aprovação necessária.", $transactionId ?? 0);
    http_response_code(200); // Responder OK para que a InfinitePay não re-envie.
    jsonResponse(true, 'Status recebido. Nenhuma ação de aprovação necessária.');
}