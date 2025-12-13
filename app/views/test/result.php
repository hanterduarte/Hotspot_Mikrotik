<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Fluxo de Venda - Resultado</title>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; padding: 20px; }
        .container { max-width: 800px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #28a745; }
        .iframe-container { border: 2px solid #ccc; margin-top: 20px; }
        iframe { width: 100%; height: 600px; border: none; }
        .controls { margin-top: 20px; text-align: center; }
        .btn { background: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; text-decoration: none; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Simulação Concluída com Sucesso!</h1>
        <p>O fluxo de pagamento foi simulado, o usuário foi criado no Mikrotik e o e-mail foi enviado.</p>
        <p>Abaixo está a página de sucesso real que o cliente veria, exibida dentro de um frame para teste.</p>

        <div class="iframe-container">
            <iframe src="/payment/success?external_reference=<?php echo htmlspecialchars($transactionId); ?>&link-login-only=<?php echo urlencode($link_login_only); ?>&link-orig=<?php echo urlencode($link_orig); ?>"></iframe>
        </div>

        <div class="controls">
            <a href="/test" class="btn">Iniciar Novo Teste</a>
        </div>
    </div>
</body>
</html>
