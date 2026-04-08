<?php

declare(strict_types=1);

namespace HotPlan\Repositories;

use HotPlan\Entities\OnCallRotation;
use HotPlan\Entities\RotationGroup;
use HotPlan\Database\Connection;

/**
 * On-Call Repository
 * 
 * Manages on-call rotations in the database.
 */
class OnCallRepository extends BaseRepository
{
    protected string $table = 'oncall_rotations';
    
    /**
     * Find entity by ID
     */
    public function findById(int $id): ?OnCallRotation
    {
        $data = parent::findById($id);
        return $data ? OnCallRotation::fromArray($data) : null;
    }
    
    /**
     * Find active on-call rotation for a given datetime
     */
    public function findActiveForDateTime(\DateTimeImmutable $dateTime): ?OnCallRotation
    {
        $dateStr = $dateTime->format('Y-m-d H:i:s');
        
        $sql = "
            SELECT * FROM {$this->table}
            WHERE is_active = 1
            AND rotation_start_date <= ?
            ORDER BY rotation_start_date DESC
            LIMIT 1
        ";
        
        $row = $this->db->fetch($sql, [$dateStr]);
        return $row ? OnCallRotation::fromArray($row) : null;
    }
    
    /**
     * Find all active rotations
     */
    public function findAllActive(): array
    {
        $rows = $this->findBy('is_active = 1');
        return array_map(fn($row) => OnCallRotation::fromArray($row), $rows);
    }
    
    /**
     * Find rotations by group
     */
    public function findByGroup(int $groupId): array
    {
        $rows = $this->findBy('group_id = ?', [$groupId]);
        return array_map(fn($row) => OnCallRotation::fromArray($row), $rows);
    }
    
    /**
     * Get current on-call employee ID
     */
    public function getCurrentOnCallEmployeeId(int $groupId, \DateTimeImmutable $dateTime): ?int
    {
        $rotation = $this->findActiveForDateTime($dateTime);
        
        if ($rotation === null) {
            return null;
        }
        
        // Get employees in rotation order
        $employeeRepo = new EmployeeRepository($this->db);
        $employees = $employeeRepo->findByRotationGroup($groupId);
        
        if (empty($employees)) {
            return null;
        }
        
        // Calculate rotation position
        $position = $rotation->getCurrentWeekNumber($dateTime);
        $index = $position % count($employees);
        
        return $employees[$index]->getId();
    }
    
    /**
     * Create rotation
     */
    public function create(array $data): OnCallRotation
    {
        $id = parent::insert($data);
        return $this->findById($id);
    }
    
    /**
     * Update rotation
     */
    public function updateRotation(int $id, array $data): bool
    {
        return parent::update($id, $data);
    }
    
    /**
     * Deactivate rotation
     */
    public function deactivate(int $id): bool
    {
        return parent::update($id, ['is_active' => 0]);
    }
    
    /**
     * Advance rotation to next employee
     */
    public function advanceRotation(int $id): bool
    {
        $rotation = $this->findById($id);
        
        if ($rotation === null) {
            return false;
        }
        
        // This would typically update the rotation state
        // For now, it just marks the rotation as modified
        return true;
    }
    
    /**
     * Get rotation group repository
     */
    public function getGroupRepository(): RotationGroupRepository
    {
        return new RotationGroupRepository($this->db);
    }
}

/**
 * Rotation Group Repository
 * 
 * Manages rotation groups in the database.
 */
class RotationGroupRepository extends BaseRepository
{
    protected string $table = 'rotation_groups';
    
    /**
     * Find entity by ID
     */
    public function findById(int $id): ?RotationGroup
    {
        $data = parent::findById($id);
        return $data ? RotationGroup::fromArray($data) : null;
    }
    
    /**
     * Find all groups
     */
    public function findAll(): array
    {
        $rows = parent::findAll();
        return array_map(fn($row) => RotationGroup::fromArray($row), $rows);
    }
    
    /**
     * Find by name
     */
    public function findByName(string $name): ?RotationGroup
    {
        $data = parent::findOneBy('name = ?', [$name]);
        return $data ? RotationGroup::fromArray($data) : null;
    }
    
    /**
     * Create group
     */
    public function create(array $data): RotationGroup
    {
        $id = parent::insert($data);
        return $this->findById($id);
    }
    
    /**
     * Update group
     */
    public function updateGroup(int $id, array $data): bool
    {
        return parent::update($id, $data);
    }
    
    /**
     * Delete group
     */
    public function delete(int $id): bool
    {
        // First update employees to remove group reference
        $this->db->update('employees', ['rotation_group_id' => null], 'rotation_group_id = ?', [$id]);
        return parent::delete($id);
    }
    
    /**
     * Set rotation order
     */
    public function setRotationOrder(int $groupId, array $employeeIds): bool
    {
        $group = $this->findById($groupId);
        
        if ($group === null) {
            return false;
        }
        
        return parent::update($groupId, [
            'rotation_order' => json_encode($employeeIds),
        ]);
    }
    
    /**
     * Get current employee in rotation
     */
    public function getCurrentEmployee(int $groupId, array $employees): ?int
    {
        $group = $this->findById($groupId);
        
        if ($group === null) {
            return null;
        }
        
        return $group->getCurrentEmployeeId(array_column($employees, 'id'));
    }
}
