<?php
require_once 'config.php';

// O ID da transação será passado via URL (ex: ?transaction_id=123)
$transactionId = $_GET['transaction_id'] ?? null;
$credentials = null;

if ($transactionId) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT t.hotspot_user, t.hotspot_password, p.name as plan_name, p.duration_seconds
            FROM transactions t
            JOIN plans p ON t.plan_id = p.id
            WHERE t.id = ? AND t.payment_status = 'approved'
        ");
        $stmt->execute([$transactionId]);
        $credentials = $stmt->fetch();
    } catch (Exception $e) {
        logEvent('error', 'Erro ao buscar credenciais na página de sucesso: ' . $e->getMessage(), $transactionId);
        // Não faz nada, a página mostrará a mensagem de erro padrão
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Aprovado - WiFi Barato</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            max-width: 600px;
            width: 100%;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 50px rgba(0,0,0,0.3);
            text-align: center;
        }
        .success-icon {
            width: 100px;
            height: 100px;
            background: #4caf50;
            border-radius: 50%;
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: scaleIn 0.5s ease;
        }
        @keyframes scaleIn {
            0% { transform: scale(0); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .success-icon::after {
            content: '✓';
            font-size: 60px;
            color: white;
            font-weight: bold;
        }
        h1 { color: #4caf50; font-size: 2em; margin-bottom: 15px; }
        .subtitle { color: #666; font-size: 1.1em; margin-bottom: 30px; }
        .credentials-box {
            background: #f0f7ff;
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            border-left: 4px solid #4caf50;
            text-align: left;
        }
        .credentials-box h3 { color: #4caf50; margin-bottom: 20px; text-align: center; }
        .credential-item { margin: 15px 0; padding: 15px; background: white; border-radius: 10px; }
        .credential-label { font-weight: bold; color: #333; display: block; margin-bottom: 5px; font-size: 0.9em; }
        .credential-value { font-size: 1.3em; color: #4caf50; font-family: monospace; font-weight: bold; }
        .instructions { background: #fff9e6; padding: 20px; border-radius: 10px; margin: 20px 0; text-align: left; }
        .instructions h4 { color: #f57c00; margin-bottom: 15px; }
        .instructions ol { margin-left: 20px; }
        .instructions li { margin: 8px 0; color: #666; }
        .info-text { background: #e8f5e9; padding: 15px; border-radius: 10px; margin: 20px 0; color: #2e7d32; }
        .error-text { background: #ffebee; padding: 15px; border-radius: 10px; margin: 20px 0; color: #c62828; }
        .btn {
            background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 10px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon"></div>
        
        <h1>🎉 Pagamento Aprovado!</h1>
        <p class="subtitle">Seu acesso à internet foi liberado.</p>

        <?php if ($credentials && $credentials['hotspot_user']): ?>
            <div class="credentials-box">
                <h3>📱 Suas Credenciais de Acesso</h3>

                <div class="credential-item">
                    <span class="credential-label">Plano:</span>
                    <span class="credential-value"><?php echo htmlspecialchars($credentials['plan_name']); ?></span>
                </div>

                <div class="credential-item">
                    <span class="credential-label">Usuário:</span>
                    <span class="credential-value"><?php echo htmlspecialchars($credentials['hotspot_user']); ?></span>
                </div>

                <div class="credential-item">
                    <span class="credential-label">Senha:</span>
                    <span class="credential-value"><?php echo htmlspecialchars($credentials['hotspot_password']); ?></span>
                </div>
            </div>

            <div class="instructions">
                <h4>📝 Como Conectar:</h4>
                <ol>
                    <li>Conecte-se à nossa rede WiFi.</li>
                    <li>A página de login abrirá automaticamente.</li>
                    <li>Digite seu usuário e senha.</li>
                    <li>Pronto! Boa navegação!</li>
                </ol>
            </div>

            <div class="info-text">
                <strong>✉️ E-mail a caminho!</strong><br>
                Uma cópia das suas credenciais foi enviada para o seu e-mail.
            </div>
        <?php else: ?>
            <div class="error-text">
                <p><strong>Ops! Algo deu errado.</strong></p>
                <p>Não foi possível exibir suas credenciais. Por favor, verifique seu e-mail ou entre em contato com o suporte se precisar de ajuda.</p>
            </div>
        <?php endif; ?>

        <a href="index.php" class="btn">Voltar ao Início</a>
    </div>
</body>
</html>
