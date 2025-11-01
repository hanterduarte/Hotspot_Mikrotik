<?php
// test_handle_payload.php - Script isolado para diagnosticar falha no Handle

// Assume que config.php inclui as funções getSetting() e logEvent()
require_once 'config.php'; 

header('Content-Type: text/plain');

echo "========================================\n";
echo "TESTE ISOLADO: LEITURA DO HANDLE E PAYLOAD\n";
echo "========================================\n\n";

// 1. Tentar obter o handle
$handle = getSetting('infinitepay_handle');

echo "➡️ 1. Valor lido de getSetting('infinitepay_handle'):\n";
echo "   >>> " . (empty($handle) ? "[HANDLE VAZIO/NULL]" : $handle) . " <<<\n\n";

if (empty($handle)) {
    echo "❌ DIAGNÓSTICO CRÍTICO: O handle está vazio/NULL. O problema É a função getSetting() ou o valor no DB.\n";
    logEvent('ip_handle_test_vazio', 'O Handle está vazio no teste isolado. VERIFICAR DB E CONEXÃO.');
    exit;
}

// 2. Simular a criação do payload (usando dados de teste)
$data = [
    "handle" => $handle, // O Handle que acabamos de ler
    "redirect_url" => BASE_URL . "/payment_success.php?external_reference=TESTE",
    "webhook_url" => BASE_URL . "/webhook_infinitypay.php",
    "order_nsu" => "TESTE_HANDLE_" . time(),
    "customer" => ["name" => "Cliente Teste", "email" => "teste@exemplo.com"],
    "items" => [["quantity" => 1, "price" => 1000, "description" => "Plano Teste"]]
];

$payload = json_encode($data);

echo "➡️ 2. Payload JSON Gerado:\n";
echo $payload . "\n\n";

// 3. Executar a chamada cURL (Requisição para a API)
$url = 'https://api.infinitepay.io/invoices/public/checkout/links';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$curlErrno = curl_errno($ch);

echo "➡️ 3. Resultado da Chamada cURL:\n";
if ($curlErrno !== 0) {
    echo "   ❌ FALHA CRÍTICA NO cURL (Infraestrutura/Firewall)!\n";
    echo "      Mensagem de Erro: " . $curlError . "\n";
    logEvent('ip_curl_fatal_isolate', 'Erro cURL: ' . $curlError);
} else {
    echo "   ✅ CONEXÃO OK. Status HTTP: " . $httpCode . "\n";
    echo "   Resposta da API: " . $response . "\n";
    logEvent('ip_api_test_isolate', 'Status: ' . $httpCode . ' Response: ' . $response);
    
    // Análise da resposta
    $result = json_decode($response, true);
    if ($httpCode === 400 && strpos($response, 'handle') !== false) {
        echo "\n⚠️  DIAGNÓSTICO FINAL (PROBLEMA AMBIENTAL):\n";
        echo "   O Handle foi lido (Passo 1), enviado no Payload (Passo 2), mas a API AINDA responde 'Handle is Missing'.\n";
        echo "   Isso prova que um Firewall, Proxy ou regra de segurança no seu servidor de hospedagem está limpando o campo 'handle' da requisição HTTP de saída.\n";
        echo "   **SOLUÇÃO: Contatar a sua Hospedagem Imediatamente e pedir para liberarem requisições POST/JSON para api.infinitepay.io.**\n";
    }
}

curl_close($ch);
echo "\n========================================\n";

?>