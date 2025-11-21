<?php
// check_payment_status.php - Endpoint para o JavaScript verificar o status do pagamento e credenciais

require_once 'config.php';

header('Content-Type: application/json');

$externalReference = $_GET['payment_id'] ?? null; // Usa o 'payment_id' do JS, que é o ID da transação

if (!$externalReference) {
    jsonResponse(false, 'Referência da transação faltando.');
}

try {
    $db = Database::getInstance()->getConnection();
    
    // 1. Busca a transação e as credenciais (se existirem)
    $stmt = $db->prepare("
        SELECT 
            t.payment_status, 
            hu.username, 
            hu.password as user_password,
            hu.expires_at                 /* RE-INSERIDO */
        FROM transactions t
        LEFT JOIN hotspot_users hu ON t.id = hu.transaction_id
        WHERE t.id = ?
    ");
    $stmt->execute([$externalReference]);
    $result = $stmt->fetch();

    if (!$result) {
        jsonResponse(false, 'Transação não encontrada.');
    }
    
    $status = strtolower($result['payment_status']);
    
    // 2. Verifica se o pagamento foi aprovado E se as credenciais já foram criadas
    // [CORREÇÃO AQUI]: Adicionando 'success' como status de aprovação válido
    $isApproved = ($status === 'approved' || $status === 'paid' || $status === 'success'); 
    
    if ($isApproved && !empty($result['username'])) {
        
        // Pagamento aprovado e usuário criado!
        jsonResponse(true, 'Pagamento Aprovado. Credenciais prontas.', [
            'status' => 'approved', // Retorna 'approved' para o JS
            'credentials' => [
                'username' => $result['username'],
                'password' => $result['user_password'],
                'expires_at' => $result['expires_at'] ?? null, 
            ]
        ]);
        
    } elseif ($isApproved) {
        
        // Pagamento aprovado, mas o Webhook ainda está trabalhando (ou falhou na criação do usuário)
        jsonResponse(true, 'Pagamento Aprovado. Aguardando credenciais.', [
            'status' => 'approved',
            'credentials' => null // Indica ao JS que ainda não pode recarregar
        ]);
        
    } else {
        // Status ainda pendente, cancelado, etc.
        jsonResponse(true, 'Aguardando confirmação de pagamento.', [
            'status' => $status,
            'credentials' => null
        ]);
    }

} catch (Exception $e) {
    logEvent('check_status_error', $e->getMessage(), $externalReference);
    http_response_code(500);
    jsonResponse(false, 'Erro interno ao verificar o status: ' . $e->getMessage());
}
?>