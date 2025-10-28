<?php
// debug_payment.php - Script para testar o fluxo de pagamento

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'InfinityPay.php';

echo "<h1>🔍 Debug do Sistema de Pagamento</h1>";

// Teste 1: Conexão com Banco de Dados
echo "<h2>1️⃣ Teste de Conexão com Banco de Dados</h2>";
try {
    $db = Database::getInstance()->getConnection();
    echo "✅ <strong>Conexão OK!</strong><br>";
} catch (Exception $e) {
    echo "❌ <strong>Erro:</strong> " . $e->getMessage() . "<br>";
    die();
}

// Teste 2: Verificar Tabelas
echo "<h2>2️⃣ Verificar Estrutura das Tabelas</h2>";
try {
    $stmt = $db->query("DESCRIBE transactions");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredColumns = ['id', 'customer_id', 'plan_id', 'payment_id', 'payment_method', 
                        'payment_status', 'amount', 'gateway', 'infinitypay_order_id'];
    
    echo "<strong>Colunas na tabela transactions:</strong><br>";
    foreach ($requiredColumns as $col) {
        if (in_array($col, $columns)) {
            echo "✅ $col<br>";
        } else {
            echo "❌ <strong style='color:red;'>$col FALTANDO!</strong><br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
}

// Teste 3: Configuração InfinityPay
echo "<h2>3️⃣ Verificar Configuração InfinityPay</h2>";
$infinityHandle = getSetting('infinitypay_handle');
if (!empty($infinityHandle)) {
    echo "✅ InfiniteTag configurada: <strong>" . htmlspecialchars($infinityHandle) . "</strong><br>";
} else {
    echo "❌ <strong style='color:red;'>InfiniteTag NÃO configurada!</strong><br>";
    echo "<p>Execute:</p>";
    echo "<pre>UPDATE settings SET setting_value = '@sua_infinitetag' WHERE setting_key = 'infinitypay_handle';</pre>";
}

// Teste 4: Buscar Planos
echo "<h2>4️⃣ Verificar Planos Ativos</h2>";
try {
    $stmt = $db->query("SELECT * FROM plans WHERE active = 1");
    $plans = $stmt->fetchAll();
    
    if (count($plans) > 0) {
        echo "✅ <strong>" . count($plans) . " planos encontrados:</strong><br>";
        foreach ($plans as $plan) {
            echo "- " . $plan['name'] . " (R$ " . number_format($plan['price'], 2, ',', '.') . ")<br>";
        }
    } else {
        echo "❌ Nenhum plano ativo encontrado!<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
}

// Teste 5: Testar Funções
echo "<h2>5️⃣ Testar Funções do Sistema</h2>";

echo "<strong>Função validateCPF:</strong> ";
if (validateCPF('12345678909')) {
    echo "✅ OK<br>";
} else {
    echo "❌ Erro<br>";
}

echo "<strong>Função validateEmail:</strong> ";
if (validateEmail('teste@example.com')) {
    echo "✅ OK<br>";
} else {
    echo "❌ Erro<br>";
}

echo "<strong>Função generateUsername:</strong> ";
$username = generateUsername('wifi');
if (!empty($username)) {
    echo "✅ OK (Exemplo: $username)<br>";
} else {
    echo "❌ Erro<br>";
}

echo "<strong>Função generatePassword:</strong> ";
$password = generatePassword(8);
if (!empty($password) && strlen($password) == 8) {
    echo "✅ OK (Exemplo: $password)<br>";
} else {
    echo "❌ Erro<br>";
}

// Teste 6: Testar InfinityPay
echo "<h2>6️⃣ Testar Classe InfinityPay</h2>";
try {
    $ip = new InfinityPay();
    echo "✅ Classe InfinityPay carregada com sucesso<br>";
} catch (Exception $e) {
    echo "❌ Erro ao carregar InfinityPay: " . $e->getMessage() . "<br>";
}

// Teste 7: Ver últimos logs
echo "<h2>7️⃣ Últimos Logs do Sistema</h2>";
try {
    $stmt = $db->query("SELECT * FROM logs ORDER BY created_at DESC LIMIT 5");
    $logs = $stmt->fetchAll();
    
    if (count($logs) > 0) {
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Tipo</th><th>Mensagem</th><th>Data</th></tr>";
        foreach ($logs as $log) {
            echo "<tr>";
            echo "<td>{$log['log_type']}</td>";
            echo "<td>" . htmlspecialchars($log['log_message']) . "</td>";
            echo "<td>{$log['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "Nenhum log encontrado.<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
}

// Teste 8: Simular criação de transação
echo "<h2>8️⃣ Testar Criação de Cliente e Transação</h2>";
try {
    $db->beginTransaction();
    
    // Dados de teste
    $customerData = [
        'name' => 'Cliente Teste Debug',
        'email' => 'teste_debug_' . time() . '@example.com',
        'phone' => '(81) 99999-9999',
        'cpf' => '12345678909'
    ];
    
    // Criar cliente
    $customerId = createOrGetCustomer($db, $customerData);
    echo "✅ Cliente criado com ID: <strong>$customerId</strong><br>";
    
    // Buscar plano
    $stmt = $db->prepare("SELECT * FROM plans WHERE active = 1 LIMIT 1");
    $stmt->execute();
    $plan = $stmt->fetch();
    
    if ($plan) {
        echo "✅ Plano selecionado: <strong>{$plan['name']}</strong><br>";
        
        // Criar transação
        $stmt = $db->prepare("
            INSERT INTO transactions (customer_id, plan_id, amount, payment_method, payment_status, gateway)
            VALUES (?, ?, ?, ?, 'pending', 'infinitypay')
        ");
        $stmt->execute([$customerId, $plan['id'], $plan['price'], 'test']);
        $transactionId = $db->lastInsertId();
        
        echo "✅ Transação criada com ID: <strong>$transactionId</strong><br>";
        
        // Rollback (não salvar no banco)
        $db->rollBack();
        echo "✅ Teste concluído (rollback executado - nada foi salvo)<br>";
    } else {
        echo "❌ Nenhum plano ativo encontrado para teste<br>";
        $db->rollBack();
    }
    
} catch (Exception $e) {
    $db->rollBack();
    echo "❌ <strong>Erro ao testar criação:</strong> " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h2>✅ Debug Concluído</h2>";
echo "<p><a href='index.php'>Voltar ao Início</a> | <a href='test_payment.php'>Ver Transações</a></p>";
?>