<?php

declare(strict_types=1);

namespace HotPlan\Repositories;

use HotPlan\Entities\Holiday;
use HotPlan\Database\Connection;

/**
 * Holiday Repository
 * 
 * Manages holidays in the database.
 */
class HolidayRepository extends BaseRepository
{
    protected string $table = 'holidays';
    
    /**
     * Find entity by ID
     */
    public function findById(int $id): ?Holiday
    {
        $data = parent::findById($id);
        return $data ? Holiday::fromArray($data) : null;
    }
    
    /**
     * Find holiday for a specific date
     */
    public function findForDate(\DateTimeImmutable $date, ?string $country = null, ?string $region = null): ?Holiday
    {
        $year = (int) $date->format('Y');
        $monthDay = $date->format('m-d');
        
        // First try to find exact match
        $sql = "
            SELECT * FROM {$this->table}
            WHERE is_active = 1
            AND (
                (is_recurring = 1 AND strftime('%m-%d', date) = ?)
                OR (is_recurring = 0 AND date = ?)
            )
        ";
        
        $params = [$monthDay, $date->format('Y-m-d')];
        
        if ($country !== null) {
            $sql .= " AND (country = ? OR country IS NULL)";
            $params[] = $country;
        }
        
        if ($region !== null) {
            $sql .= " AND (region = ? OR region IS NULL)";
            $params[] = $region;
        }
        
        $sql .= " ORDER BY priority ASC LIMIT 1";
        
        $row = $this->db->fetch($sql, $params);
        return $row ? Holiday::fromArray($row) : null;
    }
    
    /**
     * Find all holidays for a year
     */
    public function findForYear(int $year, ?string $country = null): array
    {
        $sql = "
            SELECT * FROM {$this->table}
            WHERE is_active = 1
            AND (
                (is_recurring = 0 AND strftime('%Y', date) = ?)
                OR is_recurring = 1
            )
        ";
        
        $params = [$year];
        
        if ($country !== null) {
            $sql .= " AND (country = ? OR country IS NULL)";
            $params[] = $country;
        }
        
        $sql .= " ORDER BY date ASC";
        
        $rows = $this->db->fetchAll($sql, $params);
        return array_map(fn($row) => Holiday::fromArray($row), $rows);
    }
    
    /**
     * Find upcoming holidays
     */
    public function findUpcoming(int $limit = 10): array
    {
        $today = date('Y-m-d');
        
        $sql = "
            SELECT * FROM {$this->table}
            WHERE is_active = 1
            AND (
                (is_recurring = 1 AND date >= ?)
                OR (is_recurring = 0 AND date >= ?)
            )
            ORDER BY date ASC
            LIMIT ?
        ";
        
        $rows = $this->db->fetchAll($sql, [$today, $today, $limit]);
        return array_map(fn($row) => Holiday::fromArray($row), $rows);
    }
    
    /**
     * Find recurring holidays
     */
    public function findRecurring(): array
    {
        $rows = $this->findBy('is_recurring = 1 AND is_active = 1');
        return array_map(fn($row) => Holiday::fromArray($row), $rows);
    }
    
    /**
     * Check if a date is a holiday
     */
    public function isHoliday(\DateTimeImmutable $date, ?string $country = null): bool
    {
        return $this->findForDate($date, $country) !== null;
    }
    
    /**
     * Create holiday from array
     */
    public function create(array $data): Holiday
    {
        $id = parent::insert($data);
        return $this->findById($id);
    }
    
    /**
     * Update holiday
     */
    public function updateHoliday(int $id, array $data): bool
    {
        return parent::update($id, $data);
    }
}
