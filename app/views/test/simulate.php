<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Fluxo de Venda - Passo 2</title>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; padding: 20px; text-align: center; }
        .container { max-width: 600px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .transaction-info { background: #e9ecef; padding: 15px; border-radius: 4px; margin: 20px 0; }
        .btn { background: #28a745; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; text-decoration: none; }
        .btn:hover { background: #218838; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Simulador de Compra - Passo 2</h1>
        <div class="transaction-info">
            <p>Transação pendente criada com sucesso!</p>
            <strong>ID da Transação: <?php echo htmlspecialchars($transactionId); ?></strong>
        </div>
        <p>Agora, clique no botão abaixo para simular o recebimento de um pagamento aprovado via webhook.</p>
        <a href="/test/simulate-webhook?transaction_id=<?php echo htmlspecialchars($transactionId); ?>" class="btn">
            Simular Pagamento Aprovado
        </a>
    </div>
</body>
</html>
