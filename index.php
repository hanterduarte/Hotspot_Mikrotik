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

        @media (max-width: 768px) {
            .plans-grid {
                grid-template-columns: 1fr;
            }
            .payment-methods {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="img/wifi-barato-logo.png" alt="WiFi Barato" class="logo">
        </div>

        <div class="intro-box">
            <h2>Olá! Acesso à internet rápido e barato para você.</h2>
            <p>Nossa missão é democratizar o acesso à internet de alta velocidade. Oferecemos uma conexão ágil e de baixo custo, ideal para quem precisa navegar, trabalhar ou se divertir online, sem a burocracia das operadoras tradicionais.</p>
            <p>Conecte-se com planos de curta duração ou escolha nosso plano mensal, ideal para quem busca uma solução completa sem a necessidade de contratos de fidelidade.</p>
        </div>

        <!-- Seleção de Planos -->
        <div id="plansSection" class="plans-section">
            <div class="plans-title">
                <h3>💰 Escolha seu Plano</h3>
                <p>Selecione o melhor plano para você</p>
            </div>

            <div class="plans-grid">
                <?php foreach ($plans as $plan): ?>
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

            <div class="intro-box" style="margin-top: 40px;">
                <h3 style="text-align: center; color: #1e88e5; margin-bottom: 20px;">Informações Importantes</h3>
                <p><strong>Conexão de Alta Performance e Transparência</strong></p>
                <p>Em qualquer plano que você escolher, a sua velocidade de conexão será a mais rápida que estiver disponível no momento, sem limites de banda ou download. É importante lembrar que, em algumas situações, a conexão pode sofrer quedas ou limitações devido à distância entre você e o ponto de acesso, ou à quantidade de dispositivos conectados na mesma área. Garantimos o nosso melhor para que sua experiência seja sempre a mais fluida possível.</p>
            </div>

            <div class="intro-box" style="margin-top: 20px;">
                <h3 style="text-align: center; color: #1e88e5; margin-bottom: 20px;">Contato e Suporte</h3>
                <p><strong>Conexão Rápida, Estabilidade Garantida.</strong></p>
                <p>O nosso hotspot é a solução ideal para uma navegação prática e rápida. No entanto, se você busca uma internet com ainda mais qualidade, estabilidade e velocidade, entre em contato para solicitar uma análise de viabilidade de conexão via rádio. Nossa equipe está pronta para avaliar a melhor solução para você.</p>
                <p style="margin-top: 15px;"><strong>Fale conosco:</strong></p>
                <ul style="list-style: none; padding-left: 0; margin-top: 10px;">
                    <li><strong>Telefone e WhatsApp:</strong> (81) 99818-1680</li>
                    <li><strong>E-mail:</strong> hanter.duarte@gmail.com</li>
                </ul>
            </div>
        </div>

        <!-- Formulário de Cadastro e Pagamento -->
        <div id="formSection" class="form-box">
            <h2>Finalize sua Compra</h2>

            <div class="error-message" id="errorMessage"></div>

            <div class="selected-plan-info" id="selectedPlanInfo"></div>

            <form id="paymentForm">
                <input type="hidden" id="planId" name="plan_id">

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
    </div>

    <script>
        let selectedPlanId = null;
        let selectedPaymentMethod = null;
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

            // Atualizar info do plano
            const plan = plans.find(p => p.id == planId);
            document.getElementById('selectedPlanInfo').innerHTML = `
                <h4>Plano Selecionado:</h4>
                <p><strong>${plan.name}</strong> - ${plan.price.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'})}</p>
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

        // Máscaras
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
                if (value.length < 14) {
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

        // Submeter formulário
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

                console.log('Resposta do servidor:', result); // Debug

                if (result.success) {
                    // PIX - redirecionar para página com QR Code
                    if (result.data && result.data.payment_id) {
                        window.location.href = 'pix_payment.php?payment_id=' + result.data.payment_id;
                    } 
                    // Checkout - redirecionar para Mercado Pago
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