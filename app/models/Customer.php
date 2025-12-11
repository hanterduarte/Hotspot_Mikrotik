<?php
// app/models/Customer.php

class Customer {
    protected $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Cria um novo cliente ou retorna o ID de um cliente existente com o mesmo email.
     * @param array $data Dados do cliente (name, email, phone, cpf)
     * @return int O ID do cliente.
     */
    public function createOrGet(array $data) {
        try {
            // 1. Tenta encontrar o cliente pelo email
            $stmt = $this->db->prepare("SELECT id FROM customers WHERE email = ?");
            $stmt->execute([$data['email']]);
            $customer = $stmt->fetch();

            if ($customer) {
                // Cliente encontrado, retorna o ID
                return $customer['id'];
            } else {
                // 2. Cliente não encontrado, cria um novo
                $stmt = $this->db->prepare(
                    "INSERT INTO customers (name, email, phone, cpf)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([
                    $data['name'],
                    $data['email'],
                    $data['phone'],
                    $data['cpf']
                ]);
                // Retorna o ID do novo cliente
                return $this->db->lastInsertId();
            }
        } catch (PDOException $e) {
            error_log("Erro em createOrGet Customer: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Busca um cliente pelo seu ID.
     * @param int $id O ID do cliente.
     * @return mixed Os dados do cliente ou false se não for encontrado.
     */
    public function findById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM customers WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Erro ao buscar cliente por ID: " . $e->getMessage());
            return false;
        }
    }
}
