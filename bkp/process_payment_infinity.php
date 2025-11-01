<?php
// process_payment_infinity.php - Processa a requisição e gera o link de checkout

// ==========================================================
// MANTENHA ESTAS LINHAS ATIVAS PARA VER O ERRO FATAL (SE HOUVER)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ==========================================================

require_once 'config.php';
require_once 'InfinityPay.php'; 

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

$transactionId = 0; 

try {
    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();

    // 2. Buscar plano
    $stmt = $db->prepare("SELECT * FROM plans WHERE id = ?");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();

    if (!$plan) {
        throw new Exception('Plano não encontrado.');
    }

    // 3. Criar ou obter o cliente
    // ATENÇÃO: Se a função 'createOrGetCustomer' não estiver no seu config.php, o erro será aqui.
    $customerData = createOrGetCustomer($name, $email, $phone, $cpf);
    $customerId = $customerData['id'];

    // 4. Iniciar transação no DB
    $stmt = $db->prepare("INSERT INTO transactions (customer_id, plan_id, amount, payment_method, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->execute([$customerId, $planId, $plan['price'], 'infinitepay_checkout']);
    $transactionId = $db->lastInsertId(); 
    
    // ==========================================================
    // CORREÇÃO CRÍTICA DO HANDLE
    // ==========================================================
    $ipHandle = getSetting('infinitepay_handle');
    if (empty($ipHandle)) {
        // Esta exceção é um forte candidato, pois acontecia antes.
        logEvent('ip_handle_missing_fatal', 'InfiniteTag faltando no DB antes da criação da classe.', $transactionId);
        throw new Exception('Erro de Configuração: InfiniteTag faltando (Verifique a tabela settings).');
    }
    
    // 5. Gerar o link de checkout na InfinitePay
    $ip = new InfinityPay($ipHandle); // Requer a versão correta do InfinityPay.php
    
    $result = $ip->createCheckoutLink($plan, $customerData, $transactionId);
    
    if ($result['success']) {
        $redirectUrl = $result['url'];

        // 6. Atualizar transação com a referência externa e invoice_slug
        $stmt = $db->prepare("UPDATE transactions SET infinitypay_order_id = ?, infinitypay_invoice_slug = ? WHERE id = ?");
        $stmt->execute([
            strval($transactionId), 
            $result['invoice_slug'] ?? null,
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
    $finalTransactionId = isset($transactionId) ? $transactionId : 0; 
    
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // LOG NO DB
    logEvent('system_error', 'Exceção em process_payment_infinity.php: ' . $e->getMessage(), $finalTransactionId);
    
    // ==========================================================
    // MUDANÇA CRÍTICA: RETORNA A MENSAGEM DA EXCEÇÃO (para debug)
    jsonResponse(false, 'ERRO INTERNO: ' . $e->getMessage()); 
    // ==========================================================
}