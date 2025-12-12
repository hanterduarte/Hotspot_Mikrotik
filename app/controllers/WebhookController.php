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

        // Loga o payload bruto para depuração
        logEvent('webhook_infinitypay', $payload);

        // Responde ao webhook imediatamente para evitar timeouts
        header('Content-Type: application/json');
        echo json_encode(['status' => 'received']);

        // Processa o payload
        $this->processWebhookPayload($payload);
    }

    /**
     * Lógica de processamento do payload do webhook.
     * @param string $payload O payload JSON do webhook.
     */
    public function processWebhookPayload($payload) {
        $data = json_decode($payload, true);

        // Valida o payload - agora verifica apenas o campo essencial 'order_nsu'
        if (!$data || !isset($data['order_nsu'])) {
            logEvent('webhook_error', 'Payload inválido ou faltando o campo essencial order_nsu.');
            return;
        }

        $transactionId = $data['order_nsu'];

        // Como o webhook da InfinityPay só é enviado em caso de sucesso,
        // podemos assumir que o status é 'PAID' e prosseguir.
        try {
            $transactionModel = new Transaction();
            $hotspotUserModel = new HotspotUser();

                $existingTransaction = $transactionModel->findById($transactionId);
                if (!$existingTransaction || $existingTransaction['payment_status'] === 'approved') {
                    logEvent('webhook_info', "Transação {$transactionId} já processada ou não encontrada.");
                    return;
                }

                // Usa a chave correta do webhook ('transaction_nsu') ou a do teste ('transaction_id')
                $gatewayTransactionId = $data['transaction_nsu'] ?? $data['transaction_id'] ?? 'N/A';
                $transactionModel->updatePaymentDetails($transactionId, $gatewayTransactionId, 'approved', json_encode($data));

                $planModel = new Plan();
                $plan = $planModel->findById($existingTransaction['plan_id']);

                $customerModel = new Customer();
                $customer = $customerModel->findById($existingTransaction['customer_id']);

                if (!$plan || !$customer) {
                    logEvent('webhook_error', "Plano ou cliente não encontrado para a transação {$transactionId}.");
                    return;
                }

                // Pega os dados do cliente (IP e MAC) da transação para provisionar no Mikrotik
                $clientIp = $existingTransaction['client_ip'] ?? '';
                $clientMac = $existingTransaction['client_mac'] ?? '';

                $mikrotik = new MikrotikAPI();
                $provisionResult = $mikrotik->provisionHotspotUser($plan['id'], $transactionId, $clientIp, $clientMac);

                if ($provisionResult['success']) {
                    $username = $provisionResult['username'];
                    $password = $provisionResult['password'];
                    $expiresAt = date('Y-m-d H:i:s', time() + $plan['duration_seconds']);

                    $hotspotUserModel->create($transactionId, $customer['id'], $plan['id'], $username, $password, $expiresAt);

                    sendHotspotCredentialsEmail($customer['email'], $username, $password, $expiresAt, $plan['name']);

                    logEvent('webhook_success', "Usuário {$username} criado e e-mail enviado para a transação {$transactionId}.");
                } else {
                    logEvent('webhook_error', "Falha ao provisionar usuário no Mikrotik para a transação {$transactionId}: " . $provisionResult['message']);
                }
            } catch (Exception $e) {
                logEvent('webhook_exception', "Erro ao processar webhook para transação {$transactionId}: " . $e->getMessage());
            }
    }
}
