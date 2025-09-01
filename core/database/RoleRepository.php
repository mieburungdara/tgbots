<?php

namespace TGBot\Database;

use PDO;
use PDOException;

class RoleRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAllRoles(): array
    {
        return $this->pdo->query("SELECT * FROM roles ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addRole(string $name): int
    {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO roles (name) VALUES (?) ON DUPLICATE KEY UPDATE name=name");
            $stmt->execute([$name]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Error adding role: " . $e->getMessage());
            return -1;
        }
    }

    public function deleteRole(int $id): int
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM roles WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Error deleting role: " . $e->getMessage());
            return -1;
        }
    }
}
