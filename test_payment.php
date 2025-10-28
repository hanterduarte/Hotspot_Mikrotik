<?php
require_once 'config.php';

// Buscar última transação
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT * FROM transactions ORDER BY created_at DESC LIMIT 1");
$transaction = $stmt->fetch();

echo "<h2>🔍 Debug da Última Transação</h2>";

if ($transaction) {
    echo "<h3>Dados da Transação:</h3>";
    echo "<pre>";
    print_r($transaction);
    echo "</pre>";
    
    echo "<h3>Gateway Response (JSON):</h3>";
    echo "<pre>";
    $response = json_decode($transaction['gateway_response'], true);
    print_r($response);
    echo "</pre>";
    
    if (isset($response['init_point'])) {
        echo "<h3>✅ URL de Pagamento Encontrada:</h3>";
        echo "<a href='{$response['init_point']}' target='_blank' style='padding: 10px 20px; background: #009ee3; color: white; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0;'>Abrir Página de Pagamento</a>";
    } else {
        echo "<h3>❌ URL de Pagamento NÃO encontrada no response</h3>";
    }
    
    // Verificar configurações do Mercado Pago
    echo "<h3>Configurações Mercado Pago:</h3>";
    $stmt = $db->query("SELECT * FROM settings WHERE setting_key LIKE 'mercadopago%'");
    $settings = $stmt->fetchAll();
    
    echo "<ul>";
    foreach ($settings as $setting) {
        $value = $setting['setting_value'];
        if (!empty($value)) {
            // Ocultar parcialmente as credenciais
            $value = substr($value, 0, 20) . '...' . substr($value, -10);
        } else {
            $value = "<span style='color: red;'>VAZIO - CONFIGURE!</span>";
        }
        echo "<li><strong>{$setting['setting_key']}:</strong> $value</li>";
    }
    echo "</ul>";
    
} else {
    echo "<p>❌ Nenhuma transação encontrada</p>";
}

// Verificar logs recentes
echo "<h3>📋 Logs Recentes (últimos 10):</h3>";
$stmt = $db->query("SELECT * FROM logs ORDER BY created_at DESC LIMIT 10");
$logs = $stmt->fetchAll();

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

echo "<br><br>";
echo "<a href='index.php' style='padding: 10px 20px; background: #1e88e5; color: white; text-decoration: none; border-radius: 5px;'>Voltar ao Início</a>";
?>