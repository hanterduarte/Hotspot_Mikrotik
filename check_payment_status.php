<?php
require_once 'config.php';
$transactionId = $_GET['transaction_id'] ?? null;

if (!$transactionId) {
    die("ID da transação não fornecido.");
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificando Pagamento...</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1e88e5 0%, #0d47a1 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            text-align: center;
        }
        .container {
            max-width: 500px;
            padding: 40px;
        }
        .spinner {
            border: 8px solid rgba(255, 255, 255, 0.2);
            border-top: 8px solid white;
            border-radius: 50%;
            width: 80px;
            height: 80px;
            animation: spin 1s linear infinite;
            margin: 0 auto 30px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        h1 { font-size: 2em; margin-bottom: 20px; }
        p { font-size: 1.1em; opacity: 0.9; }
    </style>
</head>
<body>
    <div class="container">
        <div class="spinner"></div>
        <h1>Aguarde, estamos confirmando seu pagamento...</h1>
        <p>Isso pode levar alguns segundos. Por favor, não feche esta página.</p>
    </div>

    <script>
        const transactionId = '<?php echo $transactionId; ?>';
        let checkCount = 0;
        const maxChecks = 15; // Tentar por até 45 segundos

        async function checkStatus() {
            checkCount++;
            if (checkCount > maxChecks) {
                // Se exceder o tempo, redirecionar para a página de falha
                window.location.href = `payment_failure.php?transaction_id=${transactionId}`;
                return;
            }

            try {
                // Usaremos um endpoint de API para verificar o status
                const response = await fetch(`api_check_status.php?transaction_id=${transactionId}`);
                const result = await response.json();

                if (result.success && result.status === 'approved') {
                    // Se aprovado, redirecionar para a página de sucesso
                    window.location.href = `payment_success.php?transaction_id=${transactionId}`;
                } else {
                    // Se não, tentar novamente em 3 segundos
                    setTimeout(checkStatus, 3000);
                }
            } catch (error) {
                console.error('Erro ao verificar status:', error);
                // Tentar novamente em caso de erro de rede
                setTimeout(checkStatus, 3000);
            }
        }

        // Iniciar a verificação
        setTimeout(checkStatus, 2000);
    </script>
</body>
</html>
