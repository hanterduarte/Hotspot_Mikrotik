<?php
// app/controllers/PaymentController.php

require_once ROOT_PATH . '/app/controllers/BaseController.php';
require_once ROOT_PATH . '/app/models/Plan.php';
require_once ROOT_PATH . '/app/models/Customer.php';
require_once ROOT_PATH . '/app/models/Transaction.php';
require_once ROOT_PATH . '/app/services/InfinityPay.php';

class PaymentController extends BaseController {
    /**
     * Processa a solicitação de pagamento do formulário.
     */
    public function process() {
        // Define o cabeçalho da resposta como JSON
        header('Content-Type: application/json');

        // Pega os dados do corpo da requisição POST (enviados como JSON)
        $input = json_decode(file_get_contents('php://input'), true);

        // Validação básica dos dados de entrada
        if (!$this->validateInput($input)) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
            return;
        }

        try {
            // Instancia os modelos e serviços necessários
            $planModel = new Plan();
            $customerModel = new Customer();
            $transactionModel = new Transaction();
            $infinityPay = new InfinityPay();

            // 1. Busca os detalhes do plano
            $plan = $planModel->findById($input['plan_id']);
            if (!$plan) {
                echo json_encode(['success' => false, 'message' => 'Plano não encontrado.']);
                return;
            }

            // 2. Cria ou obtém o cliente
            $customerId = $customerModel->createOrGet($input);
            if (!$customerId) {
                echo json_encode(['success' => false, 'message' => 'Erro ao processar dados do cliente.']);
                return;
            }

            // 3. Cria a transação com status 'pending'
            $transactionId = $transactionModel->create($customerId, $plan['id'], $plan['price']);
            if (!$transactionId) {
                echo json_encode(['success' => false, 'message' => 'Erro ao criar a transação.']);
                return;
            }

            // 4. Cria o link de checkout da InfinitePay
            $checkout = $infinityPay->createCheckoutLink(
                ['name' => $plan['name'], 'price' => $plan['price']],
                $input,
                $transactionId
            );

            // 5. Retorna a resposta
            if ($checkout['success']) {
                echo json_encode([
                    'success' => true,
                    'data' => ['redirect_url' => $checkout['url']]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => $checkout['message']]);
            }

        } catch (Exception $e) {
            // Em caso de erro, loga e retorna uma mensagem genérica
            error_log("Erro no processamento de pagamento: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Ocorreu um erro interno.']);
        }
    }

    /**
     * Valida os dados de entrada do formulário.
     * @param array $data
     * @return bool
     */
    private function validateInput(array $data) {
        return isset($data['plan_id'], $data['name'], $data['email'], $data['phone'], $data['cpf']) &&
               !empty($data['plan_id']) &&
               !empty($data['name']) &&
               filter_var($data['email'], FILTER_VALIDATE_EMAIL) &&
               preg_match('/^[0-9]{10,11}$/', $data['phone']) &&
               preg_match('/^[0-9]{11}$/', $data['cpf']);
    }
}
