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
            throw new Exception("Erro de cURL: " . $error);
        }

        curl_close($ch);
        $result = json_decode($response, true);

        return [
            'http_code' => $httpCode,
            'response' => $result
        ];
    }
    
    /**
     * Cria um link de checkout
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
        $redirectUrl = BASE_URL . "/payment_success.php?external_reference=" . $orderNsu; 
        
        // A webhook_url notifica o seu sistema sobre a mudança de status
        $webhookUrl = BASE_URL . "/webhook_infinitypay.php"; 

        $data = [
            "handle" => $this->handle,
            "redirect_url" => $redirectUrl,
            "webhook_url" => $webhookUrl,
            "order_nsu" => $orderNsu,
            "customer" => [
                "name" => $customerData['name'],
                "email" => $customerData['email'],
                "phone" => $customerData['phone'],
                "cpf" => $customerData['cpf']
            ],
            "items" => $items
        ];
        
        try {
            $requestResult = $this->makeRequest($this->apiUrl, $data);
            $response = $requestResult['response'];

            if ($requestResult['http_code'] === 200 && isset($response['payment_url'])) {
                return [
                    'success' => true, 
                    'url' => $response['payment_url'],
                    'invoice_slug' => $response['invoice_slug'] ?? null // Salva o slug para consulta
                ];
            }

            $errorMessage = $response['message'] ?? 'Erro desconhecido ao criar link.';
            return ['success' => false, 'message' => $errorMessage];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro interno na requisição: ' . $e->getMessage()];
        }
    }
    
    /**
     * NOVO: Consulta o status de uma fatura na InfinitePay usando o slug (para o fallback)
     */
    public function getInvoiceStatus($invoiceSlug) {
        // A URL da API para buscar detalhes da fatura
        $url = "https://api.infinitepay.io/v1/invoices/{$invoiceSlug}"; 
        
        // Tenta usar o Handle como Token de autenticação (simplificação, pois a integração não usa Secret Key)
        $handle = $this->handle; 
        
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $headers = [
            'Content-Type: application/json'
        ];
        
        // Inclui o Handle como Authorization Header
        if (!empty($handle)) {
             $headers[] = 'Authorization: Bearer ' . $handle;
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode === 200 && isset($data['status'])) {
            return ['success' => true, 'data' => $data];
        }
        
        logEvent('infinitepay_api_error', "Falha ao verificar status da fatura $invoiceSlug. Code: $httpCode. Response: " . $response);
        
        return ['success' => false, 'message' => "Erro ao verificar status na InfinitePay."];
    }
}