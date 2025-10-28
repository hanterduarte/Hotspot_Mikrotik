<?php
require_once 'config.php';

header('Content-Type: application/json');

$transactionId = $_GET['transaction_id'] ?? null;

if (!$transactionId) {
    echo json_encode(['success' => false, 'message' => 'ID da transação não fornecido.']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT payment_status FROM transactions WHERE id = ?");
    $stmt->execute([$transactionId]);
    $transaction = $stmt->fetch();

    if ($transaction) {
        echo json_encode([
            'success' => true,
            'status' => $transaction['payment_status']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Transação não encontrada.']);
    }

} catch (Exception $e) {
    logEvent('error', 'Erro na API de verificação de status: ' . $e->getMessage(), $transactionId);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
}
?>