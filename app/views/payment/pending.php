<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processando seu Acesso</title>
    <link rel="icon" type="image/png" href="/img/favicon.png">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #FFC107 0%, #FFA000 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        .logo {
            width: 150px;
            margin-bottom: 20px;
        }
        h1 {
            color: #FFA000;
            margin-bottom: 20px;
        }
        p {
            color: #555;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        .spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #FFA000;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="/img/wifi-barato-logo.png" alt="Logo" class="logo">
        <h1>Quase lá!</h1>
        <div class="spinner"></div>
        <p><?php echo htmlspecialchars($message ?? 'Seu pagamento foi aprovado! Estamos gerando suas credenciais. Esta página será atualizada automaticamente em instantes.'); ?></p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const transactionId = <?php echo json_encode($transactionId ?? 0); ?>;
            if (transactionId > 0) {
                const interval = setInterval(function() {
                    fetch('/payment/status?transaction_id=' + transactionId)
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'criado') {
                                clearInterval(interval);
                                window.location.reload();
                            }
                        })
                        .catch(error => {
                            console.error('Erro ao verificar status:', error);
                        });
                }, 3000); // Verifica a cada 3 segundos
            }
        });
    </script>
</body>
</html>
