<?php
require_once 'config.php';

// Busca o ID da transação na URL
$paymentId = $_GET['payment_id'] ?? null;
$externalReference = $_GET['external_reference'] ?? null;

$credentials = null;

if ($externalReference) {
    // Busca as credenciais criadas e salvas no hotspot_users
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
            justify-content: center;
            align-items: center;
        }

        .container {
            max-width: 600px;
            width: 100%;
        }

        .card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        .icon {
            color: #4CAF50;
            font-size: 80px;
            margin-bottom: 20px;
            /* Usando um ícone simples de check */
            content: "✓"; 
        }
        
        /* Ajuste para usar um caractere como ícone, já que não temos font-awesome */
        .icon-check:before {
            content: "✓";
            display: block;
            margin: 0 auto 20px auto;
            color: #4CAF50;
            font-size: 80px;
            line-height: 1;
        }

        h1 {
            color: #2E7D32;
            margin-bottom: 10px;
            font-size: 28px;
        }

        p {
            color: #555;
            margin-bottom: 20px;
            font-size: 16px;
        }

        .btn {
            display: inline-block;
            background: #4CAF50;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: background 0.3s;
            margin-top: 20px;
        }

        .btn:hover {
            background: #388E3C;
        }
        
        .info-text {
            background-color: #f0f0f0;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            color: #333;
            text-align: left;
        }

        /* --- Estilos para Credenciais --- */
        .credentials-box {
            background: #f1f8e9; /* Light green background */
            border: 2px solid #66bb6a;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            text-align: left;
        }
        .credentials-box h3 {
            color: #2e7d32;
            margin-bottom: 15px;
            text-align: center;
        }
        .credential-item {
            padding: 8px 0;
            border-bottom: 1px dashed #c8e6c9;
            display: flex;
            justify-content: space-between;
            font-size: 16px;
        }
        .credential-item:last-child {
            border-bottom: none;
        }
        .credential-item strong {
            color: #388e3c;
        }
        .email-confirmation {
            margin-top: 15px;
            padding: 10px;
            background-color: #e3f2fd; /* Light blue for info */
            border: 1px solid #90caf9;
            color: #1565c0;
            border-radius: 5px;
            text-align: center;
        }

    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="icon-check"></div>
            <h1>Pagamento Aprovado!</h1>
            <p>Seu acesso ao WiFi está liberado e as credenciais foram criadas com sucesso.</p>

            <?php if ($credentials): ?>
            
            <div class="credentials-box">
                <h3>🎉 Suas Credenciais de Acesso!</h3>
                <p>Use este usuário e senha para conectar ao WiFi:</p>
                <div class="credential-item">
                    <strong>Usuário:</strong> <span id="hotspot-user"><?php echo htmlspecialchars($credentials['username']); ?></span>
                </div>
                <div class="credential-item">
                    <strong>Senha:</strong> <span id="hotspot-pass"><?php echo htmlspecialchars($credentials['user_password']); ?></span>
                </div>
                <div class="credential-item">
                    <strong>Plano:</strong> <span><?php echo htmlspecialchars($credentials['plan_name']); ?></span>
                </div>
                <?php if ($credentials['expires_at']): ?>
                <div class="credential-item">
                    <strong>Expira em:</strong> <span><?php echo date('d/m/Y H:i', strtotime($credentials['expires_at'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="info-text email-confirmation">
                <strong>✉️ Email enviado!</strong><br>
                Suas credenciais também foram enviadas para o seu email. Guarde-as em local seguro!
            </div>
            
            <?php else: ?>
            <div class="info-text">
                <p><strong>Aguarde alguns instantes...</strong></p>
                <p>Seu pagamento foi aprovado, mas estamos aguardando a criação do usuário no servidor. Suas credenciais serão enviadas para seu email e aparecerão aqui em breve.</p>
            </div>
            <?php endif; ?>

            <a href="index.php" class="btn">Voltar ao Início</a>
        </div>
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
                // OBS: Este endpoint (check_payment_status.php) deve ser criado para retornar 
                // o status e as credenciais da transação pelo external_reference (ID da transação).
                const response = await fetch('check_payment_status.php?external_reference=<?php echo $externalReference; ?>');
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