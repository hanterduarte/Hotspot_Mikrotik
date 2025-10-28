<?php
require_once 'config.php';

$externalReference = $_GET['external_reference'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Pendente - WiFi Barato</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #ff9800 0%, #ef6c00 100%);
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
            color: #ef6c00;
            margin-bottom: 20px;
        }
        .info-text {
            color: #444;
            margin-bottom: 25px;
            line-height: 1.5;
        }
        .btn {
            display: inline-block;
            background-color: #ff9800;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #f57c00;
        }
    </style>
</head>
<body>
    <div class="message-box">
        <h2>⚠️ Pagamento Pendente/Em Análise</h2>
        <div class="info-text">
            <p>Sua transação está com status **Pendente** ou **Em Análise**.</p>
            <p style="margin-top: 10px;">Assim que o Mercado Pago aprovar, suas credenciais serão enviadas para o seu email e você será liberado automaticamente.</p>
        </div>

        <a href="index.php" class="btn">Voltar ao Início</a>
    </div>
</body>
</html>