<?php
// app/models/HotspotUser.php

class HotspotUser {
    protected $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Cria um novo usuário de hotspot no banco de dados.
     * @param int $transactionId
     * @param int $customerId
     * @param int $planId
     * @param string $username
     * @param string $password
     * @param string $expiresAt Data de expiração no formato 'Y-m-d H:i:s'
     * @return int O ID do novo usuário criado.
     */
    public function create($transactionId, $customerId, $planId, $username, $password, $expiresAt) {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO hotspot_users (transaction_id, customer_id, plan_id, username, password, expires_at, mikrotik_synced)
                 VALUES (?, ?, ?, ?, ?, ?, 1)"
            );
            $stmt->execute([$transactionId, $customerId, $planId, $username, $password, $expiresAt]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Erro ao criar usuário de hotspot: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Busca um usuário de hotspot pelo ID da transação.
     * @param int $transactionId
     * @return mixed Os dados do usuário ou false se não for encontrado.
     */
    public function findByTransactionId($transactionId) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM hotspot_users WHERE transaction_id = ?");
            $stmt->execute([$transactionId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Erro ao buscar usuário por ID de transação: " . $e->getMessage());
            return false;
        }
    }
}
