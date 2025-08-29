<?php

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

    public function addRole(string $name): bool
    {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO roles (name) VALUES (?) ON DUPLICATE KEY UPDATE name=name");
            return $stmt->execute([$name]);
        } catch (PDOException $e) {
            error_log("Error adding role: " . $e->getMessage());
            return false;
        }
    }

    public function deleteRole(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM roles WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
