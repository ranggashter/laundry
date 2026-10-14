<?php
namespace App\Models;

use Database;
use PDO;

abstract class Model {
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected PDO $db;

    public function __construct() {
        require_once CONFIG_PATH . '/database.php';
        $this->db = Database::getConnection();
    }

    public function all(string $orderBy = ''): array {
        $sql = "SELECT * FROM `{$this->table}`";
        if (!empty($orderBy)) {
            $sql .= " ORDER BY {$orderBy}";
        } else {
            $sql .= " ORDER BY `{$this->primaryKey}` DESC";
        }
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function find($id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function where(string $column, $value, string $orderBy = ''): array {
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$column}` = :val";
        if (!empty($orderBy)) {
            $sql .= " ORDER BY {$orderBy}";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['val' => $value]);
        return $stmt->fetchAll();
    }

    public function firstWhere(string $column, $value): ?array {
        $stmt = $this->db->prepare("SELECT * FROM `{$this->table}` WHERE `{$column}` = :val LIMIT 1");
        $stmt->execute(['val' => $value]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data) {
        $columns = array_keys($data);
        $escapedColumns = array_map(fn($col) => "`$col`", $columns);
        $placeholders = array_map(fn($col) => ":$col", $columns);

        $sql = sprintf(
            "INSERT INTO `%s` (%s) VALUES (%s)",
            $this->table,
            implode(', ', $escapedColumns),
            implode(', ', $placeholders)
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        return $this->db->lastInsertId();
    }

    public function update($id, array $data): bool {
        $fields = [];
        foreach (array_keys($data) as $col) {
            $fields[] = "`$col` = :$col";
        }

        $sql = sprintf(
            "UPDATE `%s` SET %s WHERE `%s` = :primary_id",
            $this->table,
            implode(', ', $fields),
            $this->primaryKey
        );

        $data['primary_id'] = $id;
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    public function delete($id): bool {
        $stmt = $this->db->prepare("DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function count(string $where = '', array $params = []): int {
        $sql = "SELECT COUNT(*) as total FROM `{$this->table}`";
        if (!empty($where)) {
            $sql .= " WHERE " . $where;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return (int)($row['total'] ?? 0);
    }

    public function rawQuery(string $sql, array $params = []): array {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function rawFirst(string $sql, array $params = []): ?array {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function rawExec(string $sql, array $params = []): bool {
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}

