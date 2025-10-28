<?php
require_once 'config.php';
require_once 'MercadoPago.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Método não permitido');
}

$input = json_decode(file_get_contents('php://input'), true);

// Validar dados
$required = ['plan_id', 'name', 'email', 'phone', 'cpf', 'payment_method'];
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
$paymentMethod = sanitizeInput($input['payment_method']);

// Validar email
if (!validateEmail($email)) {
    jsonResponse(false, 'Email inválido');
}

// Validar CPF
if (!validateCPF($cpf)) {
    jsonResponse(false, 'CPF inválido');
}

try {
    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();

    // Buscar plano
    $stmt = $db->prepare("SELECT * FROM plans WHERE id = ? AND active = 1");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();

    if (!$plan) {
        $db->rollBack();
        jsonResponse(false, 'Plano não encontrado');
    }

    // Verificar se cliente já existe
    $stmt = $db->prepare("SELECT id FROM customers WHERE email = ?");
    $stmt->execute([$email]);
    $customer = $stmt->fetch();

    if ($customer) {
        $customerId = $customer['id'];

        // Atualizar dados do cliente
        $stmt = $db->prepare("UPDATE customers SET name = ?, phone = ?, cpf = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $cpf, $customerId]);
    } else {
        // Criar novo cliente
        $stmt = $db->prepare("INSERT INTO customers (name, email, phone, cpf) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $phone, $cpf]);
        $customerId = $db->lastInsertId();
    }

    // Criar transação
    $stmt = $db->prepare("
        INSERT INTO transactions (customer_id, plan_id, payment_method, payment_status, amount, gateway)
        VALUES (?, ?, ?, 'pending', ?, 'mercadopago')
    ");
    $stmt->execute([$customerId, $planId, $paymentMethod, $plan['price']]);
    $transactionId = $db->lastInsertId();

    $db->commit();

    // Processar pagamento com Mercado Pago
    $mp = new MercadoPago();

    $customerData = [
        'transaction_id' => $transactionId,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'cpf' => $cpf
    ];

    $planData = [
        'name' => $plan['name'],
        'description' => $plan['description'],
        'price' => $plan['price']
    ];

    if ($paymentMethod === 'pix') {
        // Criar pagamento PIX
        $result = $mp->createPixPayment($planData, $customerData);

        if ($result['success']) {
            // Atualizar transação com ID do pagamento
            $stmt = $db->prepare("UPDATE transactions SET payment_id = ?, gateway_response = ? WHERE id = ?");
            $stmt->execute([
                $result['payment_id'],
                json_encode($result),
                $transactionId
            ]);

            logEvent('payment_created', "Pagamento PIX criado: " . $result['payment_id'], $transactionId);

            jsonResponse(true, 'Pagamento PIX criado com sucesso', [
                'payment_id' => $result['payment_id'],
                'qr_code' => $result['qr_code'],
                'qr_code_base64' => $result['qr_code_base64']
            ]);
        } else {
            logEvent('payment_error', "Erro ao criar PIX: " . json_encode($result), $transactionId);
            jsonResponse(false, 'Erro ao criar pagamento PIX');
        }

    } else {
        // Criar preferência para checkout (cartão/boleto)
        $result = $mp->createPreference($planData, $customerData);

        if ($result['success']) {
            // Atualizar transação
            $stmt = $db->prepare("UPDATE transactions SET payment_id = ?, gateway_response = ? WHERE id = ?");
            $stmt->execute([
                $result['preference_id'],
                json_encode($result),
                $transactionId
            ]);

            logEvent('payment_created', "Preferência criada: " . $result['preference_id'], $transactionId);

            jsonResponse(true, 'Redirecionando para pagamento', [
                'redirect_url' => $result['init_point']
            ]);
        } else {
            logEvent('payment_error', "Erro ao criar preferência: " . json_encode($result), $transactionId);
            jsonResponse(false, 'Erro ao criar preferência de pagamento');
        }
    }

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    logEvent('payment_exception', $e->getMessage());
    jsonResponse(false, 'Erro ao processar pagamento: ' . $e->getMessage());
}
?>