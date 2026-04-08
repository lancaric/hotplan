<?php

declare(strict_types=1);

namespace HotPlan\Entities;

/**
 * Rotation Group Entity
 * 
 * Represents a group of employees who rotate on-call duties.
 */
class RotationGroup extends BaseEntity
{
    /**
     * Get group name
     */
    public function getName(): string
    {
        return $this->get('name', '');
    }
    
    /**
     * Set group name
     */
    public function setName(string $name): self
    {
        return $this->set('name', $name);
    }
    
    /**
     * Get description
     */
    public function getDescription(): ?string
    {
        return $this->get('description');
    }
    
    /**
     * Set description
     */
    public function setDescription(?string $description): self
    {
        return $this->set('description', $description);
    }
    
    /**
     * Get rotation type
     */
    public function getRotationType(): string
    {
        return $this->get('rotation_type', RotationType::WEEKLY);
    }
    
    /**
     * Set rotation type
     */
    public function setRotationType(string $type): self
    {
        return $this->set('rotation_type', $type);
    }
    
    /**
     * Get rotation order (array of employee IDs)
     */
    public function getRotationOrder(): array
    {
        $value = $this->get('rotation_order');
        return is_string($value) ? json_decode($value, true) ?? [] : ($value ?? []);
    }
    
    /**
     * Set rotation order
     */
    public function setRotationOrder(array $order): self
    {
        return $this->set('rotation_order', json_encode($order));
    }
    
    /**
     * Get current rotation index
     */
    public function getCurrentIndex(): int
    {
        return (int) $this->get('current_index', 0);
    }
    
    /**
     * Set current rotation index
     */
    public function setCurrentIndex(int $index): self
    {
        return $this->set('current_index', $index);
    }
    
    /**
     * Get rotation start date
     */
    public function getRotationStartDate(): ?\DateTimeImmutable
    {
        $value = $this->get('rotation_start_date');
        return $value ? new \DateTimeImmutable($value) : null;
    }
    
    /**
     * Set rotation start date
     */
    public function setRotationStartDate(?\DateTimeImmutable $date): self
    {
        return $this->set('rotation_start_date', $date?->format('Y-m-d'));
    }
    
    /**
     * Get the current employee ID in rotation
     */
    public function getCurrentEmployeeId(array $employeeIds): ?int
    {
        $order = $this->getRotationOrder();
        
        if (empty($order)) {
            // Fallback to employee IDs array if no explicit order
            $order = $employeeIds;
        }
        
        $index = $this->getCurrentIndex();
        
        if (empty($order)) {
            return null;
        }
        
        return $order[$index % count($order)] ?? null;
    }
    
    /**
     * Advance to next employee in rotation
     */
    public function advanceRotation(): self
    {
        $currentIndex = $this->getCurrentIndex();
        return $this->setCurrentIndex($currentIndex + 1);
    }
    
    /**
     * Calculate current rotation position based on date
     */
    public function calculateCurrentPosition(\DateTimeImmutable $dateTime): int
    {
        $startDate = $this->getRotationStartDate();
        
        if ($startDate === null) {
            return 0;
        }
        
        $diff = $startDate->diff($dateTime);
        $days = $diff->days;
        
        switch ($this->getRotationType()) {
            case RotationType::DAILY:
                return $days;
            case RotationType::WEEKLY:
                return (int) floor($days / 7);
            case RotationType::CUSTOM:
                // Custom logic would be implemented here
                return (int) floor($days / 7);
            default:
                return 0;
        }
    }
    
    /**
     * Get employee at specific rotation position
     */
    public function getEmployeeAtPosition(int $position, array $employeeIds): ?int
    {
        $order = $this->getRotationOrder();
        
        if (empty($order)) {
            $order = $employeeIds;
        }
        
        if (empty($order)) {
            return null;
        }
        
        $index = $position % count($order);
        return $order[$index] ?? null;
    }
    
    /**
     * Create from database row
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
