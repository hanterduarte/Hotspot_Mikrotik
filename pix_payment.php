<?php
require_once 'config.php';

$paymentId = $_GET['payment_id'] ?? null;

if (!$paymentId) {
    header('Location: index.php');
    exit;
}

// Buscar informações da transação
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("
    SELECT t.*, p.name as plan_name, p.price, c.name as customer_name, c.email
    FROM transactions t
    JOIN plans p ON t.plan_id = p.id
    JOIN customers c ON t.customer_id = c.id
    WHERE t.payment_id = ?
");
$stmt->execute([$paymentId]);
$transaction = $stmt->fetch();

if (!$transaction) {
    header('Location: index.php');
    exit;
}

$gatewayResponse = json_decode($transaction['gateway_response'], true);
$qrCodeBase64 = $gatewayResponse['qr_code_base64'] ?? null;
$qrCode = $gatewayResponse['qr_code'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento PIX - WiFi Barato</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1e88e5 0%, #0d47a1 100%);
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
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #1e88e5;
            font-size: 2em;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 1.1em;
        }

        .plan-info {
            background: #f0f7ff;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 4px solid #1e88e5;
        }

        .plan-info h3 {
            color: #1e88e5;
            margin-bottom: 10px;
        }

        .plan-info p {
            color: #333;
            margin: 5px 0;
        }

        .qr-code-container {
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            background: white;
            border-radius: 10px;
            border: 2px dashed #1e88e5;
        }

        .qr-code-container img {
            max-width: 300px;
            width: 100%;
            height: auto;
        }

        .pix-code {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            word-break: break-all;
            font-family: monospace;
            font-size: 0.9em;
            position: relative;
        }

        .copy-btn {
            background: #1e88e5;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 1em;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
        }

        .copy-btn:hover {
            background: #0d47a1;
        }

        .copy-btn.copied {
            background: #4caf50;
        }

        .instructions {
            background: #fff9e6;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
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

        .status-check {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            background: #e3f2fd;
            border-radius: 10px;
        }

        .status-check p {
            color: #1e88e5;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #1e88e5;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 15px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            display: none;
            border-left: 4px solid #28a745;
        }

        .success-message.active {
            display: block;
        }

        .success-message h3 {
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 25px;
            }

            .header h1 {
                font-size: 1.5em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💳 Pagamento via PIX</h1>
            <p>Escaneie o QR Code ou copie o código</p>
        </div>

        <div class="plan-info">
            <h3>Resumo do Pedido</h3>
            <p><strong>Plano:</strong> <?php echo htmlspecialchars($transaction['plan_name']); ?></p>
            <p><strong>Valor:</strong> <?php echo formatMoney($transaction['price']); ?></p>
            <p><strong>Cliente:</strong> <?php echo htmlspecialchars($transaction['customer_name']); ?></p>
        </div>

        <?php if ($qrCodeBase64): ?>
        <div class="qr-code-container">
            <h4 style="color: #1e88e5; margin-bottom: 15px;">📱 Escaneie o QR Code</h4>
            <img src="data:image/png;base64,<?php echo $qrCodeBase64; ?>" alt="QR Code PIX">
        </div>
        <?php endif; ?>

        <?php if ($qrCode): ?>
        <div>
            <h4 style="color: #333; margin-bottom: 10px;">Ou copie o código PIX:</h4>
            <div class="pix-code" id="pixCode"><?php echo htmlspecialchars($qrCode); ?></div>
            <button class="copy-btn" onclick="copyPixCode()">📋 Copiar Código PIX</button>
        </div>
        <?php endif; ?>

        <div class="instructions">
            <h4>📝 Como pagar:</h4>
            <ol>
                <li>Abra o app do seu banco</li>
                <li>Escolha a opção "Pagar com PIX"</li>
                <li>Escaneie o QR Code ou cole o código copiado</li>
                <li>Confirme o pagamento</li>
                <li>Aguarde a confirmação (geralmente imediata)</li>
            </ol>
        </div>

        <div class="status-check">
            <p>🔄 Aguardando confirmação do pagamento...</p>
            <div class="loading-spinner"></div>
            <p style="font-size: 0.9em; color: #666; margin-top: 10px;">
                Atualizando automaticamente a cada 5 segundos
            </p>
        </div>

        <div class="success-message" id="successMessage">
            <h3>✅ Pagamento Confirmado!</h3>
            <p id="credentialsInfo"></p>
        </div>
    </div>

    <script>
        const paymentId = '<?php echo $paymentId; ?>';
        let checkInterval;

        function copyPixCode() {
            const pixCode = document.getElementById('pixCode').textContent;
            const btn = event.target;

            navigator.clipboard.writeText(pixCode).then(() => {
                btn.textContent = '✅ Código Copiado!';
                btn.classList.add('copied');
                
                setTimeout(() => {
                    btn.textContent = '📋 Copiar Código PIX';
                    btn.classList.remove('copied');
                }, 3000);
            }).catch(err => {
                alert('Erro ao copiar código');
            });
        }

        async function checkPaymentStatus() {
            try {
                const response = await fetch('check_payment_status.php?payment_id=' + paymentId);
                const result = await response.json();

                if (result.success && result.status === 'approved') {
                    clearInterval(checkInterval);
                    showSuccess(result.credentials);
                }
            } catch (error) {
                console.error('Erro ao verificar status:', error);
            }
        }

        function showSuccess(credentials) {
            document.querySelector('.status-check').style.display = 'none';
            const successMsg = document.getElementById('successMessage');
            const credInfo = document.getElementById('credentialsInfo');
            
            credInfo.innerHTML = `
                <p><strong>Suas credenciais foram criadas!</strong></p>
                <p style="margin-top: 10px;">
                    <strong>Usuário:</strong> ${credentials.username}<br>
                    <strong>Senha:</strong> ${credentials.password}
                </p>
                <p style="margin-top: 15px;">
                    As credenciais também foram enviadas para o seu email.<br>
                    Você já pode se conectar ao WiFi!
                </p>
            `;
            successMsg.classList.add('active');

            // Redirecionar após 10 segundos
            setTimeout(() => {
                window.location.href = 'success.php';
            }, 10000);
        }

        // Verificar status a cada 5 segundos
        checkInterval = setInterval(checkPaymentStatus, 5000);

        // Verificar imediatamente ao carregar
        checkPaymentStatus();
    </script>
</body>
</html>