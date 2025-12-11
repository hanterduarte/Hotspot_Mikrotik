<?php
// app/controllers/TestController.php

require_once ROOT_PATH . '/app/controllers/BaseController.php';
require_once ROOT_PATH . '/app/models/Plan.php';
require_once ROOT_PATH . '/app/models/Customer.php';
require_once ROOT_PATH . '/app/models/Transaction.php';
require_once ROOT_PATH . '/app/models/HotspotUser.php';
require_once ROOT_PATH . '/app/services/MikrotikAPI.php';
require_once ROOT_PATH . '/app/controllers/WebhookController.php';

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

            // Coleta os dados de simulação
            $simulationData = [
                'client_ip' => $_POST['client_ip'],
                'client_mac' => $_POST['client_mac'],
                'link_orig' => $_POST['link_orig'],
                'link_login_only' => $_POST['link_login_only'],
                'chap_id' => $_POST['chap_id'],
                'chap_challenge' => $_POST['chap_challenge']
            ];

            // Cria a transação com os dados de simulação
            $transactionId = $transactionModel->create($customerId, $plan['id'], $plan['price'], 'test_gateway', $simulationData);

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
            // Constrói um payload de webhook fictício
            $simulatedPayload = json_encode([
                'order_nsu' => $transactionId,
                'status' => 'PAID', // Usa o status correto
                'transaction_id' => 'sim_' . time()
            ]);

            // Cria uma instância do WebhookController e chama a lógica de processamento
            $webhookController = new WebhookController();
            $webhookController->processWebhookPayload($simulatedPayload);

            // Recupera a transação atualizada para obter os dados de simulação
            $transactionModel = new Transaction();
            $transactionData = $transactionModel->findById($transactionId);

            // Prepara os dados para a página de resultado
            $data = [
                'transactionId' => $transactionId,
                'link_orig' => $transactionData['sim_link_orig'] ?? '',
                'link_login_only' => $transactionData['sim_link_login_only'] ?? ''
            ];

            // Renderiza a página de resultado com o iframe
            $this->view('test/result', $data);

        } catch (Exception $e) {
            $this->view('payment/failure', ['message' => 'Erro durante a simulação do webhook: ' . $e->getMessage()]);
        }
    }
}
