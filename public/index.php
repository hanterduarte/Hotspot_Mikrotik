<?php
// public/index.php

// Define o caminho raiz do projeto para facilitar a inclusão de arquivos
define('ROOT_PATH', dirname(__DIR__));

// Carrega o arquivo de configuração
require_once ROOT_PATH . '/config/config.php';

// Carrega o roteador
require_once ROOT_PATH . '/app/Router.php';

// Cria uma instância do roteador
$router = new Router();

// Adiciona as rotas da aplicação
$router->add('GET', '', 'HomeController@index'); // Rota para a página inicial
$router->add('GET', '/', 'HomeController@index'); // Rota para a página inicial
$router->add('POST', 'payment/process', 'PaymentController@process'); // Rota para processar o pagamento
$router->add('POST', 'webhook/infinitypay', 'WebhookController@handleInfinityPay'); // Rota para o webhook
$router->add('GET', 'payment/success', 'PaymentController@success'); // Rota para a página de sucesso
$router->add('GET', 'payment/status', 'PaymentController@checkStatus'); // Rota para o verificador de status AJAX

// --- Rotas de Teste Interativo ---
// Acessar /test para iniciar o simulador de compra.
// Recomenda-se remover ou comentar estas linhas em produção.
$router->add('GET', 'test', 'TestController@index');
$router->add('POST', 'test/create-pending', 'TestController@createPendingTransaction');
$router->add('GET', 'test/simulate', 'TestController@simulate');
$router->add('GET', 'test/simulate-webhook', 'TestController@runWebhookSimulation');

// Captura a rota da requisição
$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$method = $_SERVER['REQUEST_METHOD'];

// Despacha a rota
$router->dispatch($uri, $method);
