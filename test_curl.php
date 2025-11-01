<?php
// test_curl.php - Script para testar a conexão cURL pura com a API da InfinitePay

header('Content-Type: text/plain');

echo "========================================\n";
echo "TESTE DE CONEXÃO cURL PARA INFINITEPAY\n";
echo "========================================\n";

$url = 'https://api.infinitepay.io/invoices/public/checkout/links';

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout de 10 segundos
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
// Usamos um método POST simulado (sem dados válidos)
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$curlErrno = curl_errno($ch);

echo "\n➡️ RESULTADO DA CONEXÃO:\n";
if ($curlErrno !== 0) {
    echo "❌ FALHA CRÍTICA NO cURL!\n";
    echo "   Código de Erro (errno): " . $curlErrno . "\n";
    echo "   Mensagem de Erro (error): " . $curlError . "\n";
    echo "\n=> DIAGNÓSTICO: Isso é um bloqueio de rede/firewall ou problema de certificado SSL no seu servidor.\n";
} else {
    echo "✅ CONEXÃO BÁSICA OK (cURL não falhou).\n";
    echo "   Status HTTP Recebido: " . $httpCode . "\n";
    echo "   Resposta (Trecho): " . substr($response, 0, 100) . "...\n";
    echo "\n=> DIAGNÓSTICO: O problema é o Handle (@tag) incorreto ou o payload de dados.\n";
}

curl_close($ch);
echo "\n========================================\n";

?>