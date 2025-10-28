<?php
require_once 'config.php';

$paymentId = $_GET['payment_id'] ?? null;
$externalReference = $_GET['external_reference'] ?? null;

$credentials = null;

if ($externalReference) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT hu.username, hu.password as user_password, p.name as plan_name, hu.expires_at
        FROM hotspot_users hu
        JOIN transactions t ON hu.transaction_id = t.id
        JOIN plans p ON hu.plan_id = p.id
        WHERE t.id = ?
        ORDER BY hu.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$externalReference]);
    $credentials = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Aprovado - WiFi Barato</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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

        h1 {
            color: #4caf50;
            font-size: 2em;
            margin-bottom: 15px;
        }

        .subtitle {
            color: #666;
            font-size: 1.1em;
            margin-bottom: 30px;
        }

        .credentials-box {
            background: #f0f7ff;
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            border-left: 4px solid #4caf50;
            text-align: left;
        }

        .credentials-box h3 {
            color: #4caf50;
            margin-bottom: 20px;
            text-align: center;
        }

        .credential-item {
            margin: 15px 0;
            padding: 15px;
            background: white;
            border-radius: 10px;
        }

        .credential-label {
            font-weight: bold;
            color: #333;
            display: block;
            margin-bottom: 5px;
            font-size: 0.9em;
        }

        .credential-value {
            font-size: 1.3em;
            color: #4caf50;
            font-family: monospace;
            font-weight: bold;
        }

        .instructions {
            background: #fff9e6;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: left;
        }

        .instructions h4 {
            color: #f57c00;
            margin-bottom: 15px;
        }

        .instructions ol {
            margin-left: 20px;
        }

        .instructions li {
            margin: 8px 0;
            color: #666;
        }

        .info-text {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            color: #2e7d32;
        }

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

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }

        @media (max-width: 768px) {
            .container {
                padding: 25px;
            }

            h1 {
                font-size: 1.5em;
            }

            .success-icon {
                width: 80px;
                height: 80px;
            }

            .success-icon::after {
                font-size: 40px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon"></div>
        
        <h1>🎉 Pagamento Aprovado!</h1>
        <p class="subtitle">Seu acesso foi liberado com sucesso</p>

        <?php if ($credentials): ?>
        <div class="credentials-box">
            <h3>📱 Suas Credenciais de Acesso</h3>

            <div class="credential-item">
                <span class="credential-label">Plano:</span>
                <span class="credential-value"><?php echo htmlspecialchars($credentials['plan_name']); ?></span>
            </div>

            <div class="credential-item">
                <span class="credential-label">Usuário:</span>
                <span class="credential-value"><?php echo htmlspecialchars($credentials['username']); ?></span>
            </div>

            <div class="credential-item">
                <span class="credential-label">Senha:</span>
                <span class="credential-value"><?php echo htmlspecialchars($credentials['user_password']); ?></span>
            </div>

            <div class="credential-item">
                <span class="credential-label">Válido até:</span>
                <span class="credential-value"><?php echo date('d/m/Y H:i', strtotime($credentials['expires_at'])); ?></span>
            </div>
        </div>

        <div class="instructions">
            <h4>📝 Como usar:</h4>
            <ol>
                <li>Conecte-se à rede WiFi</li>
                <li>Uma página de login será aberta automaticamente</li>
                <li>Digite o usuário e senha acima</li>
                <li>Clique em "Conectar" e pronto!</li>
            </ol>
        </div>

        <div class="info-text">
            <strong>✉️ Email enviado!</strong><br>
            Suas credenciais também foram enviadas para o seu email. Guarde-as em local seguro!
        </div>
        <?php else: ?>
        <div class="info-text">
            <p><strong>Aguarde alguns instantes...</strong></p>
            <p>Suas credenciais estão sendo geradas e serão enviadas para seu email em breve.</p>
        </div>
        <?php endif; ?>

        <a href="index.php" class="btn">Voltar ao Início</a>
    </div>

    <script>
        // Se não tem credenciais, verificar a cada 3 segundos
        <?php if (!$credentials && $externalReference): ?>
        let checkCount = 0;
        const maxChecks = 10;

        const checkInterval = setInterval(async () => {
            checkCount++;

            if (checkCount > maxChecks) {
                clearInterval(checkInterval);
                return;
            }

            try {
                const response = await fetch('check_payment_status.php?payment_id=<?php echo $externalReference; ?>');
                const result = await response.json();

                if (result.success && result.status === 'approved' && result.credentials) {
                    // Recarregar página para mostrar credenciais
                    window.location.reload();
                }
            } catch (error) {
                console.error('Erro ao verificar:', error);
            }
        }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>