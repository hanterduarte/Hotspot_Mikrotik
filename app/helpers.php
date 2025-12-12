<?php
// app/helpers.php

if (!function_exists('formatMoney')) {
    /**
     * Formata um valor numérico como uma string de moeda em Reais (BRL).
     * @param float $value O valor a ser formatado.
     * @return string A string formatada (ex: R$ 10,00).
     */
    function formatMoney($value) {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
}

if (!function_exists('logEvent')) {
    /**
     * Registra um evento no banco de dados.
     * @param string $type O tipo de log (ex: 'webhook_success', 'error').
     * @param mixed $message A mensagem a ser logada. Pode ser uma string ou um array/objeto (será convertido para JSON).
     * @param int|null $related_id Um ID relacionado ao evento (ex: ID da transação).
     * @return bool True em sucesso, false em falha.
     */
    function logEvent($type, $message, $related_id = null) {
        try {
            // Normaliza a mensagem para armazenamento
            $messageToStore = is_scalar($message) ? (string)$message : json_encode($message, JSON_UNESCAPED_UNICODE);

            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("INSERT INTO logs (log_type, log_message, related_id, created_at) VALUES (?, ?, ?, NOW())");
            return $stmt->execute([$type, $messageToStore, $related_id]);
        } catch (Exception $e) {
            // Fallback: se o log no banco falhar, registra no log de erros do PHP para não quebrar a aplicação
            error_log("Falha ao registrar log no banco: " . $e->getMessage());
            error_log("Log original: Tipo={$type}, Mensagem=" . (is_scalar($message) ? $message : json_encode($message)));
            return false;
        }
    }
}

// ----------------------------------------------------------------------
// FUNÇÕES DE SERVIÇO (EMAIL)
// ----------------------------------------------------------------------

if (!function_exists('sendEmail')) {
    /**
     * Função placeholder para envio de e-mail.
     * Recomenda-se usar PHPMailer ou um serviço de SMTP externo.
     */
    function sendEmail($to, $subject, $body) {
        // Configuração base (ajuste 'seudominio.com.br' nas settings)
        $domain = getSetting('base_domain', 'wifibarato.maiscoresed.com.br');
        $headers = "From: WiFi Barato <noreply@$domain>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";

        // A função mail() nativa precisa de um servidor de e-mail configurado no PHP
        if (getSetting('enable_email_sending', 'false') === 'true' && @mail($to, $subject, $body, $headers)) {
            logEvent('email_success', "Email de credenciais enviado para $to");
            return true;
        } else {
            logEvent('email_info', "Email de credenciais não enviado (Função mail() não usada ou desabilitada).");
            return false;
        }
    }
}

if (!function_exists('sendHotspotCredentialsEmail')) {
    /**
     * Função para formatar e enviar o email com as credenciais.
     */
    function sendHotspotCredentialsEmail($email, $username, $password, $expiresAt, $planName) {
        $subject = "Suas Credenciais WiFi - Pagamento Aprovado!";
        $body = "
            <html>
            <head>
                <title>$subject</title>
            </head>
            <body>
                <h1>Acesso WiFi Liberado!</h1>
                <p>Seu pagamento foi aprovado e seu acesso ao plano <strong>$planName</strong> está ativo.</p>
                <p>Use as credenciais abaixo para se conectar à nossa rede:</p>

                <div style='background: #f4f4f4; padding: 15px; border-radius: 5px; border: 1px solid #ddd; max-width: 400px;'>
                    <p><strong>Usuário:</strong> $username</p>
                    <p><strong>Senha:</strong> $password</p>
                    <p><strong>Expira em:</strong> " . date('d/m/Y H:i:s', strtotime($expiresAt)) . "</p>
                </div>

                <p>Obrigado!</p>
            </body>
            </html>
        ";

        return sendEmail($email, $subject, $body);
    }
}
