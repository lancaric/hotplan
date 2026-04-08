<?php

declare(strict_types=1);

namespace HotPlan\Repositories;

use HotPlan\Database\Connection;

/**
 * Base Repository
 * 
 * Provides common database operations.
 */
abstract class BaseRepository
{
    protected Connection $db;
    protected string $table;
    
    public function __construct(?Connection $db = null)
    {
        $this->db = $db ?? Connection::getInstance();
    }
    
    /**
     * Find by ID
     */
    public function findById(int $id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        return $this->db->fetch($sql, [$id]);
    }
    
    /**
     * Find all
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY id";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Find by condition
     */
    public function findBy(string $where, array $params = []): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$where}";
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Find one by condition
     */
    public function findOneBy(string $where, array $params = []): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$where} LIMIT 1";
        return $this->db->fetch($sql, $params);
    }
    
    /**
     * Insert a record
     */
    public function insert(array $data): int
    {
        // Remove id if present (auto-increment)
        unset($data['id']);
        
        // Add timestamps
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->db->insert($this->table, $data);
    }
    
    /**
     * Update a record
     */
    public function update(int $id, array $data): bool
    {
        unset($data['id']);
        unset($data['created_at']);
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $count = $this->db->update($this->table, $data, 'id = ?', [$id]);
        return $count > 0;
    }
    
    /**
     * Delete a record
     */
    public function delete(int $id): bool
    {
        $count = $this->db->delete($this->table, 'id = ?', [$id]);
        return $count > 0;
    }
    
    /**
     * Count records
     */
    public function count(?string $where = null, array $params = []): int
    {
        if ($where) {
            $sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$where}";
            $result = $this->db->fetch($sql, $params);
        } else {
            $sql = "SELECT COUNT(*) FROM {$this->table}";
            $result = $this->db->fetch($sql);
        }
        
        return (int) ($result['COUNT(*)'] ?? 0);
    }
    
    /**
     * Check if record exists
     */
    public function exists(int $id): bool
    {
        $sql = "SELECT 1 FROM {$this->table} WHERE id = ? LIMIT 1";
        return $this->db->fetch($sql, [$id]) !== null;
    }
}
