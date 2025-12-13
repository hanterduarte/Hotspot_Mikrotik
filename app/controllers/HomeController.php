<?php
// app/controllers/HomeController.php

require_once ROOT_PATH . '/app/controllers/BaseController.php';
require_once ROOT_PATH . '/app/models/Plan.php';

class HomeController extends BaseController {
    /**
     * Exibe a página principal com a lista de planos.
     */
    public function index() {
        // Captura as variáveis do Mikrotik da URL (se existirem)
        $mikrotikParams = [
            'linkLogin' => isset($_GET['link-login-only']) ? $_GET['link-login-only'] : '',
            'linkOrig' => isset($_GET['link-orig']) ? $_GET['link-orig'] : '',
            'chapId' => isset($_GET['chap-id']) ? $_GET['chap-id'] : '',
            'chapChallenge' => isset($_GET['chap-challenge']) ? $_GET['chap-challenge'] : '',
            'username' => isset($_GET['username']) ? $_GET['username'] : '',
            'error' => isset($_GET['error']) ? $_GET['error'] : '',
            'clientIp' => isset($_GET['ip']) ? $_GET['ip'] : '',
            'clientMac' => isset($_GET['mac']) ? $_GET['mac'] : '',
        ];

        // Se os parâmetros do portal cativo estiverem presentes, salva na sessão
        if (!empty($mikrotikParams['linkLogin'])) {
            $_SESSION['mikrotik_links'] = [
                'linkLogin' => $mikrotikParams['linkLogin'],
                'linkOrig' => $mikrotikParams['linkOrig']
            ];
        }

        // Cria uma instância do modelo Plan
        $planModel = new Plan();

        // Busca os planos ativos
        $plans = $planModel->getActivePlans();

        // Combina os planos com os parâmetros do Mikrotik para enviar à view
        $data = array_merge($mikrotikParams, ['plans' => $plans]);

        // Renderiza a view, passando os dados
        $this->view('home/index', $data);
    }
}
