<?php
// process_payment_infinity.php - Processa a requisição, salva IP/MAC, gera checkout
// O bypass no Mikrotik foi COMENTADO e IP/MAC não são mais obrigatórios para prosseguir.

require_once 'config.php';
require_once 'InfinityPay.php';
require_once 'MikrotikAPI.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Método não permitido');
}

$input = json_decode(file_get_contents('php://input'), true);

// 1. Validar e sanitizar dados
$required = ['plan_id', 'name', 'email', 'phone', 'cpf'];
foreach ($required as $field) {
    if (!isset($input[$field]) || trim($input[$field]) === '') {
        jsonResponse(false, "Campo obrigatório: $field");
    }
}

$planId = intval($input['plan_id']);
$name = sanitizeInput($input['name']);
$email = sanitizeInput($input['email']);
$phone = preg_replace('/[^0-9]/', '', (string)$input['phone']);
$cpf = preg_replace('/[^0-9]/', '', (string)$input['cpf']);

// --- MUDANÇA AQUI: Coleta de IP/MAC e Validação ---
// O IP e o MAC agora podem ser strings vazias se a coleta falhar.
$clientIp = !empty($input['client_ip']) ? sanitizeInput($input['client_ip']) : ''; // Default: '' (string vazia)
$clientMac = !empty($input['client_mac']) ? sanitizeInput($input['client_mac']) : ''; // Default: '' (string vazia)

// Valida IP e MAC (apenas se não estiverem vazios)
if (!empty($clientIp) && !filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    $clientIp = ''; // Limpa se for inválido
}
if (!empty($clientMac) && !preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $clientMac)) {
    $clientMac = ''; // Limpa se for inválido
}
// --------------------------------------------------


logEvent('payment_debug', "IP: $clientIp | MAC: $clientMac | Telefone: $phone");

// valida básico
if (!validateEmail($email) || !validateCPF($cpf)) {
    jsonResponse(false, 'Dados de cliente inválidos (Email/CPF)');
}

try {
    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();

    // buscar plano
    $stmt = $db->prepare("SELECT * FROM plans WHERE id = ? AND active = 1");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();
    if (!$plan) {
        $db->rollBack();
        jsonResponse(false, 'Plano não encontrado ou inativo');
    }

    // garantir price string (para gravar no amount)
    $planPrice = number_format(floatval($plan['price']), 2, '.', '');

    // criar ou buscar cliente
    $customerData = [
        'name'  => $name,
        'email' => $email,
        'phone' => $phone,
        'cpf'   => $cpf
    ];
    $customerId = createOrGetCustomer($db, $customerData);

    // criar transação pending
    $stmt = $db->prepare("
        INSERT INTO transactions (
            customer_id, plan_id, amount, payment_method, payment_status,
            client_ip, client_mac, created_at
        ) VALUES (?, ?, ?, ?, 'pending', ?, ?, NOW())
    ");
    $stmt->execute([
        $customerId,
        $planId,
        $planPrice,
        'infinitepay_checkout',
        $clientIp, // Grava IP (pode ser vazio)
        $clientMac // Grava MAC (pode ser vazio)
    ]);
    $transactionId = $db->lastInsertId();

    logEvent('transaction_created', "Transação ID $transactionId criada");

    // === Chama InfinityPay (gera link) ===
    $ip = new InfinityPay();
    try {
        $result = $ip->createCheckoutLink($plan, $customerData, $transactionId);
        logEvent('DEBUG_CHECKOUT_RESPONSE_FULL', $result);
    } catch (Exception $e) {
        logEvent('infinitepay_exception', "Exception ao criar checkout: " . $e->getMessage(), $transactionId);
        $db->rollBack();
        jsonResponse(false, 'Erro na criação do checkout: ' . $e->getMessage());
    }

    // se API retornou erro
    if (!isset($result['success']) || !$result['success']) {
        $db->rollBack();
        $errMsg = $result['message'] ?? 'Erro desconhecido';
        logEvent('payment_error', "Erro InfinitePay: $errMsg | TX: $transactionId", $transactionId);
        // opcional: gravar contexto do payload via logEvent('payment_error_payload_context', ...)
        jsonResponse(false, 'Erro ao criar pedido de pagamento: ' . $errMsg);
    }

    // sucesso -> pegamos URL e gravamos order id (se aplicável)
    $redirectUrl = $result['url'] ?? null;
    $infiniteOrderId = $result['order_id'] ?? null; // caso a lib retorne
    $stmt = $db->prepare("UPDATE transactions SET infinitypay_order_id = ? WHERE id = ?");
    $stmt->execute([ $infiniteOrderId ?? strval($transactionId), $transactionId ]);

    // commit para persistir a transação e order_id antes de responder
    $db->commit();

    // --- enviar resposta IMEDIATA ao cliente com redirect_url ---
    $response = [
        'success' => true,
        'message' => 'Redirecionando para o Checkout da InfinitePay',
        'data' => ['redirect_url' => $redirectUrl]
    ];
    echo json_encode($response);


    // === BLOCO DE BYPASS NO MIKROTIK COMENTADO PERMANENTEMENTE ===
    /*
    $stmt = $db->prepare("SELECT payload_response FROM transactions WHERE id = ?");
    $stmt->execute([$transactionId]);
    $payloadStatus = $stmt->fetchColumn();

    if ($payloadStatus !== 'Payload Criado') {
        // ... (código de rollback) ...
        exit;
    }

    sleep(2); // opcional
    $mt = new MikrotikAPI();
    $bypassResult = $mt->addClientBypass($transactionId);

    if (!$bypassResult['success']) {
        // ... (código de erro) ...
    } else {
        // ... (código de sucesso) ...
    }
    */
    // ---------------------- FIM DO BYPASS COMENTADO ----------------------

    exit;

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    logEvent('system_error', "Erro fatal: " . $e->getMessage());
    jsonResponse(false, 'Erro ao processar a requisição: ' . $e->getMessage());
}
?>