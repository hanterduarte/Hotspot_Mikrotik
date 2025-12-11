<?php
// app/controllers/TestController.php

require_once ROOT_PATH . '/app/controllers/BaseController.php';
require_once ROOT_PATH . '/app/models/Plan.php';
require_once ROOT_PATH . '/app/models/Customer.php';
require_once ROOT_PATH . '/app/models/Transaction.php';
require_once ROOT_PATH . '/app/models/HotspotUser.php';
require_once ROOT_PATH . '/app/services/MikrotikAPI.php';

class TestController extends BaseController {

    /**
     * Passo 1: Exibe o formulário para iniciar o teste.
     */
    public function index() {
        $planModel = new Plan();
        $plans = $planModel->getActivePlans();
        $this->view('test/index', ['plans' => $plans]);
    }

    /**
     * Passo 2: Cria uma transação pendente e avança para a simulação do webhook.
     */
    public function createPendingTransaction() {
        try {
            // Pega os dados do formulário
            $planId = $_POST['plan_id'];
            $customerData = [
                'name' => $_POST['name'],
                'email' => $_POST['email'],
                'phone' => $_POST['phone'],
                'cpf' => $_POST['cpf']
            ];

            // Instancia os modelos
            $planModel = new Plan();
            $customerModel = new Customer();
            $transactionModel = new Transaction();

            // Valida o plano
            $plan = $planModel->findById($planId);
            if (!$plan) {
                throw new Exception("Plano não encontrado.");
            }

            // Cria/obtém o cliente
            $customerId = $customerModel->createOrGet($customerData);

            // Cria a transação com status 'pending'
            $transactionId = $transactionModel->create($customerId, $plan['id'], $plan['price'], 'test_gateway');

            // Redireciona para a página de simulação do webhook
            header('Location: /test/simulate?transaction_id=' . $transactionId);
            exit;

        } catch (Exception $e) {
            // Em caso de erro, exibe a falha
            $this->view('payment/failure', ['message' => 'Erro ao criar transação de teste: ' . $e->getMessage()]);
        }
    }

    /**
     * Passo 3: Exibe a página de simulação.
     */
    public function simulate() {
        $transactionId = (int)$_GET['transaction_id'];
        $this->view('test/simulate', ['transactionId' => $transactionId]);
    }

    /**
     * Passo 4: Executa a simulação do webhook de pagamento aprovado.
     */
    public function runWebhookSimulation() {
        $transactionId = (int)$_GET['transaction_id'];

        if (!$transactionId) {
            $this->view('payment/failure', ['message' => 'ID da transação para simulação não encontrado.']);
            return;
        }

        try {
            // Simula a lógica do WebhookController
            $transactionModel = new Transaction();
            $hotspotUserModel = new HotspotUser();

            $existingTransaction = $transactionModel->findById($transactionId);
            if (!$existingTransaction) {
                throw new Exception("Transação de teste não encontrada.");
            }

            // Atualiza a transação para 'approved'
            $transactionModel->updatePaymentDetails($transactionId, 'sim_' . time(), 'approved', 'Payload de simulação');

            $planModel = new Plan();
            $plan = $planModel->findById($existingTransaction['plan_id']);

            $customerModel = new Customer();
            $customer = $customerModel->findById($existingTransaction['customer_id']);

            // Provisiona o usuário no Mikrotik
            $mikrotik = new MikrotikAPI();
            $provisionResult = $mikrotik->provisionHotspotUser($plan['id'], $transactionId);

            if (!$provisionResult['success']) {
                throw new Exception("Falha na simulação de provisionamento Mikrotik: " . $provisionResult['message']);
            }

            // Salva o usuário no banco
            $username = $provisionResult['username'];
            $password = $provisionResult['password'];
            $expiresAt = date('Y-m-d H:i:s', time() + $plan['duration_seconds']);
            $hotspotUserModel->create($transactionId, $customer['id'], $plan['id'], $username, $password, $expiresAt);

            // Envia o e-mail
            sendHotspotCredentialsEmail($customer['email'], $username, $password, $expiresAt, $plan['name']);

            // Renderiza a página de resultado com o iframe
            $this->view('test/result', ['transactionId' => $transactionId]);

        } catch (Exception $e) {
            $this->view('payment/failure', ['message' => 'Erro durante a simulação do webhook: ' . $e->getMessage()]);
        }
    }
}
