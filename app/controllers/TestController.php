<?php
// app/controllers/TestController.php

require_once ROOT_PATH . '/app/controllers/BaseController.php';
require_once ROOT_PATH . '/app/models/Transaction.php';
require_once ROOT_PATH . '/app/models/Customer.php';
require_once ROOT_PATH . '/app/models/Plan.php';
require_once ROOT_PATH . '/app/models/HotspotUser.php';
require_once ROOT_PATH . '/app/services/MikrotikAPI.php';

class TestController extends BaseController {

    /**
     * Simula o fluxo de venda completo para fins de teste.
     */
    public function runSalesFlowTest() {
        header('Content-Type: text/html; charset=utf-8');
        echo "<h1>Iniciando Teste de Fluxo de Venda</h1>";
        echo "<pre>";

        try {
            // --- 1. Dados de Teste ---
            $testPlanId = 1; // ID do plano a ser testado
            $testCustomerData = [
                'name' => 'Cliente Teste',
                'email' => 'teste@example.com', // E-mail para onde a notificação será enviada
                'phone' => '99999999999',
                'cpf' => '00000000000'
            ];
            echo "-> Usando Plano ID: {$testPlanId} e Cliente: {$testCustomerData['email']}\n";

            // --- 2. Instanciar Modelos ---
            $planModel = new Plan();
            $customerModel = new Customer();
            $transactionModel = new Transaction();
            $hotspotUserModel = new HotspotUser();

            // --- 3. Buscar Dados do Plano e Cliente ---
            $plan = $planModel->findById($testPlanId);
            if (!$plan) {
                throw new Exception("Plano de teste com ID {$testPlanId} não encontrado.");
            }
            echo "-> Plano encontrado: " . htmlspecialchars($plan['name']) . "\n";

            $customerId = $customerModel->createOrGet($testCustomerData);
            echo "-> ID do Cliente (criado/obtido): {$customerId}\n";

            // --- 4. Criar uma Transação de Teste ---
            $transactionId = $transactionModel->create($customerId, $plan['id'], $plan['price'], 'test_gateway');
            $transactionModel->updatePaymentDetails($transactionId, 'test_' . time(), 'approved', 'Payload de teste');
            echo "-> Transação de teste criada com ID: {$transactionId}\n";

            // --- 5. Provisionar Usuário no Mikrotik ---
            echo "-> Tentando provisionar usuário no Mikrotik...\n";
            $mikrotik = new MikrotikAPI();
            $provisionResult = $mikrotik->provisionHotspotUser($plan['id'], $transactionId);

            if (!$provisionResult['success']) {
                throw new Exception("Falha ao provisionar usuário no Mikrotik: " . $provisionResult['message']);
            }

            $username = $provisionResult['username'];
            $password = $provisionResult['password'];
            echo "-> Usuário provisionado no Mikrotik com sucesso!\n";
            echo "   - Usuário: <strong>{$username}</strong>\n";
            echo "   - Senha:   <strong>{$password}</strong>\n";

            // --- 6. Salvar Usuário no Banco de Dados ---
            $expiresAt = date('Y-m-d H:i:s', time() + $plan['duration_seconds']);
            $hotspotUserModel->create($transactionId, $customerId, $plan['id'], $username, $password, $expiresAt);
            echo "-> Credenciais salvas no banco de dados local.\n";

            // --- 7. Enviar E-mail de Teste ---
            echo "-> Tentando enviar e-mail para {$testCustomerData['email']}...\n";
            $emailSent = sendHotspotCredentialsEmail(
                $testCustomerData['email'],
                $username,
                $password,
                $expiresAt,
                $plan['name']
            );

            if ($emailSent) {
                echo "-> E-mail enviado com sucesso!\n";
            } else {
                echo "-> Falha ao enviar e-mail. Verifique as configurações e o log de eventos.\n";
            }

            echo "\n</pre>";
            echo "<h2>✅ Teste de Fluxo de Venda Concluído com Sucesso!</h2>";

        } catch (Exception $e) {
            echo "\n</pre>";
            echo "<h2>❌ ERRO NO TESTE:</h2>";
            echo "<p style='color: red; font-weight: bold;'>" . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}
