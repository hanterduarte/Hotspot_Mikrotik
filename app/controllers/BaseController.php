<?php
// app/controllers/BaseController.php

class BaseController {
    /**
     * Renderiza uma view, passando dados para ela.
     * @param string $view O nome do arquivo da view (sem a extensão .php)
     * @param array $data Um array associativo de dados a serem extraídos para a view.
     */
    protected function view($view, $data = []) {
        // Extrai o array de dados em variáveis individuais.
        // Ex: ['plans' => $plans] se torna a variável $plans dentro da view.
        extract($data);

        // Constrói o caminho para o arquivo da view.
        $viewFile = ROOT_PATH . "/app/views/{$view}.php";

        if (file_exists($viewFile)) {
            // Inclui o arquivo da view, que agora tem acesso às variáveis extraídas.
            require_once $viewFile;
        } else {
            // Lida com o caso em que o arquivo da view não existe.
            http_response_code(404);
            echo "View não encontrada: {$viewFile}";
        }
    }
}
