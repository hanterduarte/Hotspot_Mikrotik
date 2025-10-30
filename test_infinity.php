<?php
// test_infinity.php - Script de diagnóstico Atualizado

require_once 'config.php';
require_once 'InfinityPay.php';

header('Content-Type: text/plain');

echo "====================================================================\n";
echo "           DIAGNÓSTICO DE CONFIGURAÇÃO - INFINITEPAY HANDLE           \n";
echo "====================================================================\n\n";

echo "➡️ [1/3] FUNÇÃO getSetting() ATUAL (Lógica no config.php)\n";
echo "--------------------------------------------------------------------\n";
echo "A função agora busca com a consulta CORRETA:\n";
echo "**SELECT setting_value FROM settings WHERE setting_key = ?**\n";
echo "--------------------------------------------------------------------\n";


echo "\n➡️ [2/3] CONSULTA SQL EXECUTADA (Para a chave 'infinitepay_handle')\n";
echo "--------------------------------------------------------------------\n";
echo "Consulta Completa que está sendo enviada ao Banco de Dados:\n";
echo "**SELECT setting_value FROM settings WHERE setting_key = 'infinitepay_handle'**\n";
echo "--------------------------------------------------------------------\n";


// --------------------------------------------------------------------
// ONDE O getSetting() É CHAMADO
// --------------------------------------------------------------------
$key = 'infinitepay_handle';
$handle = getSetting($key, '[HANDLE NÃO ENCONTRADO]');
// --------------------------------------------------------------------


echo "\n✅ [3/3] RESULTADO FINAL DO getSetting()\n";
echo "--------------------------------------------------------------------\n";
echo "Valor retornado para a chave '$key' (Coluna setting_value):\n";
echo ">>> **" . $handle . "** <<<\n";
echo "--------------------------------------------------------------------\n\n";


// --------------------------------------------------------------------
// Teste de Instanciação da Classe
// --------------------------------------------------------------------
if ($handle === '[HANDLE NÃO ENCONTRADO]') {
    echo "⚠️  **ERRO CRÍTICO:** O Handle não foi encontrado ou é NULL no seu DB.\n";
    echo "A Classe InfinityPay VAI FALHAR, pois o construtor exige este handle.\n\n";
} else {
    echo "✅ **SUCESSO:** Handle carregado. Tentando instanciar a classe InfinityPay...\n\n";

    try {
        // Esta chamada usará o handle que você acabou de confirmar que existe
        $ipApi = new InfinityPay(); 
        echo "Status da Classe InfinityPay: OK. Handle carregado com sucesso internamente.\n\n";
        
        // ... (restante do código para teste de API, se quiser mantê-lo)

    } catch (Exception $e) {
        echo "❌ **ERRO:** Exceção na Instanciação: " . $e->getMessage() . "\n";
    }
}

echo "\n====================================================================\n";
?>