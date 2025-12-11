<?php
// app/controllers/WebhookController.php

require_once ROOT_PATH . '/app/controllers/BaseController.php';
require_once ROOT_PATH . '/app/models/Transaction.php';
require_once ROOT_PATH . '/app/models/Customer.php';
require_once ROOT_PATH . '/app/models/Plan.php';
require_once ROOT_PATH . '/app/models/HotspotUser.php';
require_once ROOT_PATH . '/app/services/MikrotikAPI.php';

class WebhookController extends BaseController {

    public function handleInfinityPay() {
        // Pega o corpo da requisição
        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);

        // Loga o payload bruto para depuração
        logEvent('webhook_infinitypay', $payload);

        // Valida o payload
        if (!$data || !isset($data['order_nsu']) || !isset($data['status'])) {
            http_response_code(400); // Bad Request
            echo "Payload inválido.";
            return;
        }

        $transactionId = $data['order_nsu'];
        $paymentStatus = $data['status'];

        // Responde ao webhook imediatamente para evitar timeouts
        header('Content-Type: application/json');
        echo json_encode(['status' => 'received']);

        // Processa o pagamento se o status for 'approved'
        if (strtolower($paymentStatus) === 'approved') {
            try {
                $transactionModel = new Transaction();
                $hotspotUserModel = new HotspotUser();

                // 1. Verifica se a transação já foi processada
                $existingTransaction = $transactionModel->findById($transactionId);
                if (!$existingTransaction || $existingTransaction['payment_status'] === 'approved') {
                    logEvent('webhook_info', "Transação {$transactionId} já processada ou não encontrada.");
                    return;
                }

                // 2. Atualiza o status da transação
                $transactionModel->updatePaymentDetails($transactionId, $data['transaction_id'] ?? 'N/A', 'approved', json_encode($data));

                // 3. Busca dados necessários para criar o usuário
                $planModel = new Plan();
                $plan = $planModel->findById($existingTransaction['plan_id']);

                $customerModel = new Customer();
                $customer = $customerModel->findById($existingTransaction['customer_id']);

                if (!$plan || !$customer) {
                    logEvent('webhook_error', "Plano ou cliente não encontrado para a transação {$transactionId}.");
                    return;
                }

                // 4. Provisiona o usuário no Mikrotik
                $mikrotik = new MikrotikAPI();
                $provisionResult = $mikrotik->provisionHotspotUser($plan['id'], $transactionId);

                if ($provisionResult['success']) {
                    // 5. Salva o usuário no banco de dados local
                    $username = $provisionResult['username'];
                    $password = $provisionResult['password'];
                    $expiresAt = date('Y-m-d H:i:s', time() + $plan['duration_seconds']);

                    $hotspotUserModel->create(
                        $transactionId,
                        $customer['id'],
                        $plan['id'],
                        $username,
                        $password,
                        $expiresAt
                    );

                    // 6. Envia o e-mail com as credenciais
                    sendHotspotCredentialsEmail(
                        $customer['email'],
                        $username,
                        $password,
                        $expiresAt,
                        $plan['name']
                    );

                    logEvent('webhook_success', "Usuário {$username} criado e e-mail enviado para a transação {$transactionId}.");
                } else {
                    logEvent('webhook_error', "Falha ao provisionar usuário no Mikrotik para a transação {$transactionId}: " . $provisionResult['message']);
                }

            } catch (Exception $e) {
                logEvent('webhook_exception', "Erro ao processar webhook para transação {$transactionId}: " . $e->getMessage());
            }
        }
    }
}
