<?php
// InfinityPay.php - Classe para integração com InfinitePay Checkout

class InfinityPay {
    private $handle;
    private $apiUrl = 'https://api.infinitepay.io/invoices/public/checkout/links';

    public function __construct() {
        // Assume que a função getSetting() está em config.php e lê do DB
        $this->handle = getSetting('infinitepay_handle');

        if (empty($this->handle)) {
            logEvent('infinitepay_error', 'InfiniteTag (handle) não configurada.');
            throw new Exception("InfiniteTag (handle) faltando. Configure a 'infinitepay_handle' na tabela settings.");
        }
    }

    private function makeRequest($url, $data) {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            logEvent('infinitepay_error', "Erro CURL: $error");
            return ['success' => false, 'message' => "Erro de conexão: $error"];
        }
        
        curl_close($ch);
        
        $decoded = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300 && isset($decoded['url'])) {
            return ['success' => true, 'url' => $decoded['url'], 'http_code' => $httpCode];
        } else {
            $errorMessage = $decoded['message'] ?? $decoded['error'] ?? 'Erro desconhecido na API da InfinitePay.';
            logEvent('infinitepay_error', "Erro ao gerar link de checkout ($httpCode): " . json_encode($decoded));
            return ['success' => false, 'message' => $errorMessage, 'http_code' => $httpCode];
        }
    }

    /**
     * Cria o link de pagamento na InfinitePay.
     * @param array $planData Dados do plano (name, price)
     * @param array $customerData Dados do cliente (name, email, phone)
     * @param int $transactionId ID interno da transação (será o order_nsu)
     * @return array Resultado da requisição
     */
    public function createCheckoutLink($planData, $customerData, $transactionId) {
        // Prepara os itens (preço sempre em centavos)
        $items = [
            [
                'quantity' => 1,
                'price' => (int)($planData['price'] * 100),
                'description' => $planData['name'] . ' - Acesso Hotspot'
            ]
        ];

        // O 'order_nsu' é o identificador único do seu sistema
        $orderNsu = strval($transactionId); 
        
        // A redirect_url volta para sua página de sucesso, levando a referência interna
        $redirectUrl = BASE_URL . "/payment/success?external_reference=" . $orderNsu;
        
        // A webhook_url notifica o seu sistema sobre a mudança de status
        $webhookUrl = BASE_URL . "/webhook/infinitypay";

        $data = [
            "handle" => $this->handle,
            "redirect_url" => $redirectUrl, 
            "webhook_url" => $webhookUrl, 
            "order_nsu" => $orderNsu,
            "customer" => [
                "name" => $customerData['name'],
                "email" => $customerData['email'],
                "phone_number" => $customerData['phone']
            ],
            "items" => $items
        ];

        // LOG do payload exato enviado (útil para debug)
        logEvent('DEBUG_INFINITYPAY_PAYLOAD', $data, $transactionId);

        // --- Aqui: atualizar transactions.payload_response = 'Payload Criado' ---
        try {
            $db = Database::getInstance()->getConnection();
            // Verifica se a coluna existe/é utilizável; se não, o catch cuidará do erro
            $stmt = $db->prepare("UPDATE transactions SET payload_response = ? WHERE id = ?");
            $stmt->execute(['Payload Criado', $transactionId]);
            logEvent('payload_debug', "Payload Criado gravado em transactions.payload_response. TX: $transactionId", $transactionId);
        } catch (Exception $e) {
            // Apenas logamos; não interrompemos o fluxo (não é crítico)
            logEvent('payload_error', "Falha ao atualizar payload_response: " . $e->getMessage(), $transactionId);
        }

        // Envia o payload para a API (faz a requisição)

        return $this->makeRequest($this->apiUrl, $data);
    }
}
?>