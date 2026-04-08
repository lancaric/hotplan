<?php

declare(strict_types=1);

namespace HotPlan\Repositories;

use HotPlan\Entities\OverrideRule;
use HotPlan\Entities\OverrideType;
use HotPlan\Database\Connection;

/**
 * Override Repository
 * 
 * Manages override rules in the database.
 */
class OverrideRepository extends BaseRepository
{
    protected string $table = 'override_rules';
    
    /**
     * Find entity by ID
     */
    public function findById(int $id): ?OverrideRule
    {
        $data = parent::findById($id);
        return $data ? OverrideRule::fromArray($data) : null;
    }
    
    /**
     * Find active override for a given datetime
     */
    public function findActive(\DateTimeImmutable $dateTime): ?OverrideRule
    {
        $dateStr = $dateTime->format('Y-m-d H:i:s');
        
        $sql = "
            SELECT * FROM {$this->table}
            WHERE is_active = 1
            AND (
                override_type = ?
                OR (
                    (starts_at IS NULL OR starts_at <= ?)
                    AND (ends_at IS NULL OR ends_at >= ?)
                )
                OR (
                    (expires_at IS NULL OR expires_at >= ?)
                )
            )
            ORDER BY created_at DESC
            LIMIT 1
        ";
        
        $row = $this->db->fetch($sql, [
            OverrideType::INDEFINITE,
            $dateStr,
            $dateStr,
            $dateStr,
        ]);
        
        return $row ? OverrideRule::fromArray($row) : null;
    }
    
    /**
     * Find all active overrides
     */
    public function findAllActive(): array
    {
        $now = date('Y-m-d H:i:s');
        
        $sql = "
            SELECT * FROM {$this->table}
            WHERE is_active = 1
            AND (
                override_type = ?
                OR ends_at IS NULL
                OR ends_at >= ?
                OR expires_at >= ?
            )
            ORDER BY created_at DESC
        ";
        
        $rows = $this->db->fetchAll($sql, [OverrideType::INDEFINITE, $now, $now]);
        return array_map(fn($row) => OverrideRule::fromArray($row), $rows);
    }
    
    /**
     * Find overrides created by a user
     */
    public function findByUser(int $userId): array
    {
        $rows = $this->findBy('created_by = ?', [$userId]);
        return array_map(fn($row) => OverrideRule::fromArray($row), $rows);
    }
    
    /**
     * Find active override for a specific employee
     */
    public function findActiveForEmployee(int $employeeId): ?OverrideRule
    {
        $now = date('Y-m-d H:i:s');
        
        $sql = "
            SELECT * FROM {$this->table}
            WHERE is_active = 1
            AND source_employee_id = ?
            AND (
                override_type = ?
                OR ends_at >= ?
                OR expires_at >= ?
            )
            ORDER BY created_at DESC
            LIMIT 1
        ";
        
        $row = $this->db->fetch($sql, [
            $employeeId,
            OverrideType::UNTIL_EMPLOYEE,
            $now,
            $now,
        ]);
        
        return $row ? OverrideRule::fromArray($row) : null;
    }
    
    /**
     * Create override
     */
    public function createOverride(array $data): OverrideRule
    {
        $id = parent::insert($data);
        return $this->findById($id);
    }
    
    /**
     * Create temporary override
     */
    public function createTemporary(
        string $forwardTo,
        \DateTimeImmutable $startsAt,
        \DateTimeImmutable $endsAt,
        ?int $createdBy = null,
        ?string $reason = null,
    ): OverrideRule {
        return $this->createOverride([
            'override_type' => OverrideType::UNTIL_TIME,
            'is_active' => 1,
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'ends_at' => $endsAt->format('Y-m-d H:i:s'),
            'forward_to' => $forwardTo,
            'reason' => $reason,
            'created_by' => $createdBy,
        ]);
    }
    
    /**
     * Create indefinite override
     */
    public function createIndefinite(
        string $forwardTo,
        ?int $createdBy = null,
        ?string $reason = null,
    ): OverrideRule {
        return $this->createOverride([
            'override_type' => OverrideType::INDEFINITE,
            'is_active' => 1,
            'forward_to' => $forwardTo,
            'reason' => $reason,
            'created_by' => $createdBy,
        ]);
    }
    
    /**
     * Deactivate override
     */
    public function deactivate(int $id): bool
    {
        return parent::update($id, ['is_active' => 0]);
    }
    
    /**
     * Deactivate all active overrides
     */
    public function deactivateAll(): int
    {
        return $this->db->update($this->table, ['is_active' => 0], 'is_active = 1');
    }
    
    /**
     * Clean up expired overrides
     */
    public function cleanupExpired(): int
    {
        $now = date('Y-m-d H:i:s');
        
        return $this->db->update(
            $this->table,
            ['is_active' => 0],
            'is_active = 1 AND override_type != ? AND (ends_at < ? OR expires_at < ?)',
            [OverrideType::INDEFINITE, $now, $now]
        );
    }
}
