<?php
// test_ip_detection.php - Teste de detecção de IP mais robusto
// Simula a estrutura de método de classe para testar a detecção de IP.

header('Content-Type: application/json; charset=utf-8');

/**
 * Classe utilitária que contém a lógica de detecção de IP.
 * (Se a função detectClientIP estivesse realmente em MikrotikAPI,
 * ela seria chamada de forma similar, mas exigiria o include de 'MikrotikAPI.php').
 */
class IPDetectorUtility {

    /**
     * Verifica se um IP pertence a uma faixa de rede privada (RFC 1918 - apenas IPv4).
     */
    public static function isPrivateIP(string $ip): bool {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        
        $private_ranges = [
            ['10.0.0.0', '10.255.255.255'],
            ['172.16.0.0', '172.31.255.255'],
            ['192.168.0.0', '192.168.255.255'],
        ];

        $ip_long = ip2long($ip);
        if ($ip_long === false) {
            return false;
        }
        
        foreach ($private_ranges as $range) {
            $start = ip2long($range[0]);
            $end = ip2long($range[1]);
            
            if ($ip_long >= $start && $ip_long <= $end) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Tenta detectar o IP real do cliente usando a lógica de prioridades (Hotspot V4 Privado > Público V4 > IPv6).
     * @return array Um array contendo o IP selecionado, sua fonte e todos os candidatos.
     */
    public static function detectClientIP(): array {
        $all_headers = [];
        // Coleta todos os headers HTTP e variáveis importantes
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0 || in_array($key, ['REMOTE_ADDR', 'QUERY_STRING', 'REQUEST_METHOD'])) {
                $all_headers[$key] = $value;
            }
        }

        // Lista de prioridades de headers
        $header_priorities = [
            'HTTP_CF_CONNECTING_IP',   // Cloudflare Tunnel
            'HTTP_X_REAL_IP',          // Nginx/Load Balancer
            'HTTP_X_FORWARDED_FOR',    // Standard Proxy (o primeiro IP é o cliente)
            'REMOTE_ADDR'              // Último recurso
        ];

        $ip_candidates = [];
        $processed_ips = [];
        
        // 1. ANÁLISE DE HEADERS E CANDIDATOS A IP
        foreach ($header_priorities as $header) {
            $value = $_SERVER[$header] ?? null;

            if ($value) {
                // Tratamento de múltiplos IPs em X-Forwarded-For
                if (in_array($header, ['HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED'])) {
                    $ips = array_map('trim', explode(',', $value));
                    $ip_list = $ips; 

                    foreach ($ip_list as $ip) {
                        if (filter_var($ip, FILTER_VALIDATE_IP) && !in_array($ip, $processed_ips)) {
                            $is_v4 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 'SIM' : 'NÃO';
                            $is_private = $is_v4 === 'SIM' ? (self::isPrivateIP($ip) ? 'SIM' : 'NÃO') : 'N/A';
                            
                            $ip_candidates[] = [
                                'source' => $header . ' (Extraído: ' . $ip . ')',
                                'ip' => $ip,
                                'is_v4' => $is_v4,
                                'private_v4_rfc1918' => $is_private,
                            ];
                            $processed_ips[] = $ip;
                        }
                    }
                } else {
                    // Outros headers
                    $ip = $value;
                    if (filter_var($ip, FILTER_VALIDATE_IP) && !in_array($ip, $processed_ips)) {
                        $is_v4 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 'SIM' : 'NÃO';
                        $is_private = $is_v4 === 'SIM' ? (self::isPrivateIP($ip) ? 'SIM' : 'NÃO') : 'N/A';
                        
                        $ip_candidates[] = [
                            'source' => $header,
                            'ip' => $ip,
                            'is_v4' => $is_v4,
                            'private_v4_rfc1918' => $is_private,
                        ];
                        $processed_ips[] = $ip;
                    }
                }
            }
        }
        
        // 2. SELEÇÃO DO IP (Prioridade: Privado V4 > Público V4 > IPv6)
        $selected_ip = '0.0.0.0';
        $selected_source = 'Nenhum IP válido detectado';

        // 2a. Prioriza IP V4 Privado (CRÍTICO para Hotspot Bypass)
        foreach ($ip_candidates as $candidate) {
            if ($candidate['is_v4'] === 'SIM' && $candidate['private_v4_rfc1918'] === 'SIM') {
                $selected_ip = $candidate['ip'];
                $selected_source = $candidate['source'] . ' (Melhor Opção: V4 Privado)';
                break; 
            }
        }

        // 2b. Se não encontrou privado, pega o primeiro IP V4 Válido
        if ($selected_ip === '0.0.0.0') {
            foreach ($ip_candidates as $candidate) {
                if ($candidate['is_v4'] === 'SIM') {
                    $selected_ip = $candidate['ip'];
                    $selected_source = $candidate['source'] . ' (Melhor Opção: V4 Público)';
                    break; 
                }
            }
        }

        // 2c. Se ainda não encontrou IP V4, tenta o primeiro IP V6 Válido
        if ($selected_ip === '0.0.0.0') {
            foreach ($ip_candidates as $candidate) {
                if ($candidate['is_v4'] === 'NÃO') { // Se não é V4, é V6
                    $selected_ip = $candidate['ip'];
                    $selected_source = $candidate['source'] . ' (Opção V6)';
                    break;
                }
            }
        }
        
        return [
            'ip_selecionado' => $selected_ip,
            'fonte_selecionada' => $selected_source,
            'candidatos_analisados' => $ip_candidates,
            'headers_brutos' => $all_headers,
        ];
    }
}

// ======================================================================
// CHAMADA E EXIBIÇÃO DA FUNÇÃO (Simulando a Instanciação)
// ======================================================================

// O modo mais limpo e rápido para uma função de utilidade é usar a chamada estática:
$detection_data = IPDetectorUtility::detectClientIP();

// Se a função fosse um método de instância (ex: public function detectClientIP()),
// a chamada seria:
// $detector = new IPDetectorUtility(); 
// $detection_data = $detector->detectClientIP();

// Tenta obter o MAC do Hotspot (que deve vir via GET ou POST no index.php)
$mac_info = [
    'obtido_via_get' => $_GET['mac'] ?? 'N/A',
    'obtido_via_post' => ($_POST['client_mac'] ?? $_POST['mac']) ?? 'N/A',
    'comentario_mac' => 'O MAC Address é obtido APENAS se o Hotspot (MikroTik) o injetar na URL (GET) ou no formulário (POST).',
];


$result = [
    'timestamp' => date('Y-m-d H:i:s'),
    
    'RESULTADO_DA_FUNCAO_detectClientIP' => [
        'ip_selecionado' => $detection_data['ip_selecionado'],
        'fonte_selecionada' => $detection_data['fonte_selecionada'],
        'eh_ip_privado_v4' => IPDetectorUtility::isPrivateIP($detection_data['ip_selecionado']) ? 'SIM (Rede Local Hotspot)' : 'NÃO (IP Público ou IPv6)',
        'adequado_para_bypass' => IPDetectorUtility::isPrivateIP($detection_data['ip_selecionado']) ? 'SIM ✅ (IP V4 Local é Requisito)' : 'NÃO ⚠️ (O IP Binding pode falhar)',
    ],
    
    'MAC_ADDRESS_DO_HOTSPOT' => $mac_info,
    
    'DIAGNOSTICO_COMPLETO' => [
        'candidatos_analisados' => $detection_data['candidatos_analisados'],
        'headers_brutos' => $detection_data['headers_brutos'],
    ],
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>