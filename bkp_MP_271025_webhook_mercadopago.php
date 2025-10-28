<?php
require_once 'config.php';
require_once 'MercadoPago.php';
require_once 'MikrotikAPI.php';

// Registrar log da requisição
$rawInput = file_get_contents('php://input');
logEvent('webhook_received', $rawInput);

// Decodificar dados
$data = json_decode($rawInput, true);

if (!$data) {
    http_response_code(400);
    exit('Invalid JSON');
}

// Verificar tipo de notificação
if (!isset($data['type'])) {
    http_response_code(200);
    exit('OK');
}

// Processar apenas notificações de pagamento
if ($data['type'] !== 'payment') {
    http_response_code(200);
    exit('OK');
}

// Obter ID do pagamento
$paymentId = $data['data']['id'] ?? null;

if (!$paymentId) {
    http_response_code(400);
    exit('Payment ID not found');
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Buscar informações do pagamento no Mercado Pago
    $mp = new MercadoPago();
    $paymentInfo = $mp->getPayment($paymentId);
    
    if (!$paymentInfo['success']) {
        logEvent('webhook_error', "Erro ao buscar pagamento: $paymentId");
        http_response_code(200);
        exit('OK');
    }
    
    $payment = $paymentInfo['payment'];
    $status = $payment['status'];
    $externalReference = $payment['external_reference'] ?? null;
    
    logEvent('webhook_payment_status', "Payment ID: $paymentId, Status: $status, Reference: $externalReference");
    
    if (!$externalReference) {
        logEvent('webhook_error', "External reference não encontrada para payment: $paymentId");
        http_response_code(200);
        exit('OK');
    }
    
    // Buscar transação
    $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ? OR payment_id = ?");
    $stmt->execute([$externalReference, $paymentId]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        logEvent('webhook_error', "Transação não encontrada: $externalReference");
        http_response_code(200);
        exit('OK');
    }
    
    // Atualizar status da transação
    $stmt = $db->prepare("UPDATE transactions SET payment_status = ?, gateway_response = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$status, json_encode($payment), $transaction['id']]);
    
    // Se pagamento aprovado, criar usuário no hotspot
    if ($status === 'approved' && $transaction['payment_status'] !== 'approved') {
        
        $db->beginTransaction();
        
        try {
            // Atualizar data de pagamento
            $stmt = $db->prepare("UPDATE transactions SET paid_at = NOW() WHERE id = ?");
            $stmt->execute([$transaction['id']]);
            
            // Buscar informações do plano
            $stmt = $db->prepare("SELECT * FROM plans WHERE id = ?");
            $stmt->execute([$transaction['plan_id']]);
            $plan = $stmt->fetch();
            
            // Buscar informações do cliente
            $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
            $stmt->execute([$transaction['customer_id']]);
            $customer = $stmt->fetch();
            
            if (!$plan || !$customer) {
                throw new Exception("Plano ou cliente não encontrado");
            }
            
            // Gerar credenciais
            $username = generateUsername('wifi');
            $password = generatePassword(8);
            
            // Calcular data de expiração
            $expiresAt = date('Y-m-d H:i:s', time() + $plan['duration_seconds']);
            
            // Salvar usuário no banco
            $stmt = $db->prepare("
                INSERT INTO hotspot_users (transaction_id, customer_id, username, password, plan_id, expires_at, active)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $transaction['id'],
                $customer['id'],
                $username,
                password_hash($password, PASSWORD_DEFAULT),
                $plan['id'],
                $expiresAt
            ]);
            
            $hotspotUserId = $db->lastInsertId();
            
            // Criar usuário no MikroTik
            $mikrotik = new MikrotikAPI();
            $result = $mikrotik->createHotspotUser($username, $password, 'default', $plan['duration']);
            
            if ($result['success']) {
                // Marcar como sincronizado
                $stmt = $db->prepare("UPDATE hotspot_users SET mikrotik_synced = 1 WHERE id = ?");
                $stmt->execute([$hotspotUserId]);
                
                logEvent('user_created', "Usuário criado: $username (MikroTik sincronizado)", $hotspotUserId);
            } else {
                logEvent('mikrotik_error', "Erro ao criar no MikroTik: " . json_encode($result), $hotspotUserId);
            }
            
            $db->commit();
            
            // Enviar email com credenciais (opcional)
            sendCredentialsEmail($customer['email'], $customer['name'], $username, $password, $plan['name'], $expiresAt);
            
            logEvent('payment_approved', "Pagamento aprovado e usuário criado para transação: " . $transaction['id'], $transaction['id']);
            
        } catch (Exception $e) {
            $db->rollBack();
            logEvent('webhook_error', "Erro ao criar usuário: " . $e->getMessage(), $transaction['id']);
        }
    }
    
    http_response_code(200);
    exit('OK');
    
} catch (Exception $e) {
    logEvent('webhook_exception', $e->getMessage());
    http_response_code(500);
    exit('Error');
}

// Função para enviar email com credenciais
function sendCredentialsEmail($email, $name, $username, $password, $planName, $expiresAt) {
    $subject = "Suas credenciais de acesso - WiFi Barato";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #1e88e5 0%, #0d47a1 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .credentials { background: white; padding: 20px; border-radius: 10px; margin: 20px 0; border-left: 4px solid #1e88e5; }
            .credential-item { margin: 10px 0; }
            .credential-label { font-weight: bold; color: #1e88e5; }
            .credential-value { font-size: 1.2em; color: #333; font-family: monospace; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 0.9em; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>WiFi Barato</h1>
                <p>Bem-vindo(a)!</p>
            </div>
            <div class='content'>
                <p>Olá, <strong>$name</strong>!</p>
                <p>Seu pagamento foi aprovado com sucesso! Abaixo estão suas credenciais de acesso:</p>
                
                <div class='credentials'>
                    <div class='credential-item'>
                        <span class='credential-label'>Plano:</span><br>
                        <span class='credential-value'>$planName</span>
                    </div>
                    <div class='credential-item'>
                        <span class='credential-label'>Usuário:</span><br>
                        <span class='credential-value'>$username</span>
                    </div>
                    <div class='credential-item'>
                        <span class='credential-label'>Senha:</span><br>
                        <span class='credential-value'>$password</span>
                    </div>
                    <div class='credential-item'>
                        <span class='credential-label'>Válido até:</span><br>
                        <span class='credential-value'>" . date('d/m/Y H:i', strtotime($expiresAt)) . "</span>
                    </div>
                </div>
                
                <p><strong>Como usar:</strong></p>
                <ol>
                    <li>Conecte-se à rede WiFi</li>
                    <li>Digite seu usuário e senha quando solicitado</li>
                    <li>Navegue à vontade!</li>
                </ol>
                
                <p>Precisa de ajuda? Entre em contato:</p>
                <p>WhatsApp: " . getSetting('support_phone') . "<br>
                Email: " . getSetting('support_email') . "</p>
                
                <div class='footer'>
                    <p>Este é um email automático. Por favor, não responda.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: WiFi Barato <" . getSetting('support_email') . ">" . "\r\n";
    
    $sent = mail($email, $subject, $message, $headers);
    
    if ($sent) {
        logEvent('email_sent', "Email enviado para: $email");
    } else {
        logEvent('email_error', "Erro ao enviar email para: $email");
    }
    
    return $sent;
}
?>