<?php
// test_api_log.php - Script para isolar e diagnosticar a falha de log e API

// Assegura que config.php e InfinityPay.php sejam carregados
require_once 'config.php';
require_once 'InfinityPay.php';

echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnóstico InfinitePay Checkout</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        h2 { border-bottom: 2px solid #ccc; padding-bottom: 5px; }
        .success { color: green; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .box { border: 1px solid #ddd; padding: 15px; background: #f9f9f9; }
    </style>
</head>
<body>
<h1>Diagnóstico de Conexão InfinitePay</h1>";

// O sistema de logs deve funcionar antes de tudo
echo "<h2>Testando Sistema de Log...</h2>";
try {
    logEvent('system_test', "TESTE 1: Log de teste bem-sucedido.");
    echo "<p class='success'>✅ Sucesso: Uma entrada 'system_test' deve ter sido criada na sua tabela logs.</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ FALHA CRÍTICA NO LOG: A função logEvent() falhou. Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

echo "<h2>Testando Construtor da Classe InfinityPay...</h2>";

try {
    $ip = new InfinityPay();
    echo "<p class='success'>✅ Sucesso: O Handle foi lido corretamente do DB.</p>";
    
    // --- TESTE DE CRIAÇÃO DO LINK (Com ID de Transação Fictício) ---
    echo "<h2>Testando Criação de Checkout Link (Simulação)...</h2>";
    
    // Dados de teste para simular a requisição de pagamento
    $fictitiousPlan = ['price' => 10.00, 'name' => 'Plano Teste'];
    $fictitiousCustomer = [
        'name' => 'Cliente Teste',
        // O e-mail precisa ser único/aleatório para simular um novo cliente
        'email' => 'teste' . time() . '@teste.com', 
        'phone' => '11999999999',
        // IMPORTANTE: Use um CPF com 11 dígitos, se o ambiente exige validação
        'cpf' => '99999999999' 
    ];
    // Use um ID de transação alto para não conflitar com transações reais
    $fictitiousTransactionId = 9999999; 

    // CHAVE DA SOLUÇÃO: AQUI O ERRO DE CÚRL ACONTECE E É CAPTURADO
    $result = $ip->createCheckoutLink($fictitiousPlan, $fictitiousCustomer, $fictitiousTransactionId);

    if ($result['success']) {
        echo "<p class='success'>✅ SUCESSO TOTAL: A comunicação com a API InfinitePay funcionou!</p>";
        echo "<div class='box'><p>Link de Pagamento Gerado: <a href='" . htmlspecialchars($result['url']) . "' target='_blank'>Abrir Link</a></p></div>";
    } else {
        echo "<p class='warning'>⚠️ FALHA na criação do link.</p>";
        echo "<div class='box'><p>Mensagem de Erro Capturada: <strong>" . htmlspecialchars($result['message']) . "</strong></p></div>";
        echo "<p>Verifique o log **infinitepay_api_debug** na sua tabela logs (se o erro não for de cURL).</p>";
    }

} catch (Exception $e) {
    echo "<h2><p class='error'>❌ ERRO CRÍTICO FATAL ENCONTRADO!</p></h2>";
    echo "<div class='box'>";
    echo "<p><strong>Causa da Falha (Erro de cURL/Sistema):</strong></p>";
    echo "<p class='error'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Próximo Passo:</strong> A mensagem acima (o erro do cURL) é a causa raiz. Isso geralmente é um bloqueio de rede (Firewall) ou problema de certificado SSL/cURL no seu servidor.</p>";
    echo "</div>";
}

echo "<h2>Fim do Diagnóstico.</h2>";
echo "</body></html>";
?>