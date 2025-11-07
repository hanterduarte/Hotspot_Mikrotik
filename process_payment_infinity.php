<?php
// process_payment_infinity.php - Processa a requisição, salva IP/MAC e adiciona bypass

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
    if (empty($input[$field])) {
        jsonResponse(false, "Campo obrigatório: $field");
    }
}

$planId = intval($input['plan_id']);
$name = sanitizeInput($input['name']);

// CORREÇÃO AQUI: SANITIZAR O TELEFONE
$email = sanitizeInput($input['email']);
$phone = preg_replace('/[^0-9]/', '', $input['phone']); // <-- APLICANDO REGEX PARA MANTER SÓ NÚMEROS
$cpf = preg_replace('/[^0-9]/', '', $input['cpf']);

// CAPTURAR IP e MAC do formulário
$clientIp = !empty($input['client_ip']) ? sanitizeInput($input['client_ip']) : '0.0.0.0';
// ... (restante da captura de IP/MAC)

// Validar formato do IP (IPv4)
if (!filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    $clientIp = '0.0.0.0';
}

// Validar formato do MAC (XX:XX:XX:XX:XX:XX)
if (!preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $clientMac)) {
    $clientMac = '00:00:00:00:00:00';
}

logEvent('payment_debug', "IP recebido: $clientIp | MAC recebido: $clientMac");

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
    $customerId = createOrGetCustomer($db, $customerData); 
    
    // 4. Criar a transação inicial COM IP e MAC já preenchidos
    $stmt = $db->prepare("
        INSERT INTO transactions (
            customer_id, 
            plan_id, 
            amount, 
            payment_method, 
            payment_status,
            client_ip,
            client_mac
        )
        VALUES (?, ?, ?, ?, 'pending', ?, ?)
    ");
    $stmt->execute([
        $customerId, 
        $planId, 
        $plan['price'], 
        'infinitepay_checkout',
        $clientIp,
        $clientMac
    ]);
    $transactionId = $db->lastInsertId(); 
    
    logEvent('transaction_created', "Transação ID $transactionId criada. IP: $clientIp | MAC: $clientMac");
    
    // ======================================================================
    // 5. Gerar o link de checkout na InfinitePay (PRIMEIRO PASSO)
    // ======================================================================
    logEvent('DEBUG_CHECKOUT_START', "Iniciando API InfinitePay. Transaction ID: $transactionId");
    
    $ip = new InfinityPay();
    $result = $ip->createCheckoutLink($plan, $customerData, $transactionId);
    
    if ($result['success']) {
        $redirectUrl = $result['url'];

        // 6. Atualizar transação com a referência externa 
        $stmt = $db->prepare("UPDATE transactions SET infinitypay_order_id = ? WHERE id = ?");
        $stmt->execute([strval($transactionId), $transactionId]);
        
        // ======================================================================
        // MIKROTIK: 7. Adicionar Bypass SOMENTE APÓS O SUCESSO DO CHECKOUT
        // O MikrotikAPI agora usa a Address List para o bypass
        // ======================================================================
        $mt = new MikrotikAPI();
        
        // addClientBypass agora usa o ID da transação para buscar o IP no DB
        $bypassResult = $mt->addClientBypass($transactionId); 
        
        if (!$bypassResult['success']) {
            // Se falhar, faz rollback porque a transação na InfinitePay não poderá ser paga (sem internet)
            $db->rollBack(); 
            logEvent('mikrotik_error', "Falha ao adicionar IP Bypass (Address List). TX: $transactionId. Erro: " . $bypassResult['message']);
            jsonResponse(false, 'Erro ao preparar o acesso para pagamento: ' . $bypassResult['message']);
            return; 
        }

        $mikrotikBypassId = $bypassResult['bypass_id'];
        
        // Atualizar transação com o ID do bypass (aqui será o IP do cliente)
        $stmt = $db->prepare("
            UPDATE transactions 
            SET mikrotik_bypass_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$mikrotikBypassId, $transactionId]);
        
        logEvent('mikrotik_info', "Bypass (Address List) adicionado. ID: $mikrotikBypassId | Transaction: $transactionId");
        // ======================================================================

        $db->commit();
        
        logEvent('payment_created', "Checkout criado. Transaction: $transactionId | Bypass: $mikrotikBypassId"); 
        
        jsonResponse(true, 'Redirecionando para o Checkout da InfinitePay', [
            'redirect_url' => $redirectUrl
        ]);
        
    } else {
        $db->rollBack();
        logEvent('payment_error', "Erro InfinitePay: " . $result['message'], $transactionId);
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