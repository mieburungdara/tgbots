<?php

class PackageRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function find(int $id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM media_packages WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPackageFiles(int $package_id): array
    {
        $stmt = $this->pdo->prepare("SELECT file_id, type FROM media_files WHERE package_id = ? ORDER BY id");
        $stmt->execute([$package_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findForPurchase(int $package_id)
    {
        $stmt = $this->pdo->prepare("SELECT price, seller_user_id FROM media_packages WHERE id = ? AND status = 'available'");
        $stmt->execute([$package_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function markAsSold(int $package_id)
    {
        $stmt = $this->pdo->prepare("UPDATE media_packages SET status = 'sold' WHERE id = ?");
        $stmt->execute([$package_id]);
    }
}
