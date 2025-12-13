<?php
// app/models/Plan.php

class Plan {
    protected $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Busca todos os planos ativos no banco de dados.
     * @return array Uma lista de planos.
     */
    public function getActivePlans() {
        try {
            $stmt = $this->db->query("SELECT * FROM plans WHERE active = 1 ORDER BY price ASC");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            // Em uma aplicação real, logaríamos o erro.
            // Por enquanto, retornamos um array vazio para evitar que a aplicação quebre.
            error_log("Erro ao buscar planos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca um plano específico pelo seu ID.
     * @param int $id O ID do plano.
     * @return mixed Os dados do plano ou false se não for encontrado.
     */
    public function findById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM plans WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Erro ao buscar plano por ID: " . $e->getMessage());
            return false;
        }
    }
}
