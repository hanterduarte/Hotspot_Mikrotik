<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Falha no Pagamento</title>
    <link rel="icon" type="image/png" href="/img/favicon.png">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #F44336 0%, #C62828 100%);
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
            color: #C62828;
            margin-bottom: 20px;
        }
        p {
            color: #555;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        .home-btn {
            background: #1e88e5;
            color: white;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            transition: background 0.3s;
        }
        .home-btn:hover {
            background: #1565c0;
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="/img/wifi-barato-logo.png" alt="Logo" class="logo">
        <h1>Ocorreu um Erro</h1>
        <p><?php echo htmlspecialchars($message ?? 'Não foi possível processar sua solicitação. Por favor, tente novamente ou entre em contato com o suporte.'); ?></p>
        <a href="/" class="home-btn">Voltar para a Página Inicial</a>
    </div>
</body>
</html>
