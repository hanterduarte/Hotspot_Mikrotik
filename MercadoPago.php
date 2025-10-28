<?php
// MercadoPago.php - Classe para integração com Mercado Pago

class MercadoPago {
    private $accessToken;
    private $publicKey;

    public function __construct() {
        $this->accessToken = getSetting('mercadopago_access_token');
        $this->publicKey = getSetting('mercadopago_public_key');
    }

    public function getPublicKey() {
        return $this->publicKey;
    }

    // Criar preferência de pagamento
    public function createPreference($planData, $customerData) {
        $url = 'https://api.mercadopago.com/checkout/preferences';

        $preference = [
            'items' => [
                [
                    'title' => $planData['name'],
                    'description' => $planData['description'],
                    'quantity' => 1,
                    'currency_id' => 'BRL',
                    'unit_price' => floatval($planData['price'])
                ]
            ],
            'payer' => [
                'name' => $customerData['name'],
                'email' => $customerData['email'],
                'phone' => [
                    'number' => preg_replace('/[^0-9]/', '', $customerData['phone'])
                ],
                'identification' => [
                    'type' => 'CPF',
                    'number' => preg_replace('/[^0-9]/', '', $customerData['cpf'])
                ]
            ],
            'back_urls' => [
                'success' => BASE_URL . 'https://wifibarato.maiscoresed.com.br/hotspot/payment_success.php',
                'failure' => BASE_URL . 'https://wifibarato.maiscoresed.com.br/hotspot/payment_failure.php',
                'pending' => BASE_URL . 'https://wifibarato.maiscoresed.com.br/hotspot/payment_pending.php'
            ],
            'auto_return' => 'approved',
            'notification_url' => BASE_URL . 'https://wifibarato.maiscoresed.com.br/hotspot/webhook_mercadopago.php',
            'external_reference' => $customerData['transaction_id'],
            'statement_descriptor' => 'WiFi Barato',
            'expires' => true,
            'expiration_date_from' => date('c'),
            'expiration_date_to' => date('c', strtotime('+30 minutes'))
        ];

        $response = $this->makeRequest($url, 'POST', $preference);

        if ($response && isset($response['id'])) {
            logEvent('mercadopago_success', "Preferência criada: " . $response['id'], $customerData['transaction_id']);
            return [
                'success' => true,
                'preference_id' => $response['id'],
                'init_point' => $response['init_point'],
                'sandbox_init_point' => $response['sandbox_init_point'] ?? null
            ];
        }

        logEvent('mercadopago_error', "Erro ao criar preferência: " . json_encode($response));
        return [
            'success' => false,
            'message' => 'Erro ao criar preferência de pagamento',
            'error' => $response
        ];
    }

    // Criar pagamento PIX
    public function createPixPayment($planData, $customerData) {
        $url = 'https://api.mercadopago.com/v1/payments';

        $payment = [
            'transaction_amount' => floatval($planData['price']),
            'description' => $planData['name'] . ' - ' . $planData['description'],
            'payment_method_id' => 'pix',
            'payer' => [
                'email' => $customerData['email'],
                'first_name' => explode(' ', $customerData['name'])[0],
                'last_name' => substr($customerData['name'], strlen(explode(' ', $customerData['name'])[0])),
                'identification' => [
                    'type' => 'CPF',
                    'number' => preg_replace('/[^0-9]/', '', $customerData['cpf'])
                ]
            ],
            'external_reference' => $customerData['transaction_id'],
            'notification_url' => BASE_URL . 'https://wifibarato.maiscoresed.com.br/hotspot/webhook_mercadopago.php'
        ];

        $response = $this->makeRequest($url, 'POST', $payment);

        if ($response && isset($response['id'])) {
            logEvent('mercadopago_success', "Pagamento PIX criado: " . $response['id'], $customerData['transaction_id']);

            $qrCode = null;
            $qrCodeBase64 = null;

            if (isset($response['point_of_interaction']['transaction_data'])) {
                $qrCode = $response['point_of_interaction']['transaction_data']['qr_code'] ?? null;
                $qrCodeBase64 = $response['point_of_interaction']['transaction_data']['qr_code_base64'] ?? null;
            }

            return [
                'success' => true,
                'payment_id' => $response['id'],
                'status' => $response['status'],
                'qr_code' => $qrCode,
                'qr_code_base64' => $qrCodeBase64,
                'expiration_date' => $response['date_of_expiration'] ?? null
            ];
        }

        logEvent('mercadopago_error', "Erro ao criar pagamento PIX: " . json_encode($response));
        return [
            'success' => false,
            'message' => 'Erro ao criar pagamento PIX',
            'error' => $response
        ];
    }

    // Buscar informações de pagamento
    public function getPayment($paymentId) {
        $url = "https://api.mercadopago.com/v1/payments/$paymentId";
        $response = $this->makeRequest($url, 'GET');

        if ($response && isset($response['id'])) {
            return [
                'success' => true,
                'payment' => $response
            ];
        }

        return [
            'success' => false,
            'message' => 'Pagamento não encontrado'
        ];
    }

    // Fazer requisição HTTP
    private function makeRequest($url, $method = 'GET', $data = null) {
        $ch = curl_init();

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->accessToken
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            logEvent('mercadopago_error', "Erro CURL: $error");
            return null;
        }

        curl_close($ch);

        $decoded = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return $decoded;
        }

        logEvent('mercadopago_error', "HTTP $httpCode: " . $response);
        return $decoded;
    }
}
?>