<?php
/**
 * QuickBill POS - Base Model
 */

abstract class Model {

    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    protected $companyId;

    public function __construct() {
        $this->db        = Database::getInstance();
        $this->companyId = isset($_SESSION['company_id']) ? (int)$_SESSION['company_id'] : 1;
    }

    // ── Core CRUD ─────────────────────────────────────────────────────────

    public function find($id) {
        return $this->db->fetchOne(
            "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?",
            [$id]
        );
    }

    public function findAll($where = '', $params = [], $order = '', $limit = 0, $offset = 0) {
        $sql = "SELECT * FROM `{$this->table}`";
        if ($where) $sql .= " WHERE $where";
        if ($order) $sql .= " ORDER BY $order";
        if ($limit)  $sql .= " LIMIT $limit";
        if ($offset) $sql .= " OFFSET $offset";
        return $this->db->fetchAll($sql, $params);
    }

    public function findByCompany($where = '', $params = [], $order = '', $limit = 0, $offset = 0) {
        $compWhere = "`company_id` = ?";
        if ($where) $compWhere .= " AND ($where)";
        array_unshift($params, $this->companyId);
        return $this->findAll($compWhere, $params, $order, $limit, $offset);
    }

    public function insert($data) {
        $data['company_id'] = $data['company_id'] ?? $this->companyId;
        $cols = implode(', ', array_map(function($c) { return "`$c`"; }, array_keys($data)));
        $vals = implode(', ', array_fill(0, count($data), '?'));
        return $this->db->insert(
            "INSERT INTO `{$this->table}` ($cols) VALUES ($vals)",
            array_values($data)
        );
    }

    public function update($id, $data) {
        unset($data['id'], $data['company_id'], $data['created_at']);
        $set = implode(', ', array_map(function($c) { return "`$c` = ?"; }, array_keys($data)));
        $params = array_values($data);
        $params[] = $id;
        return $this->db->execute(
            "UPDATE `{$this->table}` SET $set WHERE `{$this->primaryKey}` = ?",
            $params
        );
    }

    public function delete($id) {
        return $this->db->execute(
            "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?",
            [$id]
        );
    }

    public function softDelete($id) {
        return $this->db->execute(
            "UPDATE `{$this->table}` SET `is_active` = 0 WHERE `{$this->primaryKey}` = ?",
            [$id]
        );
    }

    public function count($where = '', $params = []) {
        return $this->db->count($this->table, $where, $params);
    }

    public function countByCompany($where = '', $params = []) {
        $compWhere = "`company_id` = ?";
        if ($where) $compWhere .= " AND ($where)";
        array_unshift($params, $this->companyId);
        return $this->db->count($this->table, $compWhere, $params);
    }

    // ── Document Numbering ────────────────────────────────────────────────

    public function getNextDocNo($docType, $branchId = null) {
        $branchId = $branchId ?? ($_SESSION['branch_id'] ?? 1);
        $pdo = $this->db->getPdo();

        $pdo->beginTransaction();
        try {
            // Lock the row
            $stmt = $pdo->prepare(
                "SELECT * FROM doc_numbering
                 WHERE company_id = ? AND branch_id = ? AND doc_type = ?
                 FOR UPDATE"
            );
            $stmt->execute([$this->companyId, $branchId, $docType]);
            $row = $stmt->fetch();

            if (!$row) {
                $pdo->rollBack();
                return $docType . '-000001';
            }

            $newNo   = $row['current_no'] + 1;
            $padded  = str_pad($newNo, $row['min_digits'], '0', STR_PAD_LEFT);
            $docNo   = $row['prefix'] . $padded . $row['suffix'];

            // Check yearly reset
            $year = date('Y');
            if ($row['reset_yearly'] && $row['last_reset_year'] != $year) {
                $newNo  = 1;
                $padded = str_pad($newNo, $row['min_digits'], '0', STR_PAD_LEFT);
                $docNo  = $row['prefix'] . $padded . $row['suffix'];
                $pdo->prepare(
                    "UPDATE doc_numbering SET current_no = ?, last_reset_year = ?
                     WHERE company_id = ? AND branch_id = ? AND doc_type = ?"
                )->execute([$newNo, $year, $this->companyId, $branchId, $docType]);
            } else {
                $pdo->prepare(
                    "UPDATE doc_numbering SET current_no = ?
                     WHERE company_id = ? AND branch_id = ? AND doc_type = ?"
                )->execute([$newNo, $this->companyId, $branchId, $docType]);
            }

            $pdo->commit();
            return $docNo;

        } catch (Exception $e) {
            $pdo->rollBack();
            return $docType . '-ERR-' . time();
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function exists($where, $params = []) {
        return $this->db->count($this->table, $where, $params) > 0;
    }

    public function getPdo() {
        return $this->db->getPdo();
    }
}
