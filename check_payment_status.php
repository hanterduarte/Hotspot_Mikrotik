<?php
require_once 'config.php';

header('Content-Type: application/json');

$paymentId = $_GET['payment_id'] ?? null;

if (!$paymentId) {
    jsonResponse(false, 'Payment ID não fornecido');
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Buscar transação
    $stmt = $db->prepare("
        SELECT t.*, hu.username, hu.password as user_password
        FROM transactions t
        LEFT JOIN hotspot_users hu ON t.id = hu.transaction_id
        WHERE t.payment_id = ?
    ");
    $stmt->execute([$paymentId]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        jsonResponse(false, 'Transação não encontrada');
    }
    
    $response = [
        'success' => true,
        'status' => $transaction['payment_status'],
        'transaction_id' => $transaction['id']
    ];
    
    // Se aprovado e tem credenciais, retornar
    if ($transaction['payment_status'] === 'approved' && $transaction['username']) {
        $response['credentials'] = [
            'username' => $transaction['username'],
            'password' => $transaction['user_password']
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    jsonResponse(false, 'Erro ao verificar status: ' . $e->getMessage());
}
?>