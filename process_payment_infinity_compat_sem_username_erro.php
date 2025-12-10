<?php
// process_payment_infinity.php – Compatível com schema antigo (customers + transactions)
// Mantém dados do cliente em customers e grava apenas campos de transação + Mikrotik essenciais em transactions.

require_once 'config.php';
require_once 'InfinityPay.php';
require_once 'MikrotikAPI.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Método não permitido');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(false, 'Payload inválido (JSON)');
    }

    // 1) Validar campos obrigatórios do cliente
    $required = ['plan_id', 'name', 'email', 'phone', 'cpf'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || trim((string)$input[$field]) === '') {
            jsonResponse(false, "Campo obrigatório: $field");
        }
    }

    // Sanitização
    $planId = intval($input['plan_id']);
    $name   = sanitizeInput($input['name']);
    $email  = sanitizeInput($input['email']);
    $phone  = preg_replace('/[^0-9]/', '', (string)$input['phone']);
    $cpf    = preg_replace('/[^0-9]/', '', (string)$input['cpf']);

    // IP / MAC (opcional)
    $clientIp  = !empty($input['client_ip'])  ? sanitizeInput($input['client_ip'])  : '';
    $clientMac = !empty($input['client_mac']) ? sanitizeInput($input['client_mac']) : '';

    if (!empty($clientIp) && !filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $clientIp = '';
    }
    if (!empty($clientMac) && !preg_match('/^([0-9A-Fa-f]{2}[:\-]){5}([0-9A-Fa-f]{2})$/', $clientMac)) {
        $clientMac = '';
    }

    // Variáveis do Mikrotik (underscore conforme combinado)
    $mikrotikLinkLoginOnly = sanitizeInput($input['link_login_only'] ?? '');
    $mikrotikLinkOrig      = sanitizeInput($input['link_orig'] ?? '');
    $mikrotikChapId        = sanitizeInput($input['chap_id'] ?? '');
    $mikrotikChapChallenge = sanitizeInput($input['chap_challenge'] ?? '');
    // Removidos do INSERT: $mikrotikUsername, $mikrotikError

    // Validações básicas de email/CPF se existirem no config.php
    if (function_exists('validateEmail') && !validateEmail($email)) {
        jsonResponse(false, 'Email inválido');
    }
    if (function_exists('validateCPF') && !validateCPF($cpf)) {
        jsonResponse(false, 'CPF inválido');
    }

    // 2) Buscar dados do plano
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT id, name, price FROM plans WHERE id = ? AND active = 1");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();
    if (!$plan) {
        jsonResponse(false, 'Plano não encontrado ou inativo.');
    }

    $amount   = $plan['price'];
    $planName = $plan['name'];

    // 3) Transação DB (com customers)
    $db->beginTransaction();

    // 3.1) Criar/obter cliente na tabela customers
    if (!function_exists('createOrGetCustomer')) {
        // Fallback simples caso a função não exista
        $stmtC = $db->prepare("SELECT id FROM customers WHERE cpf = ? LIMIT 1");
        $stmtC->execute([$cpf]);
        $rowC = $stmtC->fetch();
        if ($rowC) {
            $customerId = intval($rowC['id']);
            $stmtU = $db->prepare("UPDATE customers SET name = ?, email = ?, phone = ? WHERE id = ?");
            $stmtU->execute([$name, $email, $phone, $customerId]);
        } else {
            $stmtI = $db->prepare("INSERT INTO customers (name, email, phone, cpf, created_at) VALUES (?,?,?,?, NOW())");
            $stmtI->execute([$name, $email, $phone, $cpf]);
            $customerId = intval($db->lastInsertId());
        }
    } else {
        $customerData = [
            'name'  => $name,
            'email' => $email,
            'phone' => $phone,
            'cpf'   => $cpf,
        ];
        $customerId = createOrGetCustomer($db, $customerData);
    }

    // 3.2) Criar transação (SEM name/email/phone/cpf e SEM mikrotik_username/mikrotik_error)
    $stmt = $db->prepare(" 
        INSERT INTO transactions (
            customer_id, plan_id, amount, payment_method, payment_status,
            client_ip, client_mac,
            mikrotik_link_login_only, mikrotik_link_orig, mikrotik_chap_id, mikrotik_chap_challenge,
            created_at,
            infinitypay_order_id
        ) VALUES (
            ?, ?, ?, ?, 'pending',
            ?, ?,
            ?, ?, ?, ?,
            NOW(),
            NULL
        )
    ");

    $stmt->execute([
        $customerId,
        $planId,
        number_format((float)$amount, 2, '.', ''),
        'infinitepay_checkout',
        $clientIp,
        $clientMac,
        $mikrotikLinkLoginOnly,
        $mikrotikLinkOrig,
        $mikrotikChapId,
        $mikrotikChapChallenge,
    ]);

    $transactionId = $db->lastInsertId();

    // 4) Criar Checkout na InfinitePay
    $ip = new InfinityPay();

    $successUrl = BASE_URL . "payment_success.php?external_reference={$transactionId}";

    $customerData = [
        'name'  => $name,
        'email' => $email,
        'phone' => $phone,
        'cpf'   => $cpf,
    ];

    $items = [
        [
            'name'     => $planName,
            'amount'   => (int)($amount * 100), // em centavos
            'quantity' => 1,
            'sku'      => $planId,
        ]
    ];

    $checkoutResult = $ip->createCheckout($transactionId, $successUrl, $customerData, $items);

    if (!$checkoutResult['success']) {
        $db->rollBack();
        logEvent('infinitypay_error', 'Falha ao criar link de checkout: ' . ($checkoutResult['message'] ?? 'sem mensagem') . "\nTX: $transactionId");
        jsonResponse(false, 'Falha ao criar link de checkout: ' . ($checkoutResult['message'] ?? ''));
    }

    $redirectUrl     = $checkoutResult['data']['redirect_url'] ?? null;
    $infiniteOrderId = $checkoutResult['data']['order_id'] ?? null;

    // 5) Persistir order_id antes de responder
    $stmt = $db->prepare("UPDATE transactions SET infinitypay_order_id = ? WHERE id = ?");
    $stmt->execute([ $infiniteOrderId ?? strval($transactionId), $transactionId ]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Redirecionando para o Checkout da InfinitePay',
        'data'    => ['redirect_url' => $redirectUrl]
    ]);
    exit;

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    logEvent('payment_error', "Erro ao processar pagamento: {$e->getMessage()}\nTX: " . ($transactionId ?? 'N/A'));
    jsonResponse(false, 'Erro interno ao processar pagamento. Código: ' . $e->getMessage());
}
