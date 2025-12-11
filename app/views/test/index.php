<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Fluxo de Venda - Passo 1</title>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; padding: 20px; }
        .container { max-width: 600px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc; box-sizing: border-box; }
        .btn { background: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Simulador de Compra - Passo 1</h1>
        <p>Preencha os dados abaixo para criar uma transação pendente.</p>
        <form action="/test/create-pending" method="POST">
            <div class="form-group">
                <label for="plan_id">Selecione o Plano:</label>
                <select name="plan_id" id="plan_id" required>
                    <?php foreach ($plans as $plan): ?>
                        <option value="<?php echo $plan['id']; ?>">
                            <?php echo htmlspecialchars($plan['name']) . ' - ' . formatMoney($plan['price']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="name">Nome do Cliente:</label>
                <input type="text" name="name" id="name" value="Cliente de Teste" required>
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" name="email" id="email" value="teste@example.com" required>
            </div>
            <div class="form-group">
                <label for="phone">Telefone (11 dígitos):</label>
                <input type="text" name="phone" id="phone" value="11999999999" required pattern="[0-9]{10,11}">
            </div>
            <div class="form-group">
                <label for="cpf">CPF (11 dígitos):</label>
                <input type="text" name="cpf" id="cpf" value="12345678901" required pattern="[0-9]{11}">
            </div>

            <hr style="margin: 20px 0;">
            <h3 style="color: #555;">Parâmetros de Simulação Mikrotik (Opcional)</h3>
            <div class="form-group">
                <label for="client_ip">IP do Cliente:</label>
                <input type="text" name="client_ip" id="client_ip" value="10.0.0.254">
            </div>
            <div class="form-group">
                <label for="client_mac">MAC do Cliente:</label>
                <input type="text" name="client_mac" id="client_mac" value="00:11:22:33:44:55">
            </div>
            <div class="form-group">
                <label for="link_orig">Link de Origem (link-orig):</label>
                <input type="text" name="link_orig" id="link_orig" value="http://www.google.com/">
            </div>
            <div class="form-group">
                <label for="chap_id">Chap ID:</label>
                <input type="text" name="chap_id" id="chap_id" value="ab">
            </div>
             <div class="form-group">
                <label for="chap_challenge">Chap Challenge:</label>
                <input type="text" name="chap_challenge" id="chap_challenge" value="1234567890abcdef">
            </div>

            <button type="submit" class="btn">Criar Transação Pendente</button>
        </form>
    </div>
</body>
</html>
