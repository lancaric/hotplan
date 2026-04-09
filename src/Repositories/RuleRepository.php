<?php

declare(strict_types=1);

namespace HotPlan\Repositories;

use HotPlan\Entities\ForwardingRule;
use HotPlan\Entities\RuleType;

/**
 * Rule Repository
 * 
 * Manages forwarding rules in the database.
 */
class RuleRepository extends BaseRepository
{
    protected string $table = 'forwarding_rules';
    
    /**
     * Find entity by ID
     */
    public function findById(int $id): ?ForwardingRule
    {
        $data = parent::findById($id);
        return $data ? ForwardingRule::fromArray($data) : null;
    }
    
    /**
     * Find all active rules
     */
    public function findActive(): array
    {
        $rows = $this->findBy('is_active = 1');
        return array_map(fn($row) => ForwardingRule::fromArray($row), $rows);
    }
    
    /**
     * Find rules by type
     */
    public function findByType(string $type): array
    {
        $rows = $this->findBy('rule_type = ? AND is_active = 1', [$type]);
        return array_map(fn($row) => ForwardingRule::fromArray($row), $rows);
    }
    
    /**
     * Find matching rules for a given datetime
     */
    public function findMatchingRules(\DateTimeImmutable $dateTime): array
    {
        $dateStr = $dateTime->format('Y-m-d H:i:s');
        $dayOfWeek = (int) $dateTime->format('w');
        $timeStr = $dateTime->format('H:i:s');
        
        // Find rules that are active and within their time window
        $sql = "
            SELECT * FROM {$this->table}
            WHERE is_active = 1
            AND (valid_from IS NULL OR valid_from <= ?)
            AND (valid_until IS NULL OR valid_until >= ?)
            ORDER BY priority ASC
        ";
        
        $rows = $this->db->fetchAll($sql, [$dateStr, $dateStr]);
        
        $rules = [];
        foreach ($rows as $row) {
            $rule = ForwardingRule::fromArray($row);
            
            // Check day of week if specified
            $daysOfWeek = $rule->getDaysOfWeek();
            if ($daysOfWeek !== null) {
                $normalizedDays = array_map(
                    static fn($day) => is_numeric($day) ? (int) $day : $day,
                    $daysOfWeek
                );
                if (!in_array($dayOfWeek, $normalizedDays, true)) {
                    continue;
                }
            }
            
            // Check time range if specified
            $startTime = $rule->getStartTime();
            $endTime = $rule->getEndTime();
            
            if ($startTime !== null && $endTime !== null) {
                $start = $startTime->format('H:i:s');
                $end = $endTime->format('H:i:s');
                
                // Handle overnight ranges
                if ($start > $end) {
                    if ($timeStr < $start && $timeStr > $end) {
                        continue;
                    }
                } else {
                    if ($timeStr < $start || $timeStr > $end) {
                        continue;
                    }
                }
            }
            
            $rules[] = $rule;
        }
        
        return $rules;
    }
    
    /**
     * Find upcoming rule changes
     */
    public function findUpcomingChanges(\DateTimeImmutable $from): array
    {
        $fromStr = $from->format('Y-m-d H:i:s');
        
        $sql = "
            SELECT * FROM {$this->table}
            WHERE is_active = 1
            AND valid_from > ?
            ORDER BY valid_from ASC
            LIMIT 10
        ";
        
        $rows = $this->db->fetchAll($sql, [$fromStr]);
        return array_map(fn($row) => ForwardingRule::fromArray($row), $rows);
    }
    
    /**
     * Find fallback rules
     */
    public function findFallbackRules(): array
    {
        return $this->findByType(RuleType::FALLBACK);
    }
    
    /**
     * Find override rules
     */
    public function findOverrideRules(): array
    {
        return $this->findByType(RuleType::OVERRIDE);
    }
    
    /**
     * Find rules for a specific employee
     */
    public function findByEmployee(int $employeeId): array
    {
        $sql = "
            SELECT * FROM {$this->table}
            WHERE target_employee_id = ?
            OR target_group_id IN (
                SELECT rotation_group_id FROM employees WHERE id = ?
            )
            ORDER BY priority ASC
        ";
        
        $rows = $this->db->fetchAll($sql, [$employeeId, $employeeId]);
        return array_map(fn($row) => ForwardingRule::fromArray($row), $rows);
    }
    
    /**
     * Get the highest priority fallback rule
     */
    public function findBestFallback(): ?ForwardingRule
    {
        $sql = "
            SELECT * FROM {$this->table}
            WHERE rule_type = ? AND is_active = 1
            ORDER BY priority ASC
            LIMIT 1
        ";
        
        $row = $this->db->fetch($sql, [RuleType::FALLBACK]);
        return $row ? ForwardingRule::fromArray($row) : null;
    }
    
    /**
     * Find rules expiring soon
     */
    public function findExpiringSoon(\DateInterval $interval): array
    {
        $threshold = (new \DateTimeImmutable())->add($interval)->format('Y-m-d H:i:s');
        
        $sql = "
            SELECT * FROM {$this->table}
            WHERE is_active = 1
            AND valid_until IS NOT NULL
            AND valid_until <= ?
            ORDER BY valid_until ASC
        ";
        
        $rows = $this->db->fetchAll($sql, [$threshold]);
        return array_map(fn($row) => ForwardingRule::fromArray($row), $rows);
    }
    
    /**
     * Create rule from array
     */
    public function create(array $data): ForwardingRule
    {
        $id = parent::insert($data);
        return $this->findById($id);
    }
    
    /**
     * Update rule
     */
    public function updateRule(int $id, array $data): bool
    {
        return parent::update($id, $data);
    }
    
    /**
     * Deactivate rule
     */
    public function deactivate(int $id): bool
    {
        return parent::update($id, ['is_active' => 0]);
    }
    
    /**
     * Activate rule
     */
    public function activate(int $id): bool
    {
        return parent::update($id, ['is_active' => 1]);
    }
}
