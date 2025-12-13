<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Aprovado!</title>
    <link rel="icon" type="image/png" href="/img/favicon.png">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap');
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #5cb85c; /* Tom de verde principal */
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            max-width: 400px;
            width: 100%;
            padding: 30px;
            box-sizing: border-box;
            text-align: center;
        }
        .icon-check {
            width: 40px;
            height: 40px;
            margin-bottom: 15px;
        }
        h1 {
            color: #4CAF50;
            font-size: 1.8em;
            margin: 0 0 10px 0;
        }
        .subtitle {
            color: #666;
            margin-bottom: 25px;
        }
        .credentials-box {
            background-color: #f0f9f0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: left;
        }
        .credentials-box h2 {
            color: #333;
            font-size: 1.3em;
            margin: 0 0 15px 0;
            text-align: center;
        }
        .credential-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 1.1em;
        }
        .credential-item .label {
            color: #555;
        }
        .credential-item .value {
            color: #007bff;
            font-weight: bold;
        }
        .valid-until {
            color: #888;
            font-size: 0.9em;
            margin-top: 15px;
            text-align: center;
        }
        .login-section h3 {
            color: #444;
            font-size: 1.1em;
            margin-bottom: 15px;
        }
        .login-form input[type="text"],
        .login-form input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            margin-bottom: 10px;
            box-sizing: border-box;
            font-size: 1em;
            text-align: center;
        }
        .btn {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            box-sizing: border-box;
            transition: background-color 0.3s;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
            border: none;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
        .separator {
            background-color: #f0f0f0;
            border-radius: 10px;
            padding: 15px;
            margin-top: 25px;
            font-size: 0.9em;
            color: #666;
        }
        .btn-secondary {
            background-color: #00695c;
            color: white;
            border: none;
            margin-top: 10px;
        }
        .btn-secondary:hover {
            background-color: #004d40;
        }
    </style>
</head>
<body>
    <div class="container">
        <svg class="icon-check" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
            <path fill="#4CAF50" d="M256 0C114.6 0 0 114.6 0 256s114.6 256 256 256s256-114.6 256-256S397.4 0 256 0zm0 464c-114.7 0-208-93.3-208-208S141.3 48 256 48s208 93.3 208 208s-93.3 208-208 208z"/>
            <path fill="#4CAF50" d="M364.2 152.2c-6.2-6.2-16.4-6.2-22.6 0L224 269.4l-55.6-55.6c-6.2-6.2-16.4-6.2-22.6 0s-6.2 16.4 0 22.6l67.2 67.2c3.1 3.1 7.2 4.7 11.3 4.7s8.2-1.6 11.3-4.7l128-128c6.3-6.2 6.3-16.4 0-22.6z"/>
        </svg>

        <h1>Pagamento Aprovado!</h1>
        <p class="subtitle">Seu acesso para o plano <strong><?php echo htmlspecialchars($plan['name']); ?></strong> foi liberado.</p>

        <div class="credentials-box">
            <h2>Suas Credenciais</h2>
            <div class="credential-item">
                <span class="label">Usuário:</span>
                <span class="value"><?php echo htmlspecialchars($user['username']); ?></span>
            </div>
            <div class="credential-item">
                <span class="label">Senha:</span>
                <span class="value"><?php echo htmlspecialchars($user['password']); ?></span>
            </div>
            <p class="valid-until">Válido até: <strong><?php echo date('d/m/Y H:i', strtotime($user['expires_at'])); ?></strong></p>
        </div>

        <div class="login-section">
            <h3>Conecte-se agora:</h3>
            <form class="login-form" method="post" action="<?php echo htmlspecialchars($mikrotik['linkLogin']); ?>">
                <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                <input type="password" name="password" value="<?php echo htmlspecialchars($user['password']); ?>" readonly>
                <input type="hidden" name="dst" value="<?php echo htmlspecialchars($mikrotik['linkOrig']); ?>">
                <input type="hidden" name="popup" value="true">
                <button type="submit" class="btn btn-primary">🔐 CONECTAR AGORA</button>
            </form>
        </div>

        <div class="separator">
            <p>Se preferir, clique abaixo para voltar à tela principal e fazer o login lá:</p>
            <a href="<?php echo htmlspecialchars($mikrotik['linkLogin']); ?>" class="btn btn-secondary">Voltar para a Tela Principal de Login</a>
        </div>
    </div>
</body>
</html>
