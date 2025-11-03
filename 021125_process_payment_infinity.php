<?php
// process_payment_infinity.php - Processa a requisição e gera o link de checkout

require_once 'config.php';
require_once 'InfinityPay.php'; 
// OBS: A função createOrGetCustomer() deve estar disponível via config.php

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Método não permitido');
}

$input = json_decode(file_get_contents('php://input'), true);

// 1. Validar e sanitizar dados
$required = ['plan_id', 'name', 'email', 'phone', 'cpf']; 
foreach ($required as $field) {
    if (empty($input[$field])) {
        jsonResponse(false, "Campo obrigatório: $field");
    }
}

$planId = intval($input['plan_id']);
$name = sanitizeInput($input['name']);
$email = sanitizeInput($input['email']);
$phone = sanitizeInput($input['phone']);
$cpf = preg_replace('/[^0-9]/', '', $input['cpf']);

if (!validateEmail($email) || !validateCPF($cpf)) {
    jsonResponse(false, 'Dados de cliente inválidos (Email/CPF)');
}

try {
    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();

    // 2. Buscar plano
    $stmt = $db->prepare("SELECT * FROM plans WHERE id = ? AND active = 1");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();

    if (!$plan) {
        $db->rollBack();
        jsonResponse(false, 'Plano não encontrado ou inativo');
    }
    
    // 3. Criar ou buscar cliente
    $customerData = ['name' => $name, 'email' => $email, 'phone' => $phone, 'cpf' => $cpf];
    // **Assumindo que createOrGetCustomer existe e retorna o customerId**
    $customerId = createOrGetCustomer($db, $customerData); 
    
    // 4. Criar a transação inicial (status: pending)
    $stmt = $db->prepare("
        INSERT INTO transactions (customer_id, plan_id, amount, payment_method, payment_status)
        VALUES (?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$customerId, $planId, $plan['price'], 'infinitepay_checkout']);
    $transactionId = $db->lastInsertId(); 
    
    // 5. Gerar o link de checkout na InfinitePay
    $ip = new InfinityPay();
    
    $result = $ip->createCheckoutLink($plan, $customerData, $transactionId);
    
    if ($result['success']) {
        $redirectUrl = $result['url'];

        // 6. Atualizar transação com a referência externa (order_nsu)
        $stmt = $db->prepare("UPDATE transactions SET infinitypay_order_id = ? WHERE id = ?");
        $stmt->execute([
            strval($transactionId), 
            $transactionId
        ]);
        
        $db->commit();
        logEvent('payment_created', "Link de Checkout InfinitePay criado. Transaction ID: $transactionId");
        
        // 7. Retornar a URL de redirecionamento para o JavaScript
        jsonResponse(true, 'Redirecionando para o Checkout da InfinitePay', [
            'redirect_url' => $redirectUrl
        ]);
        
    } else {
        $db->rollBack();
        logEvent('payment_error', "Erro ao criar link de checkout InfinitePay: " . $result['message'], $transactionId);
        jsonResponse(false, 'Erro ao criar pedido de pagamento: ' . $result['message']);
    }
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    logEvent('payment_exception', $e->getMessage());
    jsonResponse(false, 'Erro ao processar a requisição: ' . $e->getMessage());
}

?>