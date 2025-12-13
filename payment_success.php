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
// ---------------------------------------------------

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
                c.email as customer_email   /* 🟢 NOVO: Email do Cliente */
            FROM transactions t
            LEFT JOIN hotspot_users hu ON t.id = hu.transaction_id
            LEFT JOIN plans p ON t.plan_id = p.id
            LEFT JOIN customers c ON t.customer_id = c.id /* 🟢 NOVO: JOIN com a tabela customers */
            WHERE t.id = ?
            ORDER BY hu.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$externalReference]);
        $result = $stmt->fetch();

        if ($result) {
            $transactionStatus = strtolower($result['payment_status']);
            
            // Corrige o nome do plano se o plan_id estiver correto no DB
            $planName = $result['plan_name'] ?? $planName; 
            $expiresAt = $result['expires_at']; 
            
            // ATRIBUIÇÃO DO EMAIL
            $customerEmail = $result['customer_email'] ?? $customerEmail;
            
            // ATRIBUIÇÃO DAS VARIÁVEIS DO MIKROTIK DO BANCO DE DADOS
            $linkLoginOnly = $result['mikrotik_link_login_only'] ?? '';
            $linkOrig = $result['mikrotik_link_orig'] ?? '';
            $chapId = $result['mikrotik_chap_id'] ?? '';
            $chapChallenge = $result['mikrotik_chap_challenge'] ?? '';
            
            // CONDIÇÃO DE SUCESSO: Aprovado/Pago E o Usuário Hotspot foi criado (username não vazio)
            if (($transactionStatus === 'approved' || $transactionStatus === 'paid' || $transactionStatus === 'success') && !empty($result['username'])) {
                $credentials = $result;
            }
        }
        
    } catch (Exception $e) {
        // logEvent('payment_success_db_error', $e->getMessage(), $externalReference); 
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
            border-top: none; 
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
        
        /* NOVO: Container de Opções Secundárias (fundo verde claro) */
        .secondary-options {
            background-color: #f0fff0; /* Verde bem claro */
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 8px;
            margin-top: 25px; 
            text-align: center;
        }

        .secondary-info {
            font-size: 0.9em;
            color: #5a5a5a;
            margin-bottom: 10px;
        }
        
        /* NOVO: Botão de Redirecionamento (Melhor Contraste - Verde Escuro) */
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
            <p>Seu acesso para o **<?php echo htmlspecialchars($planName ?? 'Plano Hotspot'); ?>** foi liberado.</p>
            
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
            <p style="margin-top: 20px; font-size: 0.9em; color: #555;">**Nota:** Você deve retornar para a tela de login principal e utilizar suas credenciais de acesso no campo de Usuáario e Senha.</p>
            <?php endif; ?>
            
            <div class="secondary-options">
                <p class="secondary-info">
                    Se preferir, clique abaixo para voltar à tela principal e fazer o login lá:
                </p>
                <a href="index.php" class="redirect-button">Voltar para a Tela Principal de Login</a>
            </div>
            
            
        <?php else: ?>
            <div class="icon-success">⏳</div>
            <h1>Aguardando Confirmação</h1>
            <p>Seu pagamento foi aprovado, mas estamos aguardando a criação do seu usuário.</p>
            <div class="waiting-message">Aguardando credenciais do Hotspot...</div>
            <p style="font-size: 0.9em; color: #555; margin-top: 15px;">Aguarde, esta página será atualizada automaticamente.</p>
            
        <?php endif; ?>
    </div>

    <script>
        // 🟢 AJUSTE: Aumentando maxChecks para 50 (50 verificações * 3 segundos = 150 segundos)
        const maxChecks = 50;
        const transactionId = '<?php echo $externalReference; ?>';
        // 🟢 NOVO: Captura o email para a mensagem de fallback
        const customerEmail = '<?php echo htmlspecialchars($customerEmail); ?>';
        const container = document.querySelector('.container'); // Referência ao container para alteração

        <?php if (!$credentials && $externalReference): ?>
        let checkCount = 0;

        const checkInterval = setInterval(async () => {
            checkCount++;
            
            // 🟢 TRATAMENTO DE TEMPO LIMITE
            if (checkCount > maxChecks) {
                clearInterval(checkInterval);
                console.error('Tempo limite de verificação excedido (90 segundos).');
                
                
                // 1. Altera o conteúdo do container para a mensagem de fallback por email
                container.innerHTML = `
                    <div class="icon-success" style="color: #ff9800;">⚠️</div>
                    <h1 style="color: #ff9800;">Acesso em Processamento</h1>
                    <p style="text-align: center; margin-top: 15px;">
                        O pagamento foi confirmado, mas a criação de usuário no Mikrotik pode estar demorando.
                        <br><br>
                        <strong>Suas credenciais foram enviadas para o email cadastrado: ${customerEmail}.</strong>
                        <br>
                        Por favor, verifique sua caixa de entrada e spam.
                    </p>
                    <div class="secondary-options">
                        <p class="secondary-info">
                            Se preferir, clique abaixo para tentar logar na tela principal:
                        </p>
                        <a href="index.php" class="redirect-button">Voltar para a Tela Principal de Login</a>
                    </div>
                `;
                
                return; // Encerra a função após exibir o fallback
            }

            try {
                // Chama o endpoint para verificar se o Webhook já criou as credenciais
                const response = await fetch(`check_payment_status.php?payment_id=${transactionId}`);
                const result = await response.json();
                
                // 🟢 AJUSTE DE VERIFICAÇÃO DE CREDENCIAIS (Para suportar estrutura plana ou aninhada em 'data')
                // 1. Tenta pegar as credenciais diretamente (result.credentials)
                let credentialsReady = result.credentials;
                
                // 2. Se não encontrou, tenta pegar em result.data.credentials
                if (!credentialsReady && result.data) {
                    credentialsReady = result.data.credentials;
                }
                
                const isApprovedStatus = (result.status === 'approved' || result.status === 'paid' || result.status === 'success');
                // Se o status estiver em 'data' (mais comum em APIs)
                const isApprovedStatusInScope = (result.data && (result.data.status === 'approved' || result.data.status === 'paid' || result.data.status === 'success'));


                if (result.success && (isApprovedStatus || isApprovedStatusInScope) && credentialsReady) {
                    clearInterval(checkInterval);
                    // Recarregar a página para buscar as novas credenciais E as variáveis do Mikrotik do DB
                    window.location.reload(); 
                }
            } catch (error) {
                console.error('Erro ao verificar:', error);
            }
        }, 3000); // Verifica a cada 3 segundos
        <?php endif; ?>
    </script>
</body>
</html>