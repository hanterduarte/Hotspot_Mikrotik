<?php
require_once 'config.php';

// Captura variáveis da Transação
$paymentId = $_GET['payment_id'] ?? null;
$externalReference = $_GET['external_reference'] ?? $paymentId;

// --- Variáveis do Mikrotik (serão buscadas do DB) ---
$linkLoginOnly = '';
$linkOrig = '';
$chapId = '';
$chapChallenge = '';

$credentials = null;
$transactionStatus = null;
$planName = "Seu Plano";
$expiresAt = null;
$customerEmail = 'verifique seu email'; // Valor padrão

if ($externalReference) {
    // Tenta obter o usuário criado pelo Webhook
    try {
        $db = Database::getInstance()->getConnection();
        
        // Consulta que busca credenciais, status, variáveis do Mikrotik E o email do cliente (JOIN customers)
        $stmt = $db->prepare("
            SELECT 
                hu.username, 
                hu.password as user_password, 
                p.name as plan_name, 
                hu.expires_at,      
                t.payment_status,
                t.mikrotik_link_login_only, 
                t.mikrotik_link_orig,       
                t.mikrotik_chap_id,         
                t.mikrotik_chap_challenge,
                c.email as customer_email
            FROM transactions t
            LEFT JOIN hotspot_users hu ON t.id = hu.transaction_id
            LEFT JOIN plans p ON t.plan_id = p.id
            LEFT JOIN customers c ON t.customer_id = c.id
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
            $customerEmail = $result['customer_email'] ?? $customerEmail;
            
            // Atribui as variáveis do Mikrotik buscadas do DB
            $linkLoginOnly = $result['mikrotik_link_login_only'] ?? '';
            $linkOrig = $result['mikrotik_link_orig'] ?? '';
            $chapId = $result['mikrotik_chap_id'] ?? '';
            $chapChallenge = $result['mikrotik_chap_challenge'] ?? '';

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
        /* ESTILOS BASE (MANTIDOS) */
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
            margin-bottom: 20px; 
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
        
        /* 🚨 NOVO: Estilos do Container de Retorno (Usando .return-container) */
        .return-container {
            /* Estilos de .secondary-options */
            background-color: #f0fff0; /* Verde bem claro */
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 8px;
            margin-top: 25px; 
            text-align: center;
            box-shadow: none; /* Garante a sutileza */
        }

        /* 🚨 NOVO: Estilos da Mensagem de Informação (Usando p dentro de .return-container) */
        .return-container p {
            /* Estilos de .secondary-info */
            font-size: 0.9em;
            color: #5a5a5a;
            margin-bottom: 10px;
            line-height: 1.4; /* Mantido para melhor leitura */
        }
        
        /* 🚨 NOVO: Estilo para o Botão de Redirecionamento (Melhor Contraste - Verde Escuro) */
        .redirect-button {
            background-color: #00796b; 
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 0.9em;
            margin-top: 5px;
            display: block;
            text-decoration: none; 
            line-height: 1.5;
            transition: background-color 0.3s;
        }
        
        .redirect-button:hover {
            background-color: #004d40;
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
                    <input type="hidden" name="chap-id" value="<?php echo htmlspecialchars($chapId); ?>">
                    <input type="hidden" name="chap-challenge" value="<?php echo htmlspecialchars($chapChallenge); ?>">
                    
                    <input type="text" name="username" placeholder="Usuário" required value="<?php echo htmlspecialchars($credentials['username']); ?>">
                    <input type="password" name="password" placeholder="Senha" required value="<?php echo htmlspecialchars($credentials['user_password']); ?>">
                    
                    <button type="submit" name="login">🔓 CONECTAR AGORA</button>
                </form>
            </div>
            <?php else: ?>
            <p style="margin-top: 20px; font-size: 0.9em; color: #555;">**Nota:** Não foi possível carregar as variáveis de login do Hotspot.</p>
            <?php endif; ?>
            
            <div class="return-container">
                <p>Se preferir, clique abaixo para voltar à tela principal e fazer o login lá:</p>
                <a href="index.php" class="redirect-button">Voltar para a Tela Principal de Login</a>
            </div>
            
        <?php else: ?>
            <div class="icon-success">⏳</div>
            <h1 id="pollingStatusTitle">Aguardando Confirmação</h1>
            <p id="pollingStatusMessage">Seu pagamento foi aprovado, mas estamos aguardando a criação do seu usuário.</p>
            <div class="waiting-message">Aguardando credenciais do Hotspot...</div>
            <p style="font-size: 0.9em; color: #555; margin-top: 15px;">Aguarde, esta página será atualizada automaticamente.</p>
            
        <?php endif; ?>
    </div>

    <script>
        const maxChecks = 20; 
        const transactionId = '<?php echo $externalReference; ?>';
        const customerEmail = '<?php echo htmlspecialchars($customerEmail); ?>';
        const titleElement = document.getElementById('pollingStatusTitle');
        const messageElement = document.getElementById('pollingStatusMessage');

        <?php if (!$credentials && $externalReference): ?>
        let checkCount = 0;

        const checkInterval = setInterval(async () => {
            checkCount++;
            
            if (checkCount > maxChecks) {
                clearInterval(checkInterval);
                console.error('Tempo limite de verificação excedido.');
                
                if (titleElement) titleElement.textContent = 'Tempo Excedido';
                if (messageElement) {
                     messageElement.innerHTML = `
                        O sistema demorou a responder. Tente recarregar ou verifique o email <strong>${customerEmail}</strong>.
                    `;
                } else {
                     document.querySelector('.container p').innerHTML = `
                        O sistema demorou a responder. Tente recarregar ou verifique o email <strong>${customerEmail}</strong>.
                    `;
                }

                return;
            }

            try {
                // Chama o endpoint para verificar se o Webhook já criou as credenciais
                const response = await fetch(`check_payment_status.php?payment_id=${transactionId}`);
                const result = await response.json();

                // 🚨 Atualiza a mensagem se o status mudar para aprovado, mas ainda sem credenciais
                if (titleElement && result.status && (result.status === 'approved' || result.status === 'paid' || result.status === 'success')) {
                    titleElement.textContent = 'Pagamento Aprovado. Gerando Acesso...';
                    if(messageElement) messageElement.textContent = 'Aguardando credenciais do Hotspot...';
                }

                // Recarrega se APROVADO E CREDENCIAIS EXISTIREM
                if (result.success && (result.status === 'approved' || result.status === 'paid' || result.status === 'success') && result.credentials) {
                    clearInterval(checkInterval);
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