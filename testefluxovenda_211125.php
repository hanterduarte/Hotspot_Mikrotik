<?php
// testefluxovenda.php - Simula o fluxo de venda completo (Plano -> Cliente -> Checkout -> Webhook)
require_once 'config.php'; 
require_once 'MikrotikAPI.php'; 

// O arquivo 'config.php' agora é responsável por garantir que a sessão esteja ativa.
$db = Database::getInstance()->getConnection();
$message = '';
$transactionId = $_SESSION['test_transaction_id'] ?? null;
$step = $_SESSION['test_step'] ?? 1;
$selectedPlan = $_SESSION['selected_plan'] ?? null;
$clientData = $_SESSION['client_data'] ?? [];
$fictitiousPayload = $_SESSION['fictitious_payload'] ?? null;

// Novas variáveis de DEBUG para a API do Mikrotik
$mikrotikCallParams = $_SESSION['mikrotik_call_params'] ?? null;
$mikrotikCallResult = $_SESSION['mikrotik_call_result'] ?? null;

// --------------------------------------------------------------------------
// BUSCA DE PLANOS (Executada em todas as etapas)
// --------------------------------------------------------------------------
$stmt_plan = $db->query("SELECT id, name, price, description, mikrotik_profile FROM plans WHERE active = 1 ORDER BY price ASC");
$allPlans = $stmt_plan->fetchAll();

if (empty($allPlans)) {
    die("Erro: Nenhum plano ativo encontrado na tabela 'plans'. Por favor, crie um plano.");
}

// Se um plano foi selecionado na sessão, encontre os dados completos dele
if ($selectedPlan) {
    $foundPlan = array_filter($allPlans, function($p) use ($selectedPlan) {
        // Correção para garantir a comparação entre tipos (string/int)
        return (int)$p['id'] === (int)$selectedPlan['id']; 
    });
    $_SESSION['selected_plan'] = reset($foundPlan) ? reset($foundPlan) : null;
    $selectedPlan = $_SESSION['selected_plan'];
}

// --------------------------------------------------------------------------
// FUNÇÕES DE EXIBIÇÃO
// --------------------------------------------------------------------------

function displayTransaction($db, $transactionId) {
    $stmt = $db->prepare("
        SELECT 
            t.id, t.amount, t.payment_status, t.customer_id, t.infinitypay_order_id,
            hu.username, hu.password, hu.mikrotik_profile, hu.expires_at
        FROM transactions t
        LEFT JOIN hotspot_users hu ON t.id = hu.transaction_id
        WHERE t.id = ?
    ");
    $stmt->execute([$transactionId]);
    $transaction = $stmt->fetch();

    $transaction['detailed_error'] = null;

    if ($transaction && $transaction['payment_status'] !== 'success') {
        // Filtra pelo ID da transação no conteúdo da mensagem de log
        $logStmt = $db->prepare("
            SELECT log_message
            FROM logs
            WHERE log_message LIKE ? 
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $logStmt->execute(["%TX: $transactionId%"]); 
        $transaction['detailed_error'] = $logStmt->fetchColumn();
    }
    
    return $transaction;
}

// Função auxiliar para obter o customer_id (necessário para a simulação)
function getCustomerIdByTransactionId($db, $transactionId) {
    $stmt = $db->prepare("SELECT customer_id FROM transactions WHERE id = ?");
    $stmt->execute([$transactionId]);
    return $stmt->fetchColumn();
}


if (!function_exists('formatMoney')) {
    function formatMoney($amount) {
        return 'R$ ' . number_format($amount, 2, ',', '.');
    }
}


// --------------------------------------------------------------------------
// LÓGICA DO FLUXO (Tratamento de Ações POST)
// --------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Limpa variáveis de debug ao iniciar uma nova etapa ou reiniciar
    if (isset($_POST['select_plan']) || isset($_POST['start_checkout']) || isset($_POST['reset_test'])) {
        unset($_SESSION['mikrotik_call_params']);
        unset($_SESSION['mikrotik_call_result']);
    }

    // ======================================================================
    // ETAPA 1.1: SELECIONAR PLANO
    // ======================================================================
    if (isset($_POST['select_plan'])) {
        $planId = intval($_POST['plan_id']);
        $selectedPlanData = array_filter($allPlans, function($p) use ($planId) {
            return (int)$p['id'] === $planId; 
        });
        
        if (reset($selectedPlanData)) {
            $_SESSION['selected_plan'] = reset($selectedPlanData);
            $_SESSION['test_step'] = 2; // Avança para a inserção de dados
            $message = "✅ Plano '{$_SESSION['selected_plan']['name']}' (R$ {$_SESSION['selected_plan']['price']}) selecionado. Insira os dados do cliente.";
        } else {
            $message = "❌ Erro: Plano ID $planId não encontrado ou inativo.";
            $_SESSION['test_step'] = 1; 
        }
    } 
    
    // ======================================================================
    // ETAPA 2.1: INSERIR DADOS DO CLIENTE E CRIAR TRANSAÇÃO
    // ======================================================================
    elseif (isset($_POST['start_checkout']) && $selectedPlan) {
        
        // 1. Coleta e armazena os dados do formulário
        $clientData = [
            'name' => sanitizeInput($_POST['name']),
            'email' => sanitizeInput($_POST['email']),
            'phone' => preg_replace('/[^0-9]/', '', (string)$_POST['phone']),
            'cpf' => preg_replace('/[^0-9]/', '', (string)$_POST['cpf']),
            'plan_id' => $selectedPlan['id'],
            'plan_price' => $selectedPlan['price'],
            'client_ip' => '192.168.1.100', // Valor fixo para teste
            'client_mac' => '00:00:00:00:00:00' // Valor fixo para teste
        ];
        $_SESSION['client_data'] = $clientData;

        // 2. Tenta criar a transação
        $db->beginTransaction();
        
        try {
            $customerData = [
                'name' => $clientData['name'], 
                'email' => $clientData['email'], 
                'phone' => $clientData['phone'], 
                'cpf' => $clientData['cpf']
            ];
            $customerId = createOrGetCustomer($db, $customerData); 
            
            // Inserção da Transação
            $stmt = $db->prepare("
                INSERT INTO transactions (
                    customer_id, plan_id, amount, payment_method, payment_status,
                    client_ip, client_mac, created_at
                ) VALUES (?, ?, ?, ?, 'pending', ?, ?, NOW())
            ");
            $stmt->execute([
                $customerId,
                $clientData['plan_id'],
                $clientData['plan_price'],
                'infinitepay_checkout',
                $clientData['client_ip'],
                $clientData['client_mac']
            ]);
            $transactionId = $db->lastInsertId();

            // Simular o retorno da InfinitePay (Order ID)
            $invoiceSlug = 'inv-' . uniqid();
            
            $stmt = $db->prepare("UPDATE transactions SET infinitypay_order_id = ? WHERE id = ?");
            $stmt->execute([$invoiceSlug, $transactionId]);

            // PAYLOAD FICTÍCIO DA INFINITEPAY (Gerado após a transação)
            $payload = [
                "event" => "invoice_paid",
                "invoice_slug" => $invoiceSlug,
                "order_nsu" => strval($transactionId), // ID da transação
                "status" => "paid",
                "transaction_nsu" => "IP_NSU_" . time(), 
                "capture_method" => "credit_card", 
                "amount" => $selectedPlan['price'] * 100 // Valor em centavos
            ];
            $_SESSION['fictitious_payload'] = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            $db->commit();

            $_SESSION['test_transaction_id'] = $transactionId;
            $_SESSION['test_invoice_slug'] = $invoiceSlug;
            $_SESSION['test_step'] = 3; // AVANÇA para a Etapa 3: Confirmação do Payload
            $message = "✅ Transação #$transactionId (Cliente ID: $customerId) criada com sucesso e pendente. Avance para a Etapa 3 para **confirmar o Payload**.";

        } catch (Exception $e) {
            $db->rollBack();
            $message = "❌ Erro ao criar transação: " . $e->getMessage();
            $_SESSION['test_step'] = 2; 
        }
    } 
    
    // ======================================================================
    // ETAPA 3.1: CONFIRMAR RECEBIMENTO DO PAYLOAD
    // ======================================================================
    elseif (isset($_POST['confirm_payload']) && $transactionId && $step == 3) {
        $_SESSION['test_step'] = 4; // AVANÇA para a Etapa 4: Processar Webhook
        $message = "➡️ Payload Webhook (JSON) confirmado. Prossiga para a **Etapa 4: Processar Webhook**.";
    }

    // ======================================================================
    // ETAPA 4.1: PROCESSAR O WEBHOOK (Simulação Direta)
    // ======================================================================
    elseif (isset($_POST['process_webhook']) && $transactionId && $step == 4) {
        
        // 1. Obter dados necessários (simulando a busca do webhook)
        $stmt = $db->prepare("SELECT plan_id, customer_id, client_ip, client_mac FROM transactions WHERE id = ?");
        $stmt->execute([$transactionId]);
        $transactionData = $stmt->fetch();

        $planId = $transactionData['plan_id'] ?? null;
        $customerId = $transactionData['customer_id'] ?? null;
        $clientIp = $transactionData['client_ip'] ?? 'N/A';
        $clientMac = $transactionData['client_mac'] ?? 'N/A';
        
        $mikrotikCallParams = [
            'planId' => $planId,
            'clientIp' => $clientIp,
            'clientMac' => $clientMac
        ];
        
        $_SESSION['mikrotik_call_params'] = $mikrotikCallParams; // Salva para exibição
        $_SESSION['webhook_raw_response'] = "SIMULAÇÃO DIRETA - Não houve chamada cURL."; // Limpa a resposta crua anterior

        if (!$planId || !$customerId) {
             $message = "❌ Erro: Dados essenciais (Plano ou Cliente) não encontrados na transação $transactionId. Por favor, reinicie o teste.";
             $_SESSION['test_step'] = 1;
        } else {
            // 2. Instanciar e chamar a API do Mikrotik (SIMULAÇÃO DO CORPO DO WEBHOOK)
            $mt = new MikrotikAPI();
            
            // Assume que provisionHotspotUser aceita $planId e $clientIp
            $userCreationResult = $mt->provisionHotspotUser($planId, $clientIp); 
            
            $_SESSION['mikrotik_call_result'] = $userCreationResult; // Salva o resultado para exibição
            
            // 3. Simular a atualização do DB (COMMIT/ROLLBACK)
            if ($userCreationResult['success']) {
                $db->beginTransaction();
                try {
                    // a) Atualizar status da transação para 'success'
                    $stmt = $db->prepare("UPDATE transactions SET payment_status = 'success', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$transactionId]);

                    // b) Inserir o usuário hotspot
                    // Assumindo que a validade é configurada por padrão no Mikrotik profile
                    $stmt = $db->prepare("
                        INSERT INTO hotspot_users 
                        (transaction_id, customer_id, plan_id, username, password, mikrotik_profile, expires_at)
                        VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY)) 
                        ON DUPLICATE KEY UPDATE 
                        username = VALUES(username), password = VALUES(password), mikrotik_profile = VALUES(mikrotik_profile)
                    ");
                    
                    $stmt->execute([
                        $transactionId, 
                        $customerId, 
                        $planId, 
                        $userCreationResult['username'], 
                        $userCreationResult['password'], 
                        $userCreationResult['mikrotik_profile']
                    ]);
                    
                    $db->commit();
                    $message = "✅ SIMULAÇÃO WEBHOOK SUCESSO! Usuário Hotspot criado: **{$userCreationResult['username']}**. O status da transação foi atualizado para 'success'.";
                
                } catch (Exception $e) {
                    $db->rollBack();
                    $message = "❌ ERRO DB NA SIMULAÇÃO: O provisionamento no Mikrotik foi bem-sucedido, mas a atualização do DB falhou: " . $e->getMessage();
                    // Logar o erro do DB para facilitar a depuração
                    logEvent('webhook_error', "Erro ao atualizar DB após provisionamento: {$e->getMessage()} | TX: $transactionId", $transactionId);
                }
            } else {
                 $message = "❌ SIMULAÇÃO WEBHOOK FALHOU! Falha ao provisionar usuário Hotspot. Verifique os detalhes do API abaixo.";
                 // Logar o erro do API para facilitar a depuração
                 logEvent('webhook_error', "Falha ao provisionar usuário Mikrotik: {$userCreationResult['message']} | TX: $transactionId", $transactionId);
            }
         }
    }
    
    // Reset da sessão
    elseif (isset($_POST['reset_test'])) {
        session_destroy();
        header("Location: testefluxovenda.php");
        exit();
    }
    
    // Previne reenvio do formulário ao recarregar
    header("Location: testefluxovenda.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Teste de Fluxo de Venda - Hotspot</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .container { max-width: 800px; margin: auto; }
        h1 { margin-bottom: 5px; }
        h2 { border-bottom: 2px solid #ccc; padding-bottom: 10px; margin-top: 30px; }
        .message { padding: 10px; margin-bottom: 20px; border-radius: 5px; font-weight: bold; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        button { padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; margin-right: 10px; }
        .plan-card { border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-radius: 5px; display: flex; justify-content: space-between; align-items: center; }
        .plan-card:hover { background-color: #f9f9f9; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        .step-inactive { opacity: 0.5; pointer-events: none; }
        input[type="text"], input[type="email"] { width: 100%; padding: 10px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .form-group { margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Teste de Fluxo de Venda Hotspot (InfinitePay)</h1>
        <p>Etapa Atual: **<?php echo $step; ?>** | Transação: **#<?php echo $transactionId ?? 'N/A'; ?>**</p>
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, '✅') !== false || strpos($message, '➡️') !== false ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" style="margin-bottom: 20px;">
            <button type="submit" name="reset_test">🔄 Reiniciar Teste</button>
        </form>

        <hr>

        <div class="step <?php echo $step !== 1 ? 'step-inactive' : ''; ?>">
            <h2>Etapa 1: Escolha do Plano</h2>
            <?php foreach ($allPlans as $plan): ?>
            <form method="POST" style="margin: 0; padding: 0;">
                <div class="plan-card">
                    <div>
                        <strong><?php echo htmlspecialchars($plan['name']); ?></strong> (<?php echo htmlspecialchars($plan['description']); ?>)<br>
                        Preço: **<?php echo formatMoney($plan['price']); ?>**
                    </div>
                    <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                    <button type="submit" name="select_plan" value="Selecionar" <?php echo $step !== 1 ? 'disabled' : ''; ?>>
                        Selecionar
                    </button>
                </div>
            </form>
            <?php endforeach; ?>
        </div>

        <div class="step <?php echo $step !== 2 ? 'step-inactive' : ''; ?>">
            <h2>Etapa 2: Inserir Dados e Criar Transação Pendente</h2>
            <?php if ($selectedPlan): ?>
                <p>Plano Selecionado: **<?php echo htmlspecialchars($selectedPlan['name']); ?>** (<?php echo formatMoney($selectedPlan['price']); ?>)</p>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="name">Nome Completo:</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($clientData['name'] ?? 'Cliente Teste'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">E-mail:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($clientData['email'] ?? 'teste' . time() . '@exemplo.com'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Telefone (Somente números):</label>
                        <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($clientData['phone'] ?? '11987654321'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="cpf">CPF (Somente números):</label>
                        <input type="text" id="cpf" name="cpf" value="<?php echo htmlspecialchars($clientData['cpf'] ?? '12345678901'); ?>" required>
                    </div>
                    <button type="submit" name="start_checkout" <?php echo $step !== 2 ? 'disabled' : ''; ?>>
                        Criar Transação Pendente
                    </button>
                </form>
            <?php else: ?>
                <p style="color: red;">Volte à Etapa 1 para selecionar um plano.</p>
            <?php endif; ?>
        </div>
        
        <div class="step <?php echo $step !== 3 ? 'step-inactive' : ''; ?>">
            <h2>Etapa 3: Confirmar Payload Webhook</h2>
            <?php if ($transactionId && $step >= 3 && $fictitiousPayload): ?>
                <p>Transação **#<?php echo $transactionId; ?>** criada. Este é o Payload fictício que o InfinitePay enviaria para o seu `webhook_infinitypay.php`.</p>
                
                <h3 style="color: #007bff;">Payload Fictício (JSON)</h3>
                <pre><?php echo htmlspecialchars($fictitiousPayload); ?></pre>
                
                <form method="POST" style="margin-top: 20px;">
                    <button type="submit" name="confirm_payload" <?php echo $step !== 3 ? 'disabled' : ''; ?>>
                        ✅ Confirmar Recebimento e Avançar para o Processamento
                    </button>
                </form>
            <?php elseif ($step >= 3): ?>
                <p style="color: red;">Erro: Payload não encontrado. Por favor, reinicie o teste na Etapa 1.</p>
            <?php endif; ?>
        </div>

        <?php if ($transactionId && $step >= 4): ?>
        <div class="step">
            <h2>Etapa 4: Simular Processamento do Webhook</h2>
            
            <form method="POST" style="margin-top: 20px; margin-bottom: 20px;">
                <button type="submit" name="process_webhook" <?php echo $step !== 4 ? 'disabled' : ''; ?>>
                    ▶️ Processar Webhook (Simular Execução de `webhook_infinitypay.php`)
                </button>
            </form>
            
            <hr>

            <?php if ($mikrotikCallParams && $mikrotikCallResult): ?>
                <h3>Detalhes da Chamada Mikrotik API (Simulação)</h3>
                
                <h4 style="color: #007bff;">Parâmetros da Chamada provisionHotspotUser()</h4>
                <pre>
provisionHotspotUser(
    planId: <?php echo htmlspecialchars($mikrotikCallParams['planId']); ?>,
    clientIp: '<?php echo htmlspecialchars($mikrotikCallParams['clientIp']); ?>',
    clientMac: '<?php echo htmlspecialchars($mikrotikCallParams['clientMac']); ?>'
)</pre>
                
                <h4 style="color: <?php echo $mikrotikCallResult['success'] ? 'green' : 'red'; ?>;">Resultado Retornado (MikrotikAPI.php)</h4>
                <pre><?php echo htmlspecialchars(json_encode($mikrotikCallResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>

                <?php if (!$mikrotikCallResult['success']): ?>
                    <div class="message error" style="margin-top: 10px;">
                        ❌ **ERRO CRÍTICO:** O Provisionamento falhou! O erro acima é o que a API do MikroTik retornou. Verifique as credenciais do MikroTik (tabela `settings`) e o `mikrotik_profile` do plano (tabela `plans`).
                    </div>
                <?php endif; ?>
                <hr>
            <?php endif; ?>
            <h3>Dados da Transação (DB)</h3>
            <?php $transacaoFinal = displayTransaction($db, $transactionId); ?>
            
            <table>
                <tr><th>ID Transação</th><td><?php echo $transacaoFinal['id']; ?></td></tr>
                <tr><th>Status Pagamento</th><td><b style="color: <?php echo $transacaoFinal['payment_status'] === 'success' ? 'green' : 'red'; ?>;"><?php echo strtoupper($transacaoFinal['payment_status']); ?></b></td></tr>
                <tr><th>Cliente</th><td><?php echo htmlspecialchars($clientData['name'] ?? 'N/A'); ?></td></tr>
            </table>

            <h3>Resultado da Execução do Webhook</h3>
            
            <table>
                <tr><th>Status Final</th><td><b style="color: <?php echo $transacaoFinal['payment_status'] === 'success' ? 'green' : 'red'; ?>;"><?php echo strtoupper($transacaoFinal['payment_status']); ?></b></td></tr>
                <tr><th>Usuário Hotspot</th><td><b style="color: green;"><?php echo $transacaoFinal['username'] ?? 'ERRO/AUSENTE'; ?></b></td></tr>
                <tr><th>Profile (Mikrotik)</th><td><?php echo $transacaoFinal['mikrotik_profile'] ?? 'AUSENTE'; ?></td></tr>
            </table>
            
            <?php if (empty($transacaoFinal['username']) && $step > 4): ?>
                <div class="message error" style="margin-top: 20px;">
                    ⚠️ O Webhook falhou em criar o usuário Hotspot. Verifique o Log de Erro Detalhado abaixo.
                </div>
            <?php endif; ?>

            <?php if ($transacaoFinal['detailed_error'] ?? false): ?>
                 <h3 style="color: red;">Log de Erro Detalhado (Tabela `logs`)</h3>
                 <pre style="background: #ffeded; border: 1px solid #ffaaaa;"><?php echo htmlspecialchars($transacaoFinal['detailed_error']); ?></pre>
                 <p>Este é o erro **exato** que o processo logou.</p>
            <?php endif; ?>
            
        </div>
        <?php endif; // Fim Etapa 4 ?>
        
        <?php if ($transactionId && $step >= 4): ?>
        <hr>
        <div style="margin-top: 30px;">
            <h2>Simulação da Tela de Sucesso (`payment_success.php`)</h2>
            <p>Este frame carrega o arquivo `payment_success.php` usando o ID da transação atual (<strong>#<?php echo $transactionId; ?></strong>) como parâmetro `external_reference`.</p>
            <p><strong>Se o Webhook (Etapa 4) foi bem-sucedido:</strong> você verá as credenciais de acesso dentro do frame.</p>
            <p><strong>Se o Webhook falhou:</strong> você verá a tela de "Aguardando Confirmação" e a checagem automática falhará.</p>
            <iframe 
                src="payment_success.php?external_reference=<?php echo $transactionId; ?>" 
                width="100%" 
                height="600px" 
                style="border: 1px solid #ccc; border-radius: 5px;"
            >
                Seu navegador não suporta iframes.
            </iframe>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>