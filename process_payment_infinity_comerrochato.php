<?php
// process_payment_infinity.php – Compatível com schema antigo (customers + transactions)
// Mantém dados do cliente em customers e grava apenas campos de transação + Mikrotik essenciais em transactions.

ini_set('display_errors',1);
ini_set('display_startup_erros',1);
error_reporting(E_ALL);

if (!defined('APP_DEBUG')) {
    define('APP_DEBUG',true);
}

require_once 'config.php';
require_once 'InfinityPay.php';
require_once 'MikrotikAPI.php';

header('Content-Type: application/json');

// ===== FUNÇÃO AUXILIAR inputGet (estava faltando!) =====
if (!function_exists('inputGet')) {
    function inputGet($array, $key, $aliases = []) {
        if (isset($array[$key])) {
            return $array[$key];
        }
        foreach ($aliases as $alias) {
            if (isset($array[$alias])) {
                return $array[$alias];
            }
        }
        return '';
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Método não permitido');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(false, 'Payload inválido (JSON)');
    }

    // ===== DEBUG 1: ATIVADO =====
    file_put_contents('debug_payment.log', "=== NOVA REQUISIÇÃO ===\n" . date('Y-m-d H:i:s') . "\n[DEBUG 1] JSON recebido:\n" . print_r($input, true) . "\n\n", FILE_APPEND);

    // 1) Validar campos obrigatórios do cliente
    $required = ['plan_id', 'name', 'email', 'phone', 'cpf'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || trim((string)$input[$field]) === '') {
            file_put_contents('debug_payment.log', "[ERRO] Campo obrigatório faltando: $field\n\n", FILE_APPEND);
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
    $clientIp  = $input['client_ip'] ?? '';
    $clientMac = $input['client_mac'] ?? '';

    // Sanitização e validação do IP/MAC
    $clientIp  = sanitizeInput($clientIp);
    $clientMac = sanitizeInput($clientMac);

    if (!empty($clientIp) && !filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $clientIp = '';
    }
    if (!empty($clientMac) && !preg_match('/^([0-9A-Fa-f]{2}[:\-]){5}([0-9A-Fa-f]{2})$/', $clientMac)) {
        $clientMac = '';
    }

    // Mikrotik: aceita underscore e hífen (aliases)
    $mikrotikLinkLoginOnly = inputGet($input, 'link_login_only', ['link-login-only']);
    $mikrotikLinkOrig      = inputGet($input, 'link_orig', ['link-orig']);
    
    // CHAP ID e CHAP CHALLENGE: Lê o Base64 do input e DECODIFICA
    $mikrotikChapIdInput        = $input['chap_id'] ?? ''; 
    $mikrotikChapChallengeInput = $input['chap_challenge'] ?? ''; 

    // ===== DEBUG 2: ATIVADO =====
    file_put_contents('debug_payment.log', "[DEBUG 2] ANTES decodificar Base64:\n" . 
        "chap_id input: '$mikrotikChapIdInput'\n" .
        "chap_challenge input: '$mikrotikChapChallengeInput'\n" .
        "link_login_only: '$mikrotikLinkLoginOnly'\n" .
        "link_orig: '$mikrotikLinkOrig'\n\n", FILE_APPEND);

    // Aplica a decodificação do Base64 ANTES de salvar no DB
    $mikrotikChapId        = base64_decode($mikrotikChapIdInput);
    $mikrotikChapChallenge = base64_decode($mikrotikChapChallengeInput);

    // ===== DEBUG 2.1: ATIVADO =====
    file_put_contents('debug_payment.log', "[DEBUG 2.1] DEPOIS decodificar Base64:\n" . 
        "chap_id decoded length: " . strlen($mikrotikChapId) . " bytes\n" .
        "chap_id hex: " . bin2hex($mikrotikChapId) . "\n" .
        "chap_challenge decoded length: " . strlen($mikrotikChapChallenge) . " bytes\n" .
        "chap_challenge hex: " . bin2hex($mikrotikChapChallenge) . "\n\n", FILE_APPEND);

    // Validações básicas de email/CPF se existirem no config.php
    if (function_exists('validateEmail') && !validateEmail($email)) {
        file_put_contents('debug_payment.log', "[ERRO] Email inválido: $email\n\n", FILE_APPEND);
        jsonResponse(false, 'Email inválido');
    }
    if (function_exists('validateCPF') && !validateCPF($cpf)) {
        file_put_contents('debug_payment.log', "[ERRO] CPF inválido: $cpf\n\n", FILE_APPEND);
        jsonResponse(false, 'CPF inválido');
    }

    // 2) Buscar dados do plano
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT id, name, price FROM plans WHERE id = ? AND active = 1");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();
    if (!$plan) {
        file_put_contents('debug_payment.log', "[ERRO] Plano não encontrado: ID=$planId\n\n", FILE_APPEND);
        jsonResponse(false, 'Plano não encontrado ou inativo.');
    }

    $amount   = $plan['price'];
    $planName = $plan['name'];

    file_put_contents('debug_payment.log', "[DEBUG] Plano encontrado: $planName - R$ $amount\n\n", FILE_APPEND);

    // 3) Transação DB (com customers)
    $db->beginTransaction();

    // 3.1) Criar/obter cliente na tabela customers
    if (function_exists('createOrGetCustomer')) {
        $customerData = [
            'name'  => $name,
            'email' => $email,
            'phone' => $phone,
            'cpf'   => $cpf,
        ];
        $customerId = createOrGetCustomer($db, $customerData);
    } else {
        // Fallback por CPF
        $stmtC = $db->prepare("SELECT id FROM customers WHERE cpf = ? LIMIT 1");
        $stmtC->execute([$cpf]);
        $rowC = $stmtC->fetch();
        if ($rowC) {
            $customerId = (int)$rowC['id'];
            $stmtU = $db->prepare("UPDATE customers SET name = ?, email = ?, phone = ? WHERE id = ?");
            $stmtU->execute([$name, $email, $phone, $customerId]);
        } else {
            $stmtI = $db->prepare("INSERT INTO customers (name, email, phone, cpf, created_at) VALUES (?,?,?,?, NOW())");
            $stmtI->execute([$name, $email, $phone, $cpf]);
            $customerId = (int)$db->lastInsertId();
        }
    }

    file_put_contents('debug_payment.log', "[DEBUG 3] Cliente ID: $customerId\n\n", FILE_APPEND);

    // ===== DEBUG 3.1: Parâmetros ANTES do INSERT =====
    $paramsDebug = [
        'customer_id' => $customerId,
        'plan_id' => $planId,
        'amount' => number_format((float)$amount, 2, '.', ''),
        'payment_method' => 'infinitepay_checkout',
        'client_ip' => $clientIp,
        'client_mac' => $clientMac,
        'mikrotik_link_login_only' => $mikrotikLinkLoginOnly,
        'mikrotik_link_orig' => $mikrotikLinkOrig,
        'mikrotik_chap_id length' => strlen($mikrotikChapId),
        'mikrotik_chap_id hex' => bin2hex($mikrotikChapId),
        'mikrotik_chap_challenge length' => strlen($mikrotikChapChallenge),
        'mikrotik_chap_challenge hex' => bin2hex($mikrotikChapChallenge),
    ];

    file_put_contents('debug_payment.log', "[DEBUG 3.1] Parâmetros para INSERT:\n" . 
        print_r($paramsDebug, true) . "\n\n", FILE_APPEND);

    // 3.2) Criar transação 
    try {
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

        $executeResult = $stmt->execute([
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

        // ===== DEBUG 4: Resultado do INSERT =====
        if (!$executeResult) {
            $errorInfo = $stmt->errorInfo();
            file_put_contents('debug_payment.log', "[DEBUG 4 ERRO] Falha ao executar INSERT:\n" . 
                "SQLSTATE: " . $errorInfo[0] . "\n" .
                "Driver Error Code: " . $errorInfo[1] . "\n" .
                "Error Message: " . $errorInfo[2] . "\n\n", FILE_APPEND);
            throw new Exception("Erro SQL ao inserir transação: " . $errorInfo[2]);
        }

        file_put_contents('debug_payment.log', "[DEBUG 4 OK] INSERT transactions executado com sucesso!\n\n", FILE_APPEND);

    } catch (PDOException $e) {
        file_put_contents('debug_payment.log', "[DEBUG 4 EXCEPTION PDO] " . $e->getMessage() . "\n\n", FILE_APPEND);
        throw $e;
    }

    $transactionId = $db->lastInsertId();

    file_put_contents('debug_payment.log', "[DEBUG] Transaction ID criada: $transactionId\n\n", FILE_APPEND);

    // 4) Criar Checkout na InfinitePay
    $ip = new InfinityPay();

    // Garantir que BASE_URL termine com barra
    $baseUrl = rtrim(BASE_URL, '/') . '/';
    $successUrl = $baseUrl . "payment_success.php?external_reference={$transactionId}";

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

    // ===== DEBUG 5: ATIVADO =====
    file_put_contents('debug_payment.log', "[DEBUG 5] Antes de createCheckout:\n" . 
        "transaction_id: $transactionId\n" .
        "success_url: $successUrl\n" .
        "customer_data: " . print_r($customerData, true) . "\n" .
        "items: " . print_r($items, true) . "\n\n", FILE_APPEND);

    $checkoutResult = $ip->createCheckout($transactionId, $successUrl, $customerData, $items);

    // ===== DEBUG 6: ATIVADO =====
    file_put_contents('debug_payment.log', "[DEBUG 6] Resultado createCheckout:\n" . 
        print_r($checkoutResult, true) . "\n\n", FILE_APPEND);

    if (!$checkoutResult['success']) {
        $db->rollBack();
        
        $apiMessage = $checkoutResult['message'] ?? 'Resposta da API vazia ou inválida';
        
        file_put_contents('debug_payment.log', "[ERRO] Falha no checkout InfinitePay: $apiMessage\n\n", FILE_APPEND);
        
        // Registra o erro no log
        logEvent('infinitypay_error', 'Falha ao criar link de checkout: ' . $apiMessage . "\nTX: $transactionId");
        
        // Retorna a mensagem DETALHADA para o front-end
        jsonResponse(false, 'Falha no Checkout InfinitePay. Detalhe: ' . $apiMessage);
    }

    $redirectUrl     = $checkoutResult['data']['redirect_url'] ?? null;
    $infiniteOrderId = $checkoutResult['data']['order_id'] ?? null;

    // 5) Persistir order_id antes de responder
    $stmt = $db->prepare("UPDATE transactions SET infinitypay_order_id = ? WHERE id = ?");
    $stmt->execute([ $infiniteOrderId ?? strval($transactionId), $transactionId ]);

    $db->commit();

    file_put_contents('debug_payment.log', "[DEBUG 7 SUCESSO] Transação commitada. Redirecionando para: $redirectUrl\n\n", FILE_APPEND);

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
    
    // ===== DEBUG FINAL: ATIVADO =====
    file_put_contents('debug_payment.log', "[DEBUG ERRO EXCEPTION] " . date('Y-m-d H:i:s') . "\n" . 
        "Mensagem: " . $e->getMessage() . "\n" . 
        "Arquivo: " . $e->getFile() . " (linha " . $e->getLine() . ")\n" .
        "Stack Trace:\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);
    
    logEvent('payment_error', "Erro ao processar pagamento: {$e->getMessage()}\nTX: " . ($transactionId ?? 'N/A'));
    jsonResponse(false, 'Erro interno ao processar pagamento. Código: ' . $e->getMessage());
}