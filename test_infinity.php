<?php
require_once 'config.php';
require_once 'InfinityPay.php';

try {
    // Assume que a transação ID 97 é sua transação de teste
    $ipApi = new InfinityPay();

    // Simule a busca por um slug. ATENÇÃO: Troque "SEU_INVOICE_SLUG_AQUI" pelo slug de uma fatura real.
    $result = $ipApi->getInvoiceStatus("6aNVEl2dD3"); 

    if ($result['success']) {
        echo "Sucesso: Consulta à InfinitePay OK. Status: " . ($result['data']['status'] ?? 'N/A');
    } else {
        echo "Erro na consulta: " . $result['message'];
    }

} catch (Exception $e) {
    echo "Erro: Exceção na InfinitePay: " . $e->getMessage();
}
?>