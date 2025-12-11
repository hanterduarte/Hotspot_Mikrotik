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
    public function create($customerId, $planId, $amount, $gateway = 'infinitepay', $simulationData = []) {
        try {
            $sql = "INSERT INTO transactions (customer_id, plan_id, amount, gateway, payment_status,
                        sim_client_ip, sim_client_mac, sim_link_orig, sim_link_login_only, sim_chap_id, sim_chap_challenge)
                    VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql);

            $params = [
                $customerId,
                $planId,
                $amount,
                $gateway,
                $simulationData['client_ip'] ?? null,
                $simulationData['client_mac'] ?? null,
                $simulationData['link_orig'] ?? null,
                $simulationData['link_login_only'] ?? null,
                $simulationData['chap_id'] ?? null,
                $simulationData['chap_challenge'] ?? null
            ];

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
