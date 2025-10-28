<?php
require_once 'config.php';

$externalReference = $_GET['external_reference'] ?? null;
$message = "Seu pagamento não foi aprovado.";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Não Aprovado - WiFi Barato</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f44336 0%, #c62828 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .message-box {
            background: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            max-width: 400px;
            width: 100%;
        }
        .message-box h2 {
            color: #c62828;
            margin-bottom: 20px;
        }
        .info-text {
            color: #444;
            margin-bottom: 25px;
            line-height: 1.5;
        }
        .btn {
            display: inline-block;
            background-color: #f44336;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #d32f2f;
        }
    </style>
</head>
<body>
    <div class="message-box">
        <h2>❌ Pagamento Não Aprovado</h2>
        <div class="info-text">
            <p>Não foi possível concluir a transação. O status atual do seu pagamento é **Rejeitado**.</p>
            <p style="margin-top: 10px;">Por favor, verifique seus dados de pagamento e tente novamente.</p>
        </div>

        <a href="index.php" class="btn">Tentar Novo Pagamento</a>
    </div>
</body>
</html>