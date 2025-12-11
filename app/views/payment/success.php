<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Aprovado - Suas Credenciais</title>
    <link rel="icon" type="image/png" href="/img/favicon.png">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
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
            color: #2E7D32;
            margin-bottom: 20px;
        }
        p {
            color: #555;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        .credentials-box {
            background: #f4f4f4;
            border: 2px dashed #4CAF50;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .credentials-box strong {
            display: block;
            font-size: 1.2em;
            color: #333;
            margin-bottom: 10px;
        }
        .credentials-box .value {
            font-size: 1.5em;
            color: #2E7D32;
            font-weight: bold;
            letter-spacing: 1px;
            padding: 10px;
            background: #e8f5e9;
            border-radius: 5px;
        }
        .login-form input {
            width: calc(100% - 24px);
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
        }
        .login-btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 15px;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            transition: background 0.3s;
        }
        .login-btn:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="/img/wifi-barato-logo.png" alt="Logo" class="logo">
        <h1>Pagamento Aprovado!</h1>
        <p>Seu acesso à internet foi liberado. Use as credenciais abaixo para se conectar. Também enviamos uma cópia para o seu e-mail.</p>

        <div class="credentials-box">
            <div>
                <strong>Usuário:</strong>
                <span class="value"><?php echo htmlspecialchars($user['username']); ?></span>
            </div>
            <hr style="margin: 15px 0; border: 1px solid #eee;">
            <div>
                <strong>Senha:</strong>
                <span class="value"><?php echo htmlspecialchars($user['password']); ?></span>
            </div>
        </div>

        <!-- Formulário de Login para o Hotspot -->
        <form class="login-form" method="post" action="<?php echo htmlspecialchars($_GET['link-login-only'] ?? '#'); ?>">
            <input type="hidden" name="username" value="<?php echo htmlspecialchars($user['username']); ?>">
            <input type="hidden" name="password" value="<?php echo htmlspecialchars($user['password']); ?>">
            <input type="hidden" name="dst" value="<?php echo htmlspecialchars($_GET['link-orig'] ?? ''); ?>">
            <input type="hidden" name="popup" value="true">
            <button type="submit" class="login-btn">Conectar Agora</button>
        </form>
    </div>
</body>
</html>
