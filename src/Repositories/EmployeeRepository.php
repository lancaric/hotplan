<?php

declare(strict_types=1);

namespace HotPlan\Repositories;

use HotPlan\Entities\Employee;
use HotPlan\Database\Connection;

/**
 * Employee Repository
 * 
 * Manages employees in the database.
 */
class EmployeeRepository extends BaseRepository
{
    protected string $table = 'employees';
    
    /**
     * Find entity by ID
     */
    public function findById(int $id): ?Employee
    {
        $data = parent::findById($id);
        return $data ? Employee::fromArray($data) : null;
    }
    
    /**
     * Find all active employees
     */
    public function findActive(): array
    {
        $rows = $this->findBy('is_active = 1');
        return array_map(fn($row) => Employee::fromArray($row), $rows);
    }
    
    /**
     * Find employees by rotation group
     */
    public function findByRotationGroup(int $groupId): array
    {
        $sql = "
            SELECT * FROM {$this->table}
            WHERE rotation_group_id = ?
            AND is_active = 1
            ORDER BY priority ASC
        ";
        
        $rows = $this->db->fetchAll($sql, [$groupId]);
        return array_map(fn($row) => Employee::fromArray($row), $rows);
    }
    
    /**
     * Find currently on-call employees
     */
    public function findOnCall(): array
    {
        $rows = $this->findBy('is_oncall = 1 AND is_active = 1');
        return array_map(fn($row) => Employee::fromArray($row), $rows);
    }
    
    /**
     * Find by email
     */
    public function findByEmail(string $email): ?Employee
    {
        $data = parent::findOneBy('email = ?', [$email]);
        return $data ? Employee::fromArray($data) : null;
    }
    
    /**
     * Find by phone number
     */
    public function findByPhone(string $phone): ?Employee
    {
        $sql = "
            SELECT * FROM {$this->table}
            WHERE phone_internal = ?
            OR phone_mobile = ?
            OR phone_primary = ?
            LIMIT 1
        ";
        
        $data = $this->db->fetch($sql, [$phone, $phone, $phone]);
        return $data ? Employee::fromArray($data) : null;
    }
    
    /**
     * Search employees
     */
    public function search(string $query, int $limit = 20): array
    {
        $sql = "
            SELECT * FROM {$this->table}
            WHERE is_active = 1
            AND (
                name LIKE ?
                OR email LIKE ?
                OR phone_internal LIKE ?
                OR phone_mobile LIKE ?
            )
            ORDER BY name
            LIMIT ?
        ";
        
        $likeQuery = "%{$query}%";
        $rows = $this->db->fetchAll($sql, [$likeQuery, $likeQuery, $likeQuery, $likeQuery, $limit]);
        return array_map(fn($row) => Employee::fromArray($row), $rows);
    }
    
    /**
     * Create employee
     */
    public function create(array $data): Employee
    {
        $id = parent::insert($data);
        return $this->findById($id);
    }
    
    /**
     * Update employee
     */
    public function updateEmployee(int $id, array $data): bool
    {
        return parent::update($id, $data);
    }
    
    /**
     * Deactivate employee
     */
    public function deactivate(int $id): bool
    {
        return parent::update($id, ['is_active' => 0]);
    }
    
    /**
     * Activate employee
     */
    public function activate(int $id): bool
    {
        return parent::update($id, ['is_active' => 1]);
    }
    
    /**
     * Set on-call status
     */
    public function setOnCall(int $id, bool $onCall): bool
    {
        return parent::update($id, ['is_oncall' => $onCall ? 1 : 0]);
    }
    
    /**
     * Clear all on-call statuses
     */
    public function clearAllOnCall(): int
    {
        return $this->db->update($this->table, ['is_oncall' => 0], 'is_oncall = 1');
    }
    
    /**
     * Get employee with highest priority in group
     */
    public function getHighestPriorityInGroup(int $groupId): ?Employee
    {
        $sql = "
            SELECT * FROM {$this->table}
            WHERE rotation_group_id = ?
            AND is_active = 1
            ORDER BY priority ASC
            LIMIT 1
        ";
        
        $data = $this->db->fetch($sql, [$groupId]);
        return $data ? Employee::fromArray($data) : null;
    }
}
