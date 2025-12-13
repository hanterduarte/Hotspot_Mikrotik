<?php
// app/Router.php

class Router {
    protected $routes = [];

    /**
     * Adiciona uma rota à tabela de roteamento.
     * @param string $method O método HTTP (GET, POST, etc.)
     * @param string $uri A URI da rota
     * @param string $action A ação do controlador (ex: 'HomeController@index')
     */
    public function add($method, $uri, $action) {
        $this->routes[strtoupper($method)][$uri] = $action;
    }

    /**
     * Despacha a requisição para a rota correspondente.
     * @param string $uri A URI da requisição
     * @param string $method O método HTTP da requisição
     */
    public function dispatch($uri, $method) {
        $method = strtoupper($method);

        if (isset($this->routes[$method][$uri])) {
            $action = $this->routes[$method][$uri];
            $this->callAction(...explode('@', $action));
        } else {
            $this->handleNotFound();
        }
    }

    /**
     * Chama o método do controlador.
     * @param string $controller O nome da classe do controlador
     * @param string $method O nome do método a ser chamado
     */
    protected function callAction($controller, $method) {
        $controllerFile = ROOT_PATH . "/app/controllers/{$controller}.php";

        if (file_exists($controllerFile)) {
            require_once $controllerFile;
            $controllerInstance = new $controller();

            if (method_exists($controllerInstance, $method)) {
                $controllerInstance->$method();
            } else {
                $this->handleNotFound("Método `{$method}` não encontrado no controlador `{$controller}`.");
            }
        } else {
            $this->handleNotFound("Controlador `{$controller}` não encontrado.");
        }
    }

    /**
     * Lida com rotas não encontradas (404).
     */
    protected function handleNotFound($message = 'Página não encontrada.') {
        http_response_code(404);
        // No futuro, isso pode carregar uma view de erro.
        echo $message;
        exit;
    }
}
