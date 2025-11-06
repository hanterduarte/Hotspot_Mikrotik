<?php
require_once 'config.php';

// Buscar planos ativos
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT * FROM plans WHERE active = 1 ORDER BY price ASC");
$plans = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WiFi Barato - Escolha seu Plano</title>
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
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-top: 20px;
        }

        .logo {
            width: 180px;
            height: auto;
            margin-bottom: 20px;
        }

        .intro-box {
            background: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }

        .intro-box h2 {
            color: #1e88e5;
            font-size: 1.8em;
            margin-bottom: 15px;
            text-align: center;
        }

        .intro-box p {
            color: #555;
            line-height: 1.8;
            font-size: 1.05em;
            text-align: justify;
            margin-bottom: 12px;
        }

        .login-box {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            margin-bottom: 40px;
        }

        .login-box h2 {
            color: #1e88e5;
            margin-bottom: 10px;
            text-align: center;
            font-size: 1.8em;
        }

        .login-box > p {
            color: #666;
            text-align: center;
            margin-bottom: 25px;
        }

        .info-message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .info-message.alert {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .login-box .form-group { 
            margin-bottom: 20px;
            position: relative;
        }

        .login-box .form-group input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .login-box .form-group input:focus {
            outline: none;
            border-color: #1e88e5;
        }

        .login-box .form-group .ico { 
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            opacity: 0.5;
        }

        .submit-btn {
            background: linear-gradient(135deg, #1e88e5 0%, #0d47a1 100%);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-size: 1.2em;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s ease;
            text-transform: uppercase;
        }

        .submit-btn:hover {
            transform: scale(1.02);
        }

        .plans-section {
            margin: 40px 0;
        }

        .plans-title {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }

        .plans-title h3 {
            font-size: 2em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .plan-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .plan-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        }

        .plan-card.selected {
            border: 3px solid #1e88e5;
            transform: scale(1.05);
        }

        .plan-duration {
            font-size: 1.8em;
            color: #1e88e5;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .plan-price {
            font-size: 3em;
            color: #333;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .plan-features {
            list-style: none;
            margin: 20px 0;
            padding: 0;
        }

        .plan-features li {
            padding: 10px 0;
            color: #666;
            border-bottom: 1px solid #eee;
            font-size: 0.95em;
        }

        .select-btn {
            background: linear-gradient(135deg, #1e88e5 0%, #0d47a1 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 50px;
            font-size: 1em;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            margin-top: 15px;
        }

        .form-box {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            display: none;
            max-width: 600px;
            margin: 0 auto 30px;
        }

        .form-box.active {
            display: block;
        }

        .form-box h2 {
            color: #1e88e5;
            margin-bottom: 25px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #1e88e5;
        }

        .selected-plan-info {
            background: #f0f7ff;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid #1e88e5;
        }

        .selected-plan-info h4 {
            color: #1e88e5;
            margin-bottom: 10px;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 25px;
        }

        .payment-method {
            background: #f5f5f5;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .payment-method:hover {
            background: #e3f2fd;
            border-color: #1e88e5;
        }

        .payment-method.selected {
            background: #e3f2fd;
            border-color: #1e88e5;
        }

        .payment-method svg {
            width: 40px;
            height: 40px;
            margin-bottom: 10px;
        }

        .submit-payment-btn {
            background: linear-gradient(135deg, #1e88e5 0%, #0d47a1 100%);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-size: 1.2em;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
        }

        .submit-payment-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .back-btn {
            background: #e0e0e0;
            color: #333;
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-size: 1em;
            cursor: pointer;
            width: 100%;
            margin-top: 15px;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading.active {
            display: block;
        }

        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #1e88e5;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .error-message {
            background: #fee;
            color: #c33;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
        }

        .error-message.active {
            display: block;
        }

        .info-section {
            background: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }

        .info-section h3 {
            color: #1e88e5;
            font-size: 1.6em;
            margin-bottom: 20px;
            text-align: center;
        }

        .info-section h4 {
            color: #333;
            font-size: 1.3em;
            margin-bottom: 12px;
        }

        .info-section p {
            color: #555;
            line-height: 1.8;
            font-size: 1.05em;
            text-align: justify;
            margin-bottom: 15px;
        }

        .contact-section {
            background: linear-gradient(135deg, #1e88e5 0%, #0d47a1 100%);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            margin-bottom: 30px;
            color: white;
        }

        .contact-section h3 {
            font-size: 1.8em;
            margin-bottom: 10px;
            text-align: center;
        }

        .contact-section h4 {
            font-size: 1.3em;
            margin-bottom: 20px;
            text-align: center;
            font-weight: normal;
            opacity: 0.95;
        }

        .contact-section p {
            line-height: 1.8;
            font-size: 1.05em;
            text-align: center;
            margin-bottom: 25px;
        }

        .contact-info {
            background: rgba(255,255,255,0.15);
            border-radius: 15px;
            padding: 25px;
            margin-top: 20px;
        }

        .contact-info h5 {
            font-size: 1.2em;
            margin-bottom: 15px;
            text-align: center;
        }

        .contact-item {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 12px 0;
            font-size: 1.1em;
        }

        .contact-item svg {
            width: 24px;
            height: 24px;
            margin-right: 10px;
            flex-shrink: 0;
        }

        .contact-item a {
            color: white;
            text-decoration: none;
            border-bottom: 1px solid rgba(255,255,255,0.3);
            transition: border-color 0.3s ease;
        }

        .contact-item a:hover {
            border-bottom-color: white;
        }

        .footer {
            text-align: center;
            color: white;
            margin: 40px 0 20px 0;
            opacity: 0.8;
            font-size: 0.9em;
        }

        @media (max-width: 768px) {
            .plans-grid {
                grid-template-columns: 1fr;
            }
            .payment-methods {
                grid-template-columns: 1fr;
            }
            .contact-item {
                flex-direction: column;
                text-align: center;
            }
            .contact-item svg {
                margin-right: 0;
                margin-bottom: 8px;
            }
        }
    </style>
</head>
<body>
    
    $(if chap-id)
    <form name="sendin" action="$(link-login-only)" method="post" style="display:none">
        <input type="hidden" name="username" />
        <input type="hidden" name="password" />
        <input type="hidden" name="dst" value="$(link-orig)" />
        <input type="hidden" name="popup" value="true" />
    </form>

 
    <script>
        function doLogin() {
            document.sendin.username.value = document.login.username.value;
            document.sendin.password.value = hexMD5('$(chap-id)' + document.login.password.value + '$(chap-challenge)');
            document.sendin.submit();
            return false;
        }
    </script>
    $(endif)
    <div class="container">
        <div class="header">
            <img src="img/wifi-barato-logo.png" alt="WiFi Barato" class="logo"> 
        </div>

        <div class="intro-box">
            <h2>Olá! Acesso à internet rápido e barato para você.</h2>
            <p>Nossa missão é democratizar o acesso à internet de alta velocidade. Oferecemos uma conexão ágil e de baixo custo, ideal para quem precisa navegar, trabalhar ou se divertir online, sem a burocracia das operadoras tradicionais.</p>
            <p>Conecte-se com planos de curta duração ou escolha nosso plano mensal, ideal para quem busca uma solução completa sem a necessidade de contratos de fidelidade.</p>
        </div>

        <div class="login-box">
            <h2>Acesse a Internet</h2>
            <p>Digite seu usuário e senha para conectar</p>

            $(if error)
            <div class="info-message alert">$(error)</div>
            $(endif)

            <form name="login" action="$(link-login-only)" method="post" $(if chap-id) onSubmit="return doLogin()" $(endif)>
                <input type="hidden" name="dst" value="$(link-orig)" />
                <input type="hidden" name="popup" value="true" />
                
                <div class="form-group">
                    <svg class="ico" fill="#666" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                    <input name="username" type="text" value="$(username)" placeholder="Usuário" required autofocus />
                </div>

                <div class="form-group">
                    <svg class="ico" fill="#666" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                    </svg>
                    <input name="password" type="password" placeholder="Senha" required />
                </div>

                <button type="submit" class="submit-btn">Conectar Agora</button>
            </form>
        </div>
        
        <div id="plansSection" class="plans-section">
            <div class="plans-title">
                <h3>💰 Escolha seu Plano</h3>
                <p>Selecione o melhor plano para você</p>
            </div>

            <div class="plans-grid">
                <?php 
                foreach ($plans as $plan): 
                ?>
                <div class="plan-card" onclick="selectPlan(<?php echo $plan['id']; ?>)" data-plan-id="<?php echo $plan['id']; ?>">
                    <div class="plan-duration"><?php echo htmlspecialchars($plan['name']); ?></div>
                    <div class="plan-price"><?php echo formatMoney($plan['price']); ?></div>
                    <ul class="plan-features">
                        <li><?php echo htmlspecialchars($plan['description']); ?></li>
                    </ul>
                    <button class="select-btn">Escolher este Plano</button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div id="formSection" class="form-box">
            <h2>Finalize sua Compra</h2>

            <div class="error-message" id="errorMessage"></div>

            <div class="selected-plan-info" id="selectedPlanInfo"></div>

            <form id="paymentForm">
                <input type="hidden" id="planId" name="plan_id">
                <input type="hidden" id="clientIp" name="client_ip">
                <input type="hidden" id="clientMac" name="client_mac">
                
                <div class="form-group">
                    <label for="name">Nome Completo *</label>
                    <input type="text" id="name" name="name" autocomplete="name" required>
                </div>

                <div class="form-group">
                    <label for="email">E-mail *</label>
                    <input type="email" id="email" name="email" autocomplete="email" required>
                </div>

                <div class="form-group">
                    <label for="phone">Telefone/WhatsApp *</label>
                    <input type="tel" id="phone" name="phone" autocomplete="tel" placeholder="(00) 00000-0000" required>
                </div>

                <div class="form-group">
                    <label for="cpf">CPF *</label>
                    <input type="text" id="cpf" name="cpf" autocomplete="off" placeholder="000.000.000-00" required>
                </div>

                <h4 style="margin: 25px 0 15px; color: #333;">Escolha a forma de pagamento:</h4>

                <div class="payment-methods">
                    <div class="payment-method" onclick="selectPaymentMethod('pix')" data-method="pix">
                        <svg fill="#1e88e5" viewBox="0 0 24 24"><path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5zm0 18c-3.87-.78-7-4.88-7-9V8.3l7-3.11 7 3.11V11c0 4.12-3.13 8.22-7 9z"/></svg>
                        <strong>PIX</strong>
                        <p style="font-size: 0.85em; margin-top: 5px;">Aprovação imediata</p>
                    </div>

                    <div class="payment-method" onclick="selectPaymentMethod('checkout')" data-method="checkout">
                        <svg fill="#1e88e5" viewBox="0 0 24 24"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
                        <strong>Cartão/Boleto</strong>
                        <p style="font-size: 0.85em; margin-top: 5px;">Várias opções</p>
                    </div>
                </div>

                <input type="hidden" id="paymentMethod" name="payment_method">

                <button type="submit" class="submit-payment-btn" id="submitBtn" disabled>
                    Prosseguir para Pagamento
                </button>

                <button type="button" class="back-btn" onclick="backToPlans()">
                    Voltar aos Planos
                </button>
            </form>

            <div class="loading" id="loading">
                <div class="loading-spinner"></div>
                <p>Processando seu pagamento...</p>
            </div>
        </div>

        <div class="info-section">
            <h3>Informações Importantes</h3>
            <h4>Conexão de Alta Performance e Transparência</h4>
            <p>Em qualquer plano que você escolher, a sua velocidade de conexão será a mais rápida que estiver disponível no momento, sem limites de banda ou download. É importante lembrar que, em algumas situações, a conexão pode sofrer quedas ou limitações devido à distância entre você e o ponto de acesso, ou à quantidade de dispositivos conectados na mesma área. Garantimos o nosso melhor para que sua experiência seja sempre a mais fluida possível.</p>
        </div>

        <div class="contact-section">
            <h3>Contato e Suporte</h3>
            <h4>Conexão Rápida, Estabilidade Garantida.</h4>
            <p>O nosso hotspot é a solução ideal para uma navegação prática e rápida. No entanto, se você busca uma internet com ainda mais qualidade, estabilidade e velocidade, entre em contato para solicitar uma análise de viabilidade de conexão via rádio. Nossa equipe está pronta para avaliar a melhor solução para você.</p>
            
            <div class="contact-info">
                <h5>Fale conosco:</h5>
                <div class="contact-item">
                    <svg fill="white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56-.35-.12-.74-.03-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.73 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z"/>
                    </svg>
                    <a href="tel:+5581998181680">(81) 99818-1680</a>
                </div>
                <div class="contact-item">
                    <svg fill="white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                    </svg>
                    <a href="https://wa.me/5581998181680" target="_blank">WhatsApp: (81) 99818-1680</a>
                </div>
                <div class="contact-item">
                    <svg fill="white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                    </svg>
                    <a href="mailto:hanter.duarte@gmail.com">hanter.duarte@gmail.com</a>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>Powered by MikroTik RouterOS</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Captura os parâmetros IP e MAC da URL
            const urlParams = new URLSearchParams(window.location.search);
            const ip = urlParams.get('ip') || '0.0.0.0'; 
            const mac = urlParams.get('mac') || '00:00:00:00:00:00'; 

            // Preenche os campos ocultos do formulário de pagamento
            document.getElementById('clientIp').value = ip;
            document.getElementById('clientMac').value = mac;

            // Log de debug no console (opcional, pode remover em produção)
            console.log("IP Capturado da URL:", ip);
            console.log("MAC Capturado da URL:", mac);
        });

        let selectedPlanId = null;
        let selectedPaymentMethod = null;
        // O array 'plans' é alimentado pelo PHP no topo do arquivo
        const plans = <?php echo json_encode($plans); ?>; 

        function selectPlan(planId) {
            selectedPlanId = planId;
            document.getElementById('planId').value = planId;

            // Destacar plano selecionado
            document.querySelectorAll('.plan-card').forEach(card => {
                card.classList.remove('selected');
            });
            document.querySelector(`[data-plan-id="${planId}"]`).classList.add('selected');

            // Mostrar formulário
            document.getElementById('plansSection').style.display = 'none';
            document.getElementById('formSection').classList.add('active');

            // Rolar suavemente até o formulário
            document.getElementById('formSection').scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start' 
            });

            // Atualizar info do plano
            const plan = plans.find(p => p.id == planId);
            const formattedPrice = plan.price.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'}); 
            
            document.getElementById('selectedPlanInfo').innerHTML = `
                <h4>Plano Selecionado:</h4>
                <p><strong>${plan.name}</strong> - ${formattedPrice}</p>
                <p style="font-size: 0.9em; margin-top: 5px;">${plan.description}</p>
            `;
        }

        function selectPaymentMethod(method) {
            selectedPaymentMethod = method;
            document.getElementById('paymentMethod').value = method;

            // Destacar método selecionado
            document.querySelectorAll('.payment-method').forEach(el => {
                el.classList.remove('selected');
            });
            document.querySelector(`[data-method="${method}"]`).classList.add('selected');

            // Habilitar botão
            document.getElementById('submitBtn').disabled = false;
        }

        function backToPlans() {
            document.getElementById('plansSection').style.display = 'block';
            document.getElementById('formSection').classList.remove('active');
            document.getElementById('errorMessage').classList.remove('active');
        }

        // Máscaras (CPF e Telefone)
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                // Formato (00) 00000-0000 (celular 9 dígitos)
                value = value.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
                if (value.length < 14) {
                    // Formato (00) 0000-0000 (fixo 8 dígitos)
                    value = value.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
                }
            }
            e.target.value = value;
        });

        document.getElementById('cpf').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/^(\d{3})(\d{3})(\d{3})(\d{2}).*/, '$1.$2.$3-$4');
            e.target.value = value;
        });

        // Submeter formulário de pagamento
        document.getElementById('paymentForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            if (!selectedPaymentMethod) {
                showError('Por favor, selecione uma forma de pagamento');
                return;
            }

            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            document.getElementById('loading').classList.add('active');
            document.getElementById('paymentForm').style.display = 'none';
            document.getElementById('errorMessage').classList.remove('active');

            try {
                const response = await fetch('process_payment_infinity.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // PIX - redirecionar para página com QR Code
                    if (result.data && result.data.payment_id) {
                        window.location.href = 'pix_payment.php?payment_id=' + result.data.payment_id;
                    } 
                    // Checkout (Cartão/Boleto) - redirecionar para a URL do provedor
                    else if (result.data && result.data.redirect_url) {
                        window.location.href = result.data.redirect_url;
                    }
                    else {
                        showError('Erro: resposta inválida do servidor');
                        document.getElementById('loading').classList.remove('active');
                        document.getElementById('paymentForm').style.display = 'block';
                    }
                } else {
                    showError(result.message || 'Erro ao processar pagamento');
                    document.getElementById('loading').classList.remove('active');
                    document.getElementById('paymentForm').style.display = 'block';
                }
            } catch (error) {
                showError('Erro de conexão. Tente novamente.');
                document.getElementById('loading').classList.remove('active');
                document.getElementById('paymentForm').style.display = 'block';
            }
        });

        function showError(message) {
            const errorEl = document.getElementById('errorMessage');
            errorEl.textContent = message;
            errorEl.classList.add('active');
        }
    </script>
</body>
</html>