<?php

declare(strict_types=1);

namespace HotPlan\Entities;

/**
 * On-Call Rotation Entity
 * 
 * Represents a recurring on-call rotation schedule.
 */
class OnCallRotation extends BaseEntity
{
    /**
     * Get rotation name
     */
    public function getName(): string
    {
        return $this->get('name', '');
    }
    
    /**
     * Set rotation name
     */
    public function setName(string $name): self
    {
        return $this->set('name', $name);
    }
    
    /**
     * Get group ID
     */
    public function getGroupId(): int
    {
        return (int) $this->get('group_id', 0);
    }
    
    /**
     * Set group ID
     */
    public function setGroupId(int $groupId): self
    {
        return $this->set('group_id', $groupId);
    }
    
    /**
     * Get rotation pattern
     */
    public function getPattern(): string
    {
        return $this->get('rotation_pattern', RotationPattern::WEEKLY);
    }
    
    /**
     * Set rotation pattern
     */
    public function setPattern(string $pattern): self
    {
        return $this->set('rotation_pattern', $pattern);
    }
    
    /**
     * Get rotation start date
     */
    public function getStartDate(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->get('rotation_start_date', date('Y-m-d')));
    }
    
    /**
     * Set rotation start date
     */
    public function setStartDate(\DateTimeImmutable $date): self
    {
        return $this->set('rotation_start_date', $date->format('Y-m-d'));
    }
    
    /**
     * Get rotation direction
     */
    public function getDirection(): string
    {
        return $this->get('rotation_direction', RotationDirection::FORWARD);
    }
    
    /**
     * Set rotation direction
     */
    public function setDirection(string $direction): self
    {
        return $this->set('rotation_direction', $direction);
    }
    
    /**
     * Check if 24/7 coverage
     */
    public function is24x7(): bool
    {
        return (bool) $this->get('is_24x7', false);
    }
    
    /**
     * Set 24/7 coverage
     */
    public function set24x7(bool $is24x7): self
    {
        return $this->set('is_24x7', $is24x7);
    }
    
    /**
     * Get default start time
     */
    public function getDefaultStartTime(): ?\DateTimeImmutable
    {
        $value = $this->get('default_start_time');
        return $value ? new \DateTimeImmutable($value) : null;
    }
    
    /**
     * Set default start time
     */
    public function setDefaultStartTime(?string $time): self
    {
        return $this->set('default_start_time', $time);
    }
    
    /**
     * Get default end time
     */
    public function getDefaultEndTime(): ?\DateTimeImmutable
    {
        $value = $this->get('default_end_time');
        return $value ? new \DateTimeImmutable($value) : null;
    }
    
    /**
     * Set default end time
     */
    public function setDefaultEndTime(?string $time): self
    {
        return $this->set('default_end_time', $time);
    }
    
    /**
     * Get during-hours forwarding number
     */
    public function getDuringHoursForwardTo(): ?string
    {
        return $this->get('during_hours_forward_to');
    }
    
    /**
     * Set during-hours forwarding number
     */
    public function setDuringHoursForwardTo(?string $number): self
    {
        return $this->set('during_hours_forward_to', $number);
    }
    
    /**
     * Get after-hours forwarding number
     */
    public function getAfterHoursForwardTo(): ?string
    {
        return $this->get('after_hours_forward_to');
    }
    
    /**
     * Set after-hours forwarding number
     */
    public function setAfterHoursForwardTo(?string $number): self
    {
        return $this->set('after_hours_forward_to', $number);
    }
    
    /**
     * Check if should use employee's mobile
     */
    public function useEmployeeMobile(): bool
    {
        return (bool) $this->get('use_employee_mobile', true);
    }
    
    /**
     * Set use employee mobile flag
     */
    public function setUseEmployeeMobile(bool $useMobile): self
    {
        return $this->set('use_employee_mobile', $useMobile);
    }
    
    /**
     * Get fallback rule ID
     */
    public function getFallbackRuleId(): ?int
    {
        return $this->get('fallback_rule_id');
    }
    
    /**
     * Set fallback rule ID
     */
    public function setFallbackRuleId(?int $ruleId): self
    {
        return $this->set('fallback_rule_id', $ruleId);
    }
    
    /**
     * Check if rotation is active
     */
    public function isActive(): bool
    {
        return (bool) $this->get('is_active', true);
    }
    
    /**
     * Set active status
     */
    public function setActive(bool $active): self
    {
        return $this->set('is_active', $active);
    }
    
    /**
     * Calculate which rotation week we are in
     */
    public function getCurrentWeekNumber(\DateTimeImmutable $dateTime): int
    {
        $startDate = $this->getStartDate();
        $diff = $startDate->diff($dateTime);
        $days = $diff->days;
        
        switch ($this->getPattern()) {
            case RotationPattern::DAILY:
                return (int) floor($days);
            case RotationPattern::BIWEEKLY:
                return (int) floor($days / 14);
            case RotationPattern::WEEKLY:
            default:
                return (int) floor($days / 7);
        }
    }
    
    /**
     * Check if rotation is currently in active time window
     */
    public function isInActiveWindow(\DateTimeImmutable $dateTime): bool
    {
        if (!$this->isActive()) {
            return false;
        }
        
        if ($this->is24x7()) {
            return true;
        }
        
        $startTime = $this->getDefaultStartTime();
        $endTime = $this->getDefaultEndTime();
        
        if ($startTime === null || $endTime === null) {
            return true;
        }
        
        $currentTime = $dateTime->format('H:i:s');
        $start = $startTime->format('H:i:s');
        $end = $endTime->format('H:i:s');
        
        return $currentTime >= $start && $currentTime <= $end;
    }
    
    /**
     * Create from database row
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
