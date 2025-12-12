<?php
// app/models/Transaction.php

class Transaction {
    protected $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Cria uma nova transação no banco de dados.
     * @param int $customerId
     * @param int $planId
     * @param float $amount
     * @param string $gateway
     * @return int O ID da nova transação.
     */
    public function create($customerId, $planId, $amount, $gateway = 'infinity_pay', $mikrotikData = []) {
        try {
            // Campos base da transação
            $columns = [
                'customer_id' => $customerId,
                'plan_id' => $planId,
                'amount' => $amount,
                'gateway' => $gateway,
                'payment_status' => 'pending'
            ];

            // Mapeia os dados do Mikrotik para os nomes das colunas no banco
            $mikrotikMapping = [
                'client_ip' => 'sim_client_ip',
                'client_mac' => 'sim_client_mac',
                'link_orig' => 'sim_link_orig',
                'link_login_only' => 'sim_link_login_only',
                'chap_id' => 'sim_chap_id',
                'chap_challenge' => 'sim_chap_challenge'
            ];

            // Adiciona os dados do Mikrotik à query apenas se eles existirem
            foreach ($mikrotikMapping as $inputKey => $dbColumn) {
                if (!empty($mikrotikData[$inputKey])) {
                    $columns[$dbColumn] = $mikrotikData[$inputKey];
                }
            }

            // Constrói a query SQL dinamicamente
            $columnNames = implode(', ', array_keys($columns));
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $sql = "INSERT INTO transactions ($columnNames) VALUES ($placeholders)";

            $stmt = $this->db->prepare($sql);

            // Pega apenas os valores para o execute()
            $params = array_values($columns);

            $stmt->execute($params);
            return $this->db->lastInsertId();

        } catch (PDOException $e) {
            error_log("Erro ao criar transação: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Atualiza o status e os detalhes de uma transação após o pagamento.
     * @param int $transactionId
     * @param string $paymentId
     * @param string $status
     * @param string $gatewayResponse
     * @return bool
     */
    public function updatePaymentDetails($transactionId, $paymentId, $status, $gatewayResponse = '') {
        try {
            $stmt = $this->db->prepare(
                "UPDATE transactions
                 SET payment_id = ?, payment_status = ?, gateway_response = ?, paid_at = NOW()
                 WHERE id = ?"
            );
            return $stmt->execute([$paymentId, $status, $gatewayResponse, $transactionId]);
        } catch (PDOException $e) {
            error_log("Erro ao atualizar detalhes do pagamento: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca uma transação pelo seu ID.
     * @param int $id
     * @return mixed
     */
    public function findById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM transactions WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Erro ao buscar transação por ID: " . $e->getMessage());
            return false;
        }
    }
}
