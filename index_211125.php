<?php
require_once 'config.php';

// Captura variáveis do Mikrotik
$linkLogin = isset($_GET['link-login-only']) ? $_GET['link-login-only'] : '';
$linkOrig = isset($_GET['link-orig']) ? $_GET['link-orig'] : '';
$chapId = isset($_GET['chap-id']) ? $_GET['chap-id'] : '';
$chapChallenge = isset($_GET['chap-challenge']) ? $_GET['chap-challenge'] : '';
$username = isset($_GET['username']) ? $_GET['username'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

// Função de formatação de dinheiro
if (!function_exists('formatMoney')) {
    function formatMoney($amount) {
        return 'R$ ' . number_format($amount, 2, ',', '.');
    }
}

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
        
        /* --- CSS PARA O BLOCO DE LOGIN (MANTIDO) --- */
        .login-box {
            background: #e3f2fd; /* Cor azul clara */
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px; /* Espaçamento entre login e cards */
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .login-box h2 {
            color: #0d47a1;
            margin-bottom: 20px;
            font-size: 1.5em;
        }

        .input-group {
            margin-bottom: 15px;
        }

        .login-box input[type="text"],
        .login-box input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            font-size: 1em;
            max-width: 350px;
            margin: 0 auto;
            display: block; 
        }

        .login-btn {
            background: #0d47a1; /* Azul escuro */
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }

        .login-btn:hover {
            background: #1e88e5;
        }

        .login-message {
            margin-top: 15px;
            font-size: 0.9em;
            color: #666;
        }
        /* --- FIM CSS LOGIN --- */


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
            text-align: left;
        }

        .plan-features li {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            color: #666;
            font-size: 1em;
        }
        
        .plan-features li:last-child {
            border-bottom: none;
        }

        .select-btn {
            background-color: #4CAF50; /* Green */
            border: none;
            color: white;
            padding: 15px 32px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 1em;
            margin: 10px 2px;
            cursor: pointer;
            border-radius: 10px;
            width: 100%;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }

        .select-btn:hover {
            background-color: #45a049;
        }
        
        .info-section {
            background: #ffffff;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }

        .info-section h3 {
            color: #0d47a1;
            font-size: 1.5em;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
        }

        .info-section ul {
            list-style: none;
            padding-left: 0;
        }

        .info-section li {
            margin-bottom: 15px;
            color: #555;
            font-size: 1.1em;
            line-height: 1.5;
        }

        .info-section li strong {
            color: #333;
            font-weight: bold;
        }
        
        .info-section .transparency-box {
            background: #f0f7ff;
            border-left: 5px solid #1e88e5;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        
        .info-section .transparency-box p {
            color: #333;
            line-height: 1.6;
            font-style: italic;
        }

        /* --- CSS DA SEÇÃO DE CONTATO --- */
        .contact-section {
            background: #0d47a1; /* Azul escuro */
            color: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            margin-bottom: 30px;
            text-align: center;
        }

        .contact-section h3 {
            color: white;
            font-size: 1.8em;
            margin-bottom: 15px;
            border-bottom: 2px solid rgba(255,255,255,0.3);
            padding-bottom: 10px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }

        .contact-section p {
            font-size: 1.2em;
            margin-bottom: 15px;
            text-align: center;
            line-height: 1.5;
        }
        
        /* NOVO ESTILO: Fundo branco semi-transparente para o bloco de contatos */
        .contact-box-overlay {
            background: rgba(255, 255, 255, 0.15); /* Branco com 15% de opacidade */
            border-radius: 10px;
            padding: 20px 10px;
            margin-top: 20px;
            /* Isso garante que o texto fique legível contra o fundo escuro */
            color: white; 
            backdrop-filter: blur(2px); /* Efeito glassmorphism leve (opcional, mas fica legal) */
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .contact-box-overlay p {
            font-size: 1.2em; 
            font-weight: bold; 
            margin-bottom: 15px; 
            margin-top: 0;
        }

        .contact-item {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 12px 0;
            font-size: 1.1em;
            font-weight: bold;
        }

        .contact-item svg {
            width: 24px;
            height: 24px;
            margin-right: 10px;
            flex-shrink: 0;
            fill: white;
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
        /* --- FIM CSS DA SEÇÃO DE CONTATO --- */

        .footer {
            text-align: center;
            color: white;
            margin: 40px 0 20px 0;
            opacity: 0.8;
            font-size: 0.9em;
        }

        /* Modal/Payment Form CSS (MANTIDO) */
        .payment-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .payment-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.5);
            position: relative;
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }

        .close-btn {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 1.8em;
            color: #aaa;
            cursor: pointer;
            line-height: 1;
        }

        .payment-content h3 {
            color: #0d47a1;
            text-align: center;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
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
        
        .submit-btn {
            background-color: #0d47a1;
            color: white;
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-size: 1.1em;
            width: 100%;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }

        .submit-btn:hover {
            background-color: #1e88e5;
        }
        
        .loading {
            text-align: center;
            padding: 40px 20px;
            color: #0d47a1;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .loading-spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #0d47a1;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .error-message {
            color: white;
            background-color: #f44336;
            padding: 10px;
            border-radius: 8px;
            margin-top: 15px;
            display: none;
            font-weight: bold;
        }
        .error-message.active {
            display: block;
        }


        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            .plans-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="logo.png" alt="Logo WiFi Barato" class="logo">
        </div>

        <div class="intro-box">
            <h2>Olá! Acesso à internet rápido e barato para você.</h2>
            <p>Conecte-se à nossa rede e desfrute de alta velocidade para trabalhar, estudar e se divertir. Escolha um dos planos abaixo e comece a navegar em segundos. Nosso pagamento é seguro, processado pela InfinitePay, e a liberação é imediata.</p>
            <p>Para clientes que já possuem um plano ativo, basta utilizar seu login e senha no formulário abaixo para continuar a navegação.</p>
        </div>
        
        <div class="login-box">
            <h2>🤝 Já tem um plano? Acesse aqui!</h2>
            <?php if($error): ?><div class="error-message active">Usuário ou senha incorretos. Tente novamente.</div><?php endif; ?>
<form class="login-form" style="text-align:center;" method="post" action="<?php echo htmlspecialchars($linkLogin); ?>">
<label>Usuário:</label>
<input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
<label>Senha:</label>
<input type="password" name="password" required>
<input type="hidden" name="dst" value="<?php echo htmlspecialchars($linkOrig); ?>">
<input type="hidden" name="popup" value="true">
<button type="submit" class="login-btn">Entrar</button>
</form>
            <p class="login-message">Seus dados serão autenticados pelo sistema pfSense.</p>
        </div>
        <div id="plansSection" class="plans-section">
            <div class="plans-title">
                <h3>💰 Escolha seu Plano</h3>
                <p>Selecione o melhor plano para você</p>
            </div>
            <div class="plans-grid">
                <?php if (empty($plans)): ?>
                    <p style="color: white; font-size: 1.2em; text-align: center; grid-column: 1 / -1;">Nenhum plano ativo encontrado. Verifique a tabela 'plans'.</p>
                <?php else: ?>
                    <?php foreach ($plans as $plan): ?>
                        <div class="plan-card" onclick="selectPlan(<?php echo $plan['id']; ?>)" 
                            data-plan-id="<?php echo $plan['id']; ?>"
                            data-plan-name="<?php echo htmlspecialchars($plan['name']); ?>"
                            data-plan-price="<?php echo formatMoney($plan['price']); ?>"
                            data-plan-rawprice="<?php echo $plan['price']; ?>"
                        >
                            <div class="plan-duration"><?php echo htmlspecialchars($plan['name']); ?></div>
                            <div class="plan-price"><?php echo formatMoney($plan['price']); ?></div>
                            <ul class="plan-features">
                                <li>✅ Acesso Ilimitado por <?php echo htmlspecialchars($plan['duration_value']); ?> <?php echo htmlspecialchars($plan['duration_unit']); ?></li>
                                <li>✅ Velocidade Máxima Disponível</li>
                                <li>✅ Sem Franquia de Dados</li>
                                <li>✅ Suporte 24h (via WhatsApp)</li>
                            </ul>
                            <button class="select-btn">Escolher este Plano</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div id="importantInfoSection" class="info-section">
            <h3>⚠️ Informações Importantes</h3>
            
            <div class="transparency-box">
                <p><strong>Conexão de Alta Performance e Transparência</strong></p>
                <p>Em qualquer plano que você escolher, a sua velocidade de conexão será a mais rápida que estiver disponível no momento, sem limites de banda ou download. É importante lembrar que, em algumas situações, a conexão pode sofrer quedas ou limitações devido à distância entre você e o ponto de acesso, ou à quantidade de dispositivos conectados na mesma área. Garantimos o nosso melhor para que sua experiência seja sempre a mais fluida possível.</p>
            </div>
            
            <ul>
                <li><strong>Liberação Imediata:</strong> Após a confirmação do pagamento, seu acesso é liberado em até 1 minuto.</li>
                <li><strong>Segurança:</strong> Todos os pagamentos são processados pela InfinitePay, garantindo a segurança dos seus dados.</li>
                <li><strong>Validade:</strong> O acesso é válido pelo período contratado (ex: 30 dias), sem renovação automática.</li>
                <li><strong>Dispositivos:</strong> O plano contratado permite a conexão de um dispositivo por vez.</li>
            </ul>
        </div>

        <div id="contactSection" class="contact-section">
            <h3>📞 Contato e Suporte</h3>
            
            <p style="text-align: justify; padding: 0 10px;">
                <strong style="font-size: 1.1em; display: block; margin-bottom: 5px;">Conexão Rápida, Estabilidade Garantida.</strong>
                O nosso hotspot é a solução ideal para uma navegação prática e rápida. No entanto, se você busca uma internet com ainda mais qualidade, estabilidade e velocidade, entre em contato para solicitar uma análise de viabilidade de conexão via rádio. Nossa equipe está pronta para avaliar a melhor solução para você.
            </p>
            
            <div class="contact-box-overlay">
                <p>Fale conosco:</p>
                
                <div class="contact-item">
                    <svg fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M6.62 10.79a15.827 15.827 0 006.59 6.59l2.21-2.21c.27-.27.67-.36 1.02-.24A10.156 10.156 0 0121 15c.55 0 1 .45 1 1v3c0 .55-.45 1-1 1h-3c-3.13 0-6.13-1.12-8.58-3.58C7.12 14.13 6 11.13 6 8V5c0-.55.45-1 1-1h3c.55 0 1 .45 1 1s0 1.27-.47 2.03l-2.21 2.21z"/></svg>
                    (81) 99818-1680
                </div>
                <div class="contact-item">
                    <svg fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M19.05 4.908A9.873 9.873 0 0 0 12.048 2c-5.46 0-9.897 4.438-9.897 9.898 0 1.83 0.506 3.55 1.488 5.03l-1.564 4.545 4.7-1.522c1.42 0.778 3.032 1.18 4.717 1.18h.004c5.458 0 9.897-4.438 9.897-9.898A9.863 9.863 0 0 0 19.05 4.908zm-7.002 14.55c-1.57 0-3.023-.418-4.328-1.13l-.315-.184-3.262 1.058 1.077-3.155-.205-.325a8.384 8.384 0 0 1-1.285-4.492c0-4.636 3.77-8.406 8.407-8.406 2.257 0 4.38.878 5.986 2.486 1.606 1.606 2.485 3.73 2.485 5.987 0 4.636-3.77 8.406-8.406 8.406zm4.18-5.35c-.23-.115-.81-.4-.94-.45-.13-.05-.28-.02-.4.14-.1.17-.38.48-.46.58-.09.1-.17.11-.3.06-.9-.37-2.16-.95-3.07-2.48-.28-.48-.03-.74.12-.9.1-.1.2-.24.3-.37.07-.12.14-.23.18-.32.07-.15.03-.28-.02-.4-.05-.12-.46-1.1-.63-1.5-.16-.39-.33-.33-.46-.33-.12 0-.25-.01-.4-.01-.15 0-.4.05-.6.28-.2.25-.76.75-.76 1.83 0 1.08.78 2.1 1.76 3.12 1 1.03 1.94 1.4 3.06 1.68.28.08.45.07.61.04.16-.03 1.06-.43 1.34-.84.28-.4.28-.75.2-.84-.08-.08-.23-.12-.48-.25z"/>
                    </svg>
                    <a href="https://api.whatsapp.com/send?phone=5581998181680&text=Ol%C3%A1,%20gostaria%20de%20saber%20mais%20sobre%20a%20conex%C3%A3o%20via%20r%C3%A1dio." target="_blank">WhatsApp: (81) 99818-1680</a>
                </div>
                <div class="contact-item">
                    <svg fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                    <a href="mailto:hanter.duarte@gmail.com">hanter.duarte@gmail.com</a>
                </div>
            </div>
            </div>
        <div class="footer">
            &copy; 2025 WiFi Barato. Todos os direitos reservados.
        </div>
    </div>
    
    <div class="payment-modal" id="paymentModal">
        <div class="payment-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            <div id="payment-form-display">
                <div class="selected-plan-info">
                    <h4>Finalizar Compra</h4>
                    <p id="plan-name-display">Plano Selecionado: </p>
                    <p id="plan-price-display">Valor: </p>
                </div>
                
                <form id="customerForm">
                    <input type="hidden" name="plan_id" id="plan_id_input">
                    
                    <div class="form-group">
                        <input type="text" name="name" id="name" placeholder="Nome Completo" required>
                    </div>
                    <div class="form-group">
                        <input type="email" name="email" id="email" placeholder="Seu Email" required>
                    </div>
                    <div class="form-group">
                        <input type="text" name="phone" id="phone" placeholder="Celular com DDD (Apenas números)" required pattern="[0-9]{10,11}">
                    </div>
                    <div class="form-group">
                        <input type="text" name="cpf" id="cpf" placeholder="CPF (Apenas números)" required pattern="[0-9]{11}" maxlength="11">
                    </div>
                    
                    <button type="submit" class="submit-btn">Pagar com InfinitePay</button>
                    <div id="errorMessage" class="error-message"></div>
                </form>
            </div>

            <div class="loading" id="loading">
                <div class="loading-spinner"></div>
                <p>Processando seu pagamento...</p>
            </div>
        </div>
    </div>
    <script>
        const paymentModal = document.getElementById('paymentModal');
        const customerForm = document.getElementById('customerForm');
        const loadingScreen = document.getElementById('loading');
        const formDisplay = document.getElementById('payment-form-display');

        function selectPlan(planId) {
            const card = document.querySelector(`.plan-card[data-plan-id="${planId}"]`);
            if (!card) return;

            // Atualiza o formulário do modal
            document.getElementById('plan_id_input').value = planId;
            document.getElementById('plan-name-display').textContent = `Plano Selecionado: ${card.dataset.planName}`;
            document.getElementById('plan-price-display').textContent = `Valor: ${card.dataset.planPrice}`;

            // Exibe o modal
            openModal();
        }

        function openModal() {
            document.getElementById('errorMessage').classList.remove('active');
            formDisplay.style.display = 'block';
            loadingScreen.style.display = 'none';
            paymentModal.style.display = 'flex';
        }

        function closeModal() {
            paymentModal.style.display = 'none';
        }

        // Fecha o modal ao clicar fora
        window.onclick = function(event) {
            if (event.target == paymentModal) {
                closeModal();
            }
        }
        
        // Processamento do Formulário de Compra
        customerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // 1. Esconder formulário, mostrar loading
            document.getElementById('errorMessage').classList.remove('active');
            formDisplay.style.display = 'none';
            loadingScreen.style.display = 'flex';

            const formData = new FormData(customerForm);
            const data = Object.fromEntries(formData.entries());

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
                    // Redirecionamento para o Checkout da InfinitePay
                    if (result.data && result.data.redirect_url) {
                        window.location.href = result.data.redirect_url;
                    }
                    else {
                        showError('Erro: URL de redirecionamento inválida do servidor');
                        loadingScreen.style.display = 'none';
                        formDisplay.style.display = 'block';
                    }
                } else {
                    showError(result.message || 'Erro ao processar pagamento');
                    loadingScreen.style.display = 'none';
                    formDisplay.style.display = 'block';
                }
            } catch (error) {
                showError('Erro de conexão. Tente novamente.');
                loadingScreen.style.display = 'none';
                formDisplay.style.display = 'block';
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