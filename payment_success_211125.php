<?php
require_once 'config.php';

// Captura variáveis da Transação
$paymentId = $_GET['payment_id'] ?? null;
$externalReference = $_GET['external_reference'] ?? $paymentId;

// Captura variáveis ESSENCIAIS do Mikrotik (se estiverem na URL)
$linkLogin = isset($_GET['link-login-only']) ? $_GET['link-login-only'] : '';
$linkOrig = isset($_GET['link-orig']) ? $_GET['link-orig'] : '';
$chapId = isset($_GET['chap-id']) ? $_GET['chap-id'] : '';
$chapChallenge = isset($_GET['chap-challenge']) ? $_GET['chap-challenge'] : '';
$linkLoginOnly = $linkLogin; // Usaremos essa no action do formulário

$credentials = null;
$transactionStatus = null;
$planName = "Seu Plano";
$expiresAt = null;

if ($externalReference) {
    // Tenta obter o usuário criado pelo Webhook
    try {
        $db = Database::getInstance()->getConnection();
        // Consulta que busca as credenciais e a data de expiração (expires_at)
        $stmt = $db->prepare("
            SELECT 
                hu.username, 
                hu.password as user_password, 
                p.name as plan_name, 
                hu.expires_at,      
                t.payment_status
            FROM transactions t
            LEFT JOIN hotspot_users hu ON t.id = hu.transaction_id
            LEFT JOIN plans p ON t.plan_id = p.id
            WHERE t.id = ?
            ORDER BY hu.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$externalReference]);
        $result = $stmt->fetch();

        if ($result) {
            $transactionStatus = strtolower($result['payment_status']);
            $planName = $result['plan_name'] ?? $planName;
            $expiresAt = $result['expires_at']; 
            
            if (($transactionStatus === 'approved' || $transactionStatus === 'paid' || $transactionStatus === 'success') && !empty($result['username'])) {
                $credentials = $result;
            }
        }
        
    } catch (Exception $e) {
        logEvent('payment_success_db_error', $e->getMessage(), $externalReference);
        $credentials = null; 
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
        /* MANTENDO O FUNDO VERDE SEMPRE QUE HÁ UMA TRANSAÇÃO EM ANDAMENTO */
        body {
            background-color: #4CAF50 !important;
            background-image: linear-gradient(to bottom right, #4CAF50, #8BC34A) !important;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #333;
            transition: background-color 0.5s ease;
        }

        .container {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
            text-align: center;
        }

        h1 {
            color: <?php echo $credentials ? '#28a745' : '#ffc107'; ?>; 
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        h2 {
            font-size: 1.5em;
            color: #343a40;
            margin-top: 20px;
            margin-bottom: 15px;
        }

        .icon-success {
            font-size: 4em;
            color: <?php echo $credentials ? '#28a745' : '#ffc107'; ?>; 
            margin-bottom: 20px;
        }
        
        .credentials {
            background-color: #e9f7ef;
            border: 1px solid #c3e6cb;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            margin-bottom: 20px; /* Adicionado margem inferior */
            text-align: left;
        }

        .credentials p {
            margin: 8px 0;
            font-size: 1.1em;
        }

        .credentials strong {
            display: inline-block;
            min-width: 100px;
            color: #555;
        }

        .username-value, .password-value {
            font-weight: bold;
            color: #007bff;
        }
        
        .waiting-message {
            color: #ffc107;
            font-size: 1.1em;
            font-weight: bold;
            margin-top: 20px;
        }
        
        .expires {
            font-size: 0.9em;
            color: #6c757d;
            margin-top: 15px;
        }
        
        /* Estilos do Formulário de Login */
        .login-form {
            text-align: center;
            margin-top: 20px;
            padding: 15px;
            border-top: 1px solid #eee;
        }
        
        .login-form input[type="text"],
        .login-form input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        
        .login-form button {
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 1em;
            margin-top: 5px;
        }
        
        .redirect-button {
            background-color: #6c757d;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 0.9em;
            margin-top: 15px;
            display: block;
            text-decoration: none; /* Para usar no <a> */
            line-height: 1.5;
        }

    </style>
</head>
<body>
    <div class="container">
        <?php if ($credentials): ?>
            <div class="icon-success">✔️</div>
            <h1>Pagamento Aprovado!</h1>
            <p>Seu acesso para o **<?php echo htmlspecialchars($planName); ?>** foi liberado.</p>
            
            <div class="credentials">
                <h2>Suas Credenciais</h2>
                <p>
                    <strong>Usuário:</strong> 
                    <span class="username-value"><?php echo htmlspecialchars($credentials['username']); ?></span>
                </p>
                <p>
                    <strong>Senha:</strong> 
                    <span class="password-value"><?php echo htmlspecialchars($credentials['user_password']); ?></span>
                </p>
            </div>
            
            <?php if ($expiresAt): ?>
            <p class="expires">Válido até: **<?php echo date('d/m/Y H:i', strtotime($expiresAt)); ?>**</p>
            <?php endif; ?>
            
            <?php if (!empty($linkLoginOnly)): ?>
            <div class="login-form">
                <p style="margin-bottom: 10px; font-weight: bold; color: #343a40;">Conecte-se agora:</p>
                
                <form method="post" action="<?php echo htmlspecialchars($linkLoginOnly); ?>">
                    <input type="hidden" name="dst" value="<?php echo htmlspecialchars($linkOrig); ?>">
                    <input type="hidden" name="popup" value="false">
                    <input type="hidden" name="username" value="">
                    <input type="hidden" name="password" value="">
                    
                    <input type="text" name="username" placeholder="Usuário" required value="<?php echo htmlspecialchars($credentials['username']); ?>">
                    <input type="password" name="password" placeholder="Senha" required value="<?php echo htmlspecialchars($credentials['user_password']); ?>">
                    
                    <button type="submit" name="login">🔓 CONECTAR AGORA</button>
                </form>
            </div>
            <?php else: ?>
            <p style="margin-top: 20px; font-size: 0.9em; color: #555;">**Nota:** As variáveis de login do Hotspot não foram detectadas na URL. Use o botão abaixo para ir à página de login.</p>
            <?php endif; ?>
            
            <a href="index.php" class="redirect-button">Voltar para a Tela Principal de Login</a>
            
            
        <?php else: ?>
            <div class="icon-success">⏳</div>
            <h1>Aguardando Confirmação</h1>
            <p>Seu pagamento foi aprovado, mas estamos aguardando a criação do seu usuário.</p>
            <div class="waiting-message">Aguardando credenciais do Hotspot...</div>
            <p style="font-size: 0.9em; color: #555; margin-top: 15px;">Aguarde, esta página será atualizada automaticamente.</p>
            
        <?php endif; ?>
    </div>

    <script>
        const maxChecks = 20; 
        const transactionId = '<?php echo $externalReference; ?>';

        <?php if (!$credentials && $externalReference): ?>
        let checkCount = 0;

        const checkInterval = setInterval(async () => {
            checkCount++;
            
            if (checkCount > maxChecks) {
                clearInterval(checkInterval);
                console.error('Tempo limite de verificação excedido.');
                document.querySelector('.container h1').textContent = 'Tempo Excedido';
                document.querySelector('.container p').textContent = 'O sistema demorou a responder. Tente recarregar ou contate o suporte.';
                return;
            }

            try {
                const response = await fetch(`check_payment_status.php?payment_id=${transactionId}`);
                const result = await response.json();

                if (result.success && (result.status === 'approved' || result.status === 'paid') && result.credentials) {
                    clearInterval(checkInterval);
                    // Recarregar a página passando as variáveis do Mikrotik que já existiam na URL
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