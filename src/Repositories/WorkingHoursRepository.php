<?php

declare(strict_types=1);

namespace HotPlan\Repositories;

use HotPlan\Entities\WorkingHours;
use HotPlan\Database\Connection;

/**
 * Working Hours Repository
 * 
 * Manages working hours configuration in the database.
 */
class WorkingHoursRepository extends BaseRepository
{
    protected string $table = 'working_hours';
    
    /**
     * Find entity by ID
     */
    public function findById(int $id): ?WorkingHours
    {
        $data = parent::findById($id);
        return $data ? WorkingHours::fromArray($data) : null;
    }
    
    /**
     * Find working hours for a specific day of week
     */
    public function findForDay(int $dayOfWeek): ?WorkingHours
    {
        $today = date('Y-m-d');
        
        // First try to find one with no effective date restrictions
        $sql = "
            SELECT * FROM {$this->table}
            WHERE day_of_week = ?
            AND is_active = 1
            AND (effective_from IS NULL OR effective_from <= ?)
            AND (effective_until IS NULL OR effective_until >= ?)
            ORDER BY effective_from DESC NULLS FIRST
            LIMIT 1
        ";
        
        $row = $this->db->fetch($sql, [$dayOfWeek, $today, $today]);
        return $row ? WorkingHours::fromArray($row) : null;
    }
    
    /**
     * Find all working hours for a week
     */
    public function findWeekSchedule(): array
    {
        $today = date('Y-m-d');
        
        $sql = "
            SELECT wh1.* FROM {$this->table} wh1
            INNER JOIN (
                SELECT day_of_week, MAX(effective_from) as max_date
                FROM {$this->table}
                WHERE is_active = 1
                AND (effective_from IS NULL OR effective_from <= ?)
                GROUP BY day_of_week
            ) wh2 ON wh1.day_of_week = wh2.day_of_week
            AND (wh1.effective_from = wh2.max_date OR (wh1.effective_from IS NULL AND wh2.max_date IS NULL))
            WHERE wh1.is_active = 1
            ORDER BY wh1.day_of_week
        ";
        
        $rows = $this->db->fetchAll($sql, [$today]);
        return array_map(fn($row) => WorkingHours::fromArray($row), $rows);
    }
    
    /**
     * Find working days
     */
    public function findWorkingDays(): array
    {
        $today = date('Y-m-d');
        
        $sql = "
            SELECT * FROM {$this->table}
            WHERE is_working_day = 1
            AND is_active = 1
            AND (effective_from IS NULL OR effective_from <= ?)
            AND (effective_until IS NULL OR effective_until >= ?)
            ORDER BY day_of_week
        ";
        
        $rows = $this->db->fetchAll($sql, [$today, $today]);
        return array_map(fn($row) => WorkingHours::fromArray($row), $rows);
    }
    
    /**
     * Check if a datetime is within working hours
     */
    public function isWithinWorkingHours(\DateTimeImmutable $dateTime): bool
    {
        $workingHours = $this->findForDay((int) $dateTime->format('w'));
        
        if ($workingHours === null || !$workingHours->isWorkingDay()) {
            return false;
        }
        
        return $workingHours->isWithinWorkingHours($dateTime);
    }
    
    /**
     * Find effective date range for working hours
     */
    public function findEffectiveRanges(): array
    {
        $sql = "
            SELECT 
                MIN(effective_from) as min_date,
                MAX(effective_until) as max_date
            FROM {$this->table}
            WHERE is_active = 1
        ";
        
        return $this->db->fetch($sql);
    }
    
    /**
     * Create working hours from array
     */
    public function create(array $data): WorkingHours
    {
        $id = parent::insert($data);
        return $this->findById($id);
    }
    
    /**
     * Update working hours
     */
    public function updateHours(int $id, array $data): bool
    {
        return parent::update($id, $data);
    }
}
