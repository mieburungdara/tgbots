<?php

class RawUpdateRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Saves a raw JSON update payload to the database.
     *
     * @param string $payload The JSON string from Telegram.
     * @return bool True on success, false on failure.
     */
    public function create(string $payload): bool
    {
        $sql = "INSERT INTO raw_updates (payload) VALUES (?)";
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$payload]);
        } catch (PDOException $e) {
            // Can't use app_log here easily if the error is DB connection itself
            // For now, just return false.
            return false;
        }
    }

    /**
     * Retrieves all raw updates, sorted by most recent first.
     *
     * @param int $limit The maximum number of records to retrieve.
     * @return array An array of records.
     */
    public function findAll(int $limit = 100): array
    {
        $sql = "SELECT * FROM raw_updates ORDER BY id DESC LIMIT :limit";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
