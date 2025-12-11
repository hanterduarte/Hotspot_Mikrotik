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
$transacaoFinal = $_SESSION['transacao_final'] ?? []; // Adicionado para buscar dados finais

// ==========================================================================
// NOVO: Definição das Variáveis Fictícias do Mikrotik para a Simulação
// ==========================================================================
$mikrotikSimData = [
    'link-login-only' => 'http://hotspot.simulado.lan/login',  // URL de login que o Mikrotik usa
    'link-orig'       => 'http://www.google.com/test-redirect',// Destino original
    'chap-id'         => 'TESTE12345CHAPID',                   // ID de autenticação
    'chap-challenge'  => 'TESTE67890CHALLENGE',                // Challenge de autenticação
    'client_ip'       => '192.168.10.254',                     // IP do cliente
    'client_mac'      => '00:1A:2B:CC:DD:EE',                  // MAC do cliente
];
// ==========================================================================

// --------------------------------------------------------------------------
// FUNÇÃO SANITIZE (Omissa aqui, assumindo que está em config.php ou definida)
// Se 'sanitizeInput' não estiver definida, adicione-a aqui:
/*
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
*/
// --------------------------------------------------------------------------

// --------------------------------------------------------------------------
// BUSCA DE PLANOS (Executada em todas as etapas)
// Adicionamos 'duration_seconds' na busca para o debug
// --------------------------------------------------------------------------
$stmt_plan = $db->query("SELECT id, name, price, description, mikrotik_profile, duration_seconds FROM plans WHERE active = 1 ORDER BY price ASC");
$allPlans = $stmt_plan->fetchAll();

if (empty($allPlans)) {
    die("Erro: Nenhum plano ativo encontrado na tabela 'plans'. Por favor, crie um plano.");
}

// Se um plano foi selecionado, garante que o $selectedPlan tenha todos os dados
if ($selectedPlan) {
    $selectedPlan = array_filter($allPlans, fn($p) => $p['id'] == $selectedPlan['id']);
    $selectedPlan = reset($selectedPlan);
    $_SESSION['selected_plan'] = $selectedPlan;
}
// --------------------------------------------------------------------------

// --------------------------------------------------------------------------
// ETAPA 1: Seleção de Plano
// --------------------------------------------------------------------------
if (isset($_POST['select_plan'])) {
    $planId = intval($_POST['plan_id']);
    $planData = array_filter($allPlans, fn($p) => $p['id'] == $planId);
    if (!empty($planData)) {
        $selectedPlan = reset($planData);
        $_SESSION['selected_plan'] = $selectedPlan;
        $_SESSION['test_step'] = 2;
        $step = 2;
        $message = "Plano **{$selectedPlan['name']}** selecionado. Prossiga para o cadastro.";
    } else {
        $message = "Erro: Plano inválido.";
    }
}
// --------------------------------------------------------------------------

// --------------------------------------------------------------------------
// ETAPA 2: Cadastro de Cliente e Criação da Transação
// --------------------------------------------------------------------------
if (isset($_POST['process_client']) && $step == 2) {
    // Validação básica
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $phone = preg_replace('/[^0-9]/', '', (string)$_POST['phone']);
    $cpf = preg_replace('/[^0-9]/', '', (string)$_POST['cpf']);

    $clientData = compact('name', 'email', 'phone', 'cpf');

    // Simulação da chamada ao 'process_payment_infinity.php'
    try {
        $db->beginTransaction();
        
        // 2.1. Inserir/Buscar Cliente
        $stmt_customer = $db->prepare("SELECT id FROM customers WHERE cpf = ?");
        $stmt_customer->execute([$cpf]);
        $customerId = $stmt_customer->fetchColumn();
        
        if (!$customerId) {
            $stmt_insert = $db->prepare("INSERT INTO customers (name, email, phone, cpf) VALUES (?, ?, ?, ?)");
            $stmt_insert->execute([$name, $email, $phone, $cpf]);
            $customerId = $db->lastInsertId();
        }

        // 2.2. Criar Transação (Status 'pending' inicial)
        $stmt_trans = $db->prepare("
            INSERT INTO transactions (
                customer_id, plan_id, amount, payment_status,
                mikrotik_link_login_only, mikrotik_link_orig,
                mikrotik_chap_id, mikrotik_chap_challenge,
                client_ip, client_mac, created_at, updated_at
            ) VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt_trans->execute([
            $customerId, $selectedPlan['id'], $selectedPlan['price'],
            $mikrotikSimData['link-login-only'], $mikrotikSimData['link-orig'],
            $mikrotikSimData['chap-id'], $mikrotikSimData['chap-challenge'],
            $mikrotikSimData['client_ip'], $mikrotikSimData['client_mac']
        ]);
        $transactionId = $db->lastInsertId();
        
        // Simulação do payload de retorno da InfinitePay (redirecionamento)
        $redirectUrl = "http://checkout.simulado.lan/pay/" . $transactionId;
        
        $db->commit();
        
        // Armazenar dados para a próxima etapa
        $_SESSION['test_transaction_id'] = $transactionId;
        $_SESSION['client_data'] = $clientData;
        $_SESSION['test_step'] = 3;
        $step = 3;
        $message = "Cliente e Transação (**#{$transactionId}**) criados com sucesso. Redirecionamento simularia o checkout.";

    } catch (Exception $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        $message = "Erro na Etapa 2: " . $e->getMessage();
    }
}

// --------------------------------------------------------------------------
// ETAPA 3: Simulação do Checkout
// --------------------------------------------------------------------------
if (isset($_POST['simulate_checkout']) && $step == 3) {
    // Payload Fictício (Simulando o Webhook da InfinitePay de APROVAÇÃO)
    $fictitiousPayload = [
        'event' => 'invoice_paid',
        'order_nsu' => $transactionId,
        'invoice_slug' => 'INV_' . time(),
        'transaction_nsu' => 'TXN_' . time(),
        'status' => 'paid',
        'capture_method' => 'infinitepay_checkout',
        'customer' => ['email' => $clientData['email']]
    ];
    $_SESSION['fictitious_payload'] = $fictitiousPayload;
    $_SESSION['test_step'] = 4;
    $step = 4;
    $message = "Payload de Webhook (Status **PAID**) simulado e pronto para ser processado.";
}

// --------------------------------------------------------------------------
// ETAPA 4: Simulação do Webhook (`webhook_infinitypay.php`)
// --------------------------------------------------------------------------
if (isset($_POST['process_webhook']) && $step == 4 && $fictitiousPayload) {
    try {
        $db->beginTransaction();
        
        // 4a. Buscar transação APENAS da tabela transactions
        $stmt_trans = $db->prepare("SELECT * FROM transactions WHERE id = ? AND payment_status = 'pending'");
        $stmt_trans->execute([$transactionId]);
        $transaction = $stmt_trans->fetch();

        if (!$transaction) {
            throw new Exception("Transação #{$transactionId} não encontrada ou status incorreto.");
        }

        // 4b. Buscar detalhes do plano (mikrotik_profile e duration_seconds)
        $stmt_plan = $db->prepare("SELECT mikrotik_profile, duration_seconds FROM plans WHERE id = ?"); 
        $stmt_plan->execute([$transaction['plan_id']]);
        $plan = $stmt_plan->fetch();

        if (!$plan) {
            throw new Exception("Plano ID {$transaction['plan_id']} não encontrado.");
        }
        
        // 4c. Simular Ativação Cliente (Criação de Usuário Hotspot)
        $mt = new MikrotikAPI();
        // A função provisionHotspotUser está sendo mockada para retornar um sucesso
        $provisionResult = [
            'success' => true,
            'username' => 'user' . $transactionId,
            'password' => 'pass' . $transactionId,
            'mikrotik_profile' => $plan['mikrotik_profile'],
            'message' => 'Usuário provisionado (simulado).'
        ];

        $_SESSION['mikrotik_call_params'] = [
            'plan_id' => $transaction['plan_id'],
            'transaction_id' => $transactionId,
            'client_ip' => $transaction['client_ip'],
            'client_mac' => $transaction['client_mac']
        ];
<<<<<<< Updated upstream
=======
        
        // 🚨 ESTA É A CHAMADA REAL QUE VOCÊ SOLICITOU (ATUALIZADA)
        $provisionResult = $mt->provisionHotspotUser(
            $transaction['plan_id'],
            $transactionId,  // ID da venda/transação
            $transaction['client_ip'] ?? '',
            $transaction['client_mac'] ?? ''
        );
        
>>>>>>> Stashed changes
        $_SESSION['mikrotik_call_result'] = $provisionResult;

        if (!$provisionResult['success']) {
            throw new Exception("Falha ao provisionar usuário no Mikrotik (simulado).");
        }
        
        // ======================================================================
        // CÁLCULO E INSERÇÃO DE CREDENCIAIS (LÓGICA CORRIGIDA DO WEBHOOK)
        // ======================================================================
        $durationSeconds = intval($plan['duration_seconds']);
        $hasDuration = $durationSeconds > 0;

        $expiresAt = NULL;
        if ($hasDuration) {
            // CÁLCULO IDÊNTICO AO QUE ESTÁ NO webhook_infinitypay.php CORRIGIDO
            $expiresAt = date('Y-m-d H:i:s', time() + $durationSeconds);
        }

        $insertColumns = "transaction_id, plan_id, customer_id, username, password, mikrotik_profile, expires_at, created_at"; 
        $insertPlaceholders = "?, ?, ?, ?, ?, ?, ?, NOW()";

        $params = [
            $transactionId,
            $transaction['plan_id'],
            $transaction['customer_id'],
            $provisionResult['username'],
            $provisionResult['password'],
            $provisionResult['mikrotik_profile'],
            $expiresAt // <-- A data já calculada ou NULL
        ];
        
        // 4d. Salvar CREDENCIAIS no banco de dados (Tabela hotspot_users)
        $insertSql = "INSERT INTO hotspot_users ({$insertColumns}) VALUES ({$insertPlaceholders})";
        $stmt = $db->prepare($insertSql);
        $stmt->execute($params);

        // 4e. ATUALIZAR STATUS DA TRANSAÇÃO
        $stmt = $db->prepare("
            UPDATE transactions 
            SET payment_status = 'approved',
                infinitypay_order_id = ?,
                paid_at = NOW(),
                gateway = 'infinitepay_checkout',
                payment_method = ?,
                payment_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $fictitiousPayload['transaction_nsu'],
            $fictitiousPayload['capture_method'],
            $fictitiousPayload['invoice_slug'],
            $transactionId
        ]);
        
        $db->commit();
<<<<<<< Updated upstream
        $message = "Webhook SUCESSO! Usuário **{$provisionResult['username']}** criado no `hotspot_users`.";
=======
        $message = "Webhook SUCESSO! Usuário **{$provisionResult['username']}** criado. **A chamada real do Mikrotik foi executada com o comentário atualizado.**";
>>>>>>> Stashed changes
        
        // Buscar dados finais para display
        $stmt_final = $db->prepare("SELECT * FROM hotspot_users WHERE transaction_id = ?");
        $stmt_final->execute([$transactionId]);
        $_SESSION['transacao_final'] = $stmt_final->fetch() ?: [];

    } catch (Exception $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        $message = "Webhook FALHOU! Erro: " . $e->getMessage();
        $_SESSION['transacao_final'] = ['detailed_error' => $e->getMessage()];
    }
}
// --------------------------------------------------------------------------

// --------------------------------------------------------------------------
// Funções de Reset
// --------------------------------------------------------------------------
if (isset($_POST['reset'])) {
    // Lógica para apagar a transação atual do DB para evitar duplicação em testes
    if ($transactionId) {
        try {
            $db->exec("DELETE FROM hotspot_users WHERE transaction_id = {$transactionId}");
            $db->exec("DELETE FROM transactions WHERE id = {$transactionId}");
            $message .= "Transação #{$transactionId} e usuário do hotspot removidos do DB.";
        } catch (Exception $e) {
            $message .= "Erro ao tentar limpar o DB: " . $e->getMessage();
        }
    }
    
    $_SESSION['test_transaction_id'] = null;
    $_SESSION['test_step'] = 1;
    $_SESSION['selected_plan'] = null;
    $_SESSION['client_data'] = [];
    $_SESSION['fictitious_payload'] = null;
    $_SESSION['mikrotik_call_params'] = null;
    $_SESSION['mikrotik_call_result'] = null;
    $_SESSION['transacao_final'] = [];
    $transactionId = null;
    $step = 1;
    $message = "Fluxo de teste RESETADO. " . $message;
}
// --------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DEBUG: Fluxo de Venda Hotspot</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background-color: #f4f7f6; }
        .container { max-width: 900px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1, h2, h3 { color: #333; }
        h2 { border-bottom: 2px solid #eee; padding-bottom: 5px; margin-bottom: 15px; }
        .step { background-color: #e9ecef; padding: 15px; margin-bottom: 20px; border-radius: 6px; border-left: 5px solid #007bff; }
        .active-step { background-color: #d1ecf1; border-color: #00bcd4; }
        .message { padding: 10px; margin-bottom: 20px; border-radius: 4px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        form { margin-top: 15px; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        input[type="text"], input[type="email"], input[type="tel"], select { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; margin-top: 15px; }
        button:hover { background-color: #0056b3; }
        .reset-btn { background-color: #dc3545; float: right; }
        .reset-btn:hover { background-color: #c82333; }
        .debug-box { background: #f8f9fa; padding: 10px; border: 1px solid #ced4da; margin-top: 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Sistema de Debug: Fluxo de Venda</h1>
        <button class="reset-btn" onclick="document.getElementById('resetForm').submit();">RESETAR TUDO</button>
        <form id="resetForm" method="post" style="display: none;"><input type="hidden" name="reset" value="1"></form>
        <p>Transação Atual: **#<?php echo $transactionId ?: 'N/A'; ?>** (Etapa **<?php echo $step; ?>**)</p>
        
        <?php if ($message): ?>
        <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="step <?php echo $step == 1 ? 'active-step' : ''; ?>">
            <h2>1. Escolha do Plano</h2>
            <form method="post">
                <label for="plan_id">Plano:</label>
                <select id="plan_id" name="plan_id" required>
                    <?php foreach ($allPlans as $plan): ?>
                        <option value="<?php echo $plan['id']; ?>" <?php echo ($selectedPlan && $selectedPlan['id'] == $plan['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($plan['name']); ?> (R$ <?php echo number_format($plan['price'], 2, ',', '.'); ?>) - Perfil: <?php echo htmlspecialchars($plan['mikrotik_profile']); ?> (Duração: <?php echo number_format($plan['duration_seconds'], 0, ',', '.'); ?>s)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="select_plan" <?php echo $step != 1 ? 'disabled' : ''; ?>>Selecionar Plano</button>
            </form>
            <?php if ($selectedPlan): ?>
            <p class="debug-box">Plano Selecionado: **<?php echo htmlspecialchars($selectedPlan['name']); ?>**</p>
            <?php endif; ?>
        </div>

        <div class="step <?php echo $step == 2 ? 'active-step' : ''; ?>">
            <h2>2. Cadastro e Transação</h2>
            <form method="post">
                <input type="hidden" name="selected_plan_id" value="<?php echo $selectedPlan['id'] ?? ''; ?>">
                <label for="name">Nome:</label><input type="text" id="name" name="name" value="<?php echo $clientData['name'] ?? 'Teste Fluxo'; ?>" required>
                <label for="email">E-mail:</label><input type="email" id="email" name="email" value="<?php echo $clientData['email'] ?? 'teste@fluxo.com'; ?>" required>
                <label for="phone">Telefone:</label><input type="tel" id="phone" name="phone" value="<?php echo $clientData['phone'] ?? '123456789'; ?>" required>
                <label for="cpf">CPF (Cliente ID):</label><input type="text" id="cpf" name="cpf" value="<?php echo $clientData['cpf'] ?? '12345678901'; ?>" required>
                <button type="submit" name="process_client" <?php echo $step != 2 ? 'disabled' : ''; ?>>Criar Transação</button>
            </form>
            <?php if ($transactionId && $step >= 3): ?>
            <p class="debug-box">Transação **#<?php echo $transactionId; ?>** criada. Status inicial: **pending**.</p>
            <?php endif; ?>
        </div>

        <div class="step <?php echo $step == 3 ? 'active-step' : ''; ?>">
            <h2>3. Simulação de Checkout (Redirecionamento)</h2>
            <p>Simula o pagamento e o redirecionamento para o nosso sistema.</p>
            <form method="post">
                <button type="submit" name="simulate_checkout" <?php echo $step != 3 ? 'disabled' : ''; ?>>Simular Pagamento Aprovado</button>
            </form>
            <?php if ($fictitiousPayload): ?>
            <div class="debug-box">
                <p>Payload de Webhook simulado pronto: Status **<?php echo $fictitiousPayload['status']; ?>**</p>
                <hr>
                <h4 style="margin-top: 5px;">Conteúdo Completo do Payload:</h4>
                <pre style="white-space: pre-wrap; word-wrap: break-word;"><?php echo htmlspecialchars(json_encode($fictitiousPayload, JSON_PRETTY_PRINT)); ?></pre>
            </div>
            <?php endif; ?>
        </div>

        <div class="step <?php echo $step == 4 ? 'active-step' : ''; ?>">
            <h2>4. Processamento do Webhook (`webhook_infinitypay.php`)</h2>
            <p>Ação real que cria o usuário no `hotspot_users` (e chamaria o Mikrotik).</p>
            <form method="post">
                <button type="submit" name="process_webhook" <?php echo $step != 4 ? 'disabled' : ''; ?>>Processar Webhook</button>
            </form>

            <?php if (!empty($mikrotikCallParams)): ?>
                <hr>
                <h3>Parâmetros Enviados ao Mikrotik</h3>
                <div class="debug-box">
                    <pre><?php echo htmlspecialchars(json_encode($mikrotikCallParams, JSON_PRETTY_PRINT)); ?></pre>
                </div>
            <?php endif; ?>

            <?php if (!empty($mikrotikCallResult)): ?>
                <hr>
                <h3>Resultado da Simulação do Mikrotik</h3>
                <p>Usuário Hotspot: **<?php echo htmlspecialchars($mikrotikCallResult['username'] ?? 'N/A'); ?>**</p>
                <p>Senha: **<?php echo htmlspecialchars($mikrotikCallResult['password'] ?? 'N/A'); ?>**</p>
            <?php endif; ?>
            
            <?php if ($transacaoFinal['detailed_error'] ?? false): ?>
                 <h3 style="color: red;">Log de Erro Detalhado (Tabela `logs`)</h3>
                 <pre style="background: #ffeded; border: 1px solid #ffaaaa;"><?php echo htmlspecialchars($transacaoFinal['detailed_error']); ?></pre>
                 <p>Este é o erro **exato** que o processo logou.</p>
            <?php endif; ?>
        </div>
        
        
        <?php if ($transactionId && $step >= 4): ?>
        <hr>
        <div style="margin-top: 30px;">
            <h2>Simulação da Tela de Sucesso (`payment_success.php`)</h2>
            
            <div style="margin-bottom: 20px; background: #fff3cd; border: 1px solid #ffeeba; padding: 15px; border-radius: 8px;">
                <h3 style="margin-bottom: 10px; color: #856404;">🔍 Debug de Expiração (Calculado vs. Salvo)</h3>
                <?php 
                // 1. Buscar a duração do plano (garantindo que estamos usando o valor mais recente)
                $durationSeconds = 0;
                if ($selectedPlan && isset($selectedPlan['id'])) {
                    $stmt_plan_debug = $db->prepare("SELECT duration_seconds FROM plans WHERE id = ?");
                    $stmt_plan_debug->execute([$selectedPlan['id']]);
                    $planDebugData = $stmt_plan_debug->fetch();
                    if ($planDebugData) {
                        $durationSeconds = intval($planDebugData['duration_seconds']);
                    }
                }
                
                // 2. Calcular a expiração (o que o webhook DEVERIA ter feito AGORA)
                $calculatedExpiresAt = 'N/A (Duração = 0 ou Plano não selecionado)';
                if ($durationSeconds > 0) {
                    $calculatedTimestamp = time() + $durationSeconds;
                    $calculatedExpiresAt = date('Y-m-d H:i:s', $calculatedTimestamp);
                }
                
                // 3. Buscar a expiração SALVA no DB (hotspot_users)
                $dbExpiresAt = 'N/A (Usuário não criado)';
                $stmt_hotspot = $db->prepare("SELECT expires_at FROM hotspot_users WHERE transaction_id = ?");
                $stmt_hotspot->execute([$transactionId]);
                $hotspotUser = $stmt_hotspot->fetch();
                if ($hotspotUser) {
                    $dbExpiresAt = $hotspotUser['expires_at'] ?? 'NULL/Vazio';
                }
                
                // 4. Mostrar Resultados
                ?>
                <p><strong>Duração do Plano (Segundos):</strong> **<?php echo number_format($durationSeconds, 0, ',', '.'); ?>**</p>
                <p style="color: #004085;">
                    **Expiração CALCULADA (NOW + Duração):** <?php echo $calculatedExpiresAt; ?>
                </p>
                <p style="color: #155724;">
                    **Expiração SALVA no DB** (`hotspot_users`.`expires_at`): 
                    <?php echo $dbExpiresAt; ?>
                </p>
                <?php 
                // Verifica se a data salva é o momento atual (erro comum)
                $isSavedDateCloseToNow = ($dbExpiresAt !== 'N/A (Usuário não criado)' && str_starts_with($dbExpiresAt, date('Y-m-d')));
                
                // LINHA CORRIGIDA
                if ($calculatedExpiresAt !== 'N/A (Duração = 0 ou Plano não selecionado)' && $dbExpiresAt !== 'N/A (Usuário não criado)' && $isSavedDateCloseToNow && abs(strtotime($dbExpiresAt) - time()) < 10) : 
                ?>
                    <p style="color: red; font-weight: bold; margin-top: 10px;">
                        ⚠️ ERRO CRÍTICO DETECTADO: O valor SALVO no DB (`<?php echo $dbExpiresAt; ?>`) é o momento atual, ignorando a duração de **<?php echo number_format($durationSeconds, 0, ',', '.'); ?>** segundos. O problema está na query de inserção do `webhook_infinitypay.php` (a coluna `expires_at` não está recebendo a data futura, possivelmente devido a um `DEFAULT` incorreto ou erro de `INSERT`).
                    </p>
                <?php endif; ?>
            </div>
            <p>Este frame carrega o arquivo `payment_success.php` usando o ID da transação atual (<strong>#<?php echo $transactionId; ?></strong>) como parâmetro `external_reference`.</p>
            <p><strong>Teste de Sucesso:</strong> Se o Webhook (Etapa 4) foi bem-sucedido, o formulário de login dentro deste frame deve usar os dados do Mikrotik salvos na transação (**`http://hotspot.simulado.lan/login`**, etc.).</p>
            <iframe 
                src="payment_success.php?external_reference=<?php echo $transactionId; ?>" 
                width="100%" 
                height="600px" 
                style="border: 1px solid #ccc; border-radius: 8px;"
                frameborder="0"
            ></iframe>
        </div>
        <?php endif; // Fim Simulação da Tela de Sucesso ?>
    </div>
</body>
</html>