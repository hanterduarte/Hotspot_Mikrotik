<?php
// app/controllers/PaymentController.php

require_once ROOT_PATH . '/app/controllers/BaseController.php';
require_once ROOT_PATH . '/app/models/Plan.php';
require_once ROOT_PATH . '/app/models/Customer.php';
require_once ROOT_PATH . '/app/models/Transaction.php';
require_once ROOT_PATH . '/app/models/HotspotUser.php';
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

            // 3. Coleta os dados do Mikrotik do input (se existirem)
            $mikrotikData = [
                'client_ip' => $input['client_ip'] ?? null,
                'client_mac' => $input['client_mac'] ?? null,
                'link_orig' => $input['link_orig'] ?? null,
                'link_login_only' => $input['link_login_only'] ?? null,
                'chap_id' => $input['chap_id'] ?? null,
                'chap_challenge' => $input['chap_challenge'] ?? null
            ];

            // Salva os links do Mikrotik na sessão para usar na página de sucesso
            if (!empty($mikrotikData['link_login_only'])) {
                $_SESSION['mikrotik_links'] = [
                    'linkLogin' => $mikrotikData['link_login_only'],
                    'linkOrig' => $mikrotikData['link_orig']
                ];
            }

            // Cria a transação com status 'pending' e os dados do Mikrotik
            $transactionId = $transactionModel->create($customerId, $plan['id'], $plan['price'], 'infinity_pay', $mikrotikData);
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

    /**
     * Exibe a página de sucesso após o pagamento.
     */
    public function success() {
        // Pega o ID da transação da URL
        $transactionId = isset($_GET['external_reference']) ? (int)$_GET['external_reference'] : 0;

        if (!$transactionId) {
            $this->view('payment/failure', ['message' => 'ID da transação não encontrado.']);
            return;
        }

        // Busca as credenciais do usuário
        $hotspotUserModel = new HotspotUser();
        $hotspotUser = $hotspotUserModel->findByTransactionId($transactionId);

        if ($hotspotUser) {
            // Busca os detalhes do plano
            $planModel = new Plan();
            $plan = $planModel->findById($hotspotUser['plan_id']);

            // Recupera os links do Mikrotik da sessão ou usa um fallback
            $mikrotikLinks = $_SESSION['mikrotik_links'] ?? ['linkLogin' => '#', 'linkOrig' => '/'];

            // Prepara os dados para a view
            $data = [
                'user' => $hotspotUser,
                'plan' => $plan,
                'mikrotik' => $mikrotikLinks
            ];

            // Exibe a página de sucesso com todos os dados
            $this->view('payment/success', $data);
        } else {
            // Se o usuário ainda não foi criado (webhook pode estar atrasado), exibe uma mensagem de pendente.
            $this->view('payment/pending', [
                'message' => 'Seu pagamento foi aprovado! Estamos gerando suas credenciais.',
                'transactionId' => $transactionId
            ]);
        }
    }

    /**
     * Verifica o status de criação do usuário do hotspot para uma transação.
     * Usado pela página 'pending' para polling AJAX.
     */
    public function checkStatus() {
        header('Content-Type: application/json');
        $transactionId = isset($_GET['transaction_id']) ? (int)$_GET['transaction_id'] : 0;

        if (!$transactionId) {
            echo json_encode(['status' => 'erro', 'message' => 'ID da transação não fornecido.']);
            return;
        }

        $hotspotUserModel = new HotspotUser();
        $hotspotUser = $hotspotUserModel->findByTransactionId($transactionId);

        if ($hotspotUser) {
            echo json_encode(['status' => 'criado']);
        } else {
            echo json_encode(['status' => 'pendente']);
        }
    }
}
