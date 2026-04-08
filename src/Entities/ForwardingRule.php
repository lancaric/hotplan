<?php

declare(strict_types=1);

namespace HotPlan\Entities;

/**
 * Forwarding Rule Entity
 * 
 * Represents a forwarding rule with time-based conditions and priority.
 */
class ForwardingRule extends BaseEntity
{
    /**
     * Get rule name
     */
    public function getName(): string
    {
        return $this->get('name', '');
    }
    
    /**
     * Set rule name
     */
    public function setName(string $name): self
    {
        return $this->set('name', $name);
    }
    
    /**
     * Get rule type
     */
    public function getRuleType(): string
    {
        return $this->get('rule_type', RuleType::FALLBACK);
    }
    
    /**
     * Set rule type
     */
    public function setRuleType(string $type): self
    {
        return $this->set('rule_type', $type);
    }
    
    /**
     * Get priority (lower = higher priority)
     */
    public function getPriority(): int
    {
        return (int) $this->get('priority', 50);
    }
    
    /**
     * Set priority
     */
    public function setPriority(int $priority): self
    {
        return $this->set('priority', $priority);
    }
    
    /**
     * Check if rule is active
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
     * Check if rule is recurring
     */
    public function isRecurring(): bool
    {
        return (bool) $this->get('is_recurring', false);
    }
    
    /**
     * Set recurring status
     */
    public function setRecurring(bool $recurring): self
    {
        return $this->set('is_recurring', $recurring);
    }
    
    /**
     * Get valid from timestamp
     */
    public function getValidFrom(): ?\DateTimeImmutable
    {
        $value = $this->get('valid_from');
        return $value ? new \DateTimeImmutable($value) : null;
    }
    
    /**
     * Set valid from
     */
    public function setValidFrom(?\DateTimeImmutable $from): self
    {
        return $this->set('valid_from', $from?->format('Y-m-d H:i:s'));
    }
    
    /**
     * Get valid until timestamp
     */
    public function getValidUntil(): ?\DateTimeImmutable
    {
        $value = $this->get('valid_until');
        return $value ? new \DateTimeImmutable($value) : null;
    }
    
    /**
     * Set valid until
     */
    public function setValidUntil(?\DateTimeImmutable $until): self
    {
        return $this->set('valid_until', $until?->format('Y-m-d H:i:s'));
    }
    
    /**
     * Get days of week (0=Sunday, 6=Saturday)
     */
    public function getDaysOfWeek(): ?array
    {
        $value = $this->get('days_of_week');
        if ($value === null) {
            return null;
        }
        return is_string($value) ? json_decode($value, true) ?? [] : $value;
    }
    
    /**
     * Set days of week
     */
    public function setDaysOfWeek(?array $days): self
    {
        if ($days === null) {
            return $this->set('days_of_week', null);
        }
        return $this->set('days_of_week', json_encode($days));
    }
    
    /**
     * Get start time
     */
    public function getStartTime(): ?\DateTimeImmutable
    {
        $value = $this->get('start_time');
        return $value ? new \DateTimeImmutable($value) : null;
    }
    
    /**
     * Set start time
     */
    public function setStartTime(?string $time): self
    {
        return $this->set('start_time', $time);
    }
    
    /**
     * Get end time
     */
    public function getEndTime(): ?\DateTimeImmutable
    {
        $value = $this->get('end_time');
        return $value ? new \DateTimeImmutable($value) : null;
    }
    
    /**
     * Set end time
     */
    public function setEndTime(?string $time): self
    {
        return $this->set('end_time', $time);
    }
    
    /**
     * Get forward to number
     */
    public function getForwardTo(): string
    {
        return $this->get('forward_to', '');
    }
    
    /**
     * Set forward to number
     */
    public function setForwardTo(string $number): self
    {
        return $this->set('forward_to', $number);
    }
    
    /**
     * Get target type
     */
    public function getTargetType(): string
    {
        return $this->get('target_type', TargetType::NUMBER);
    }
    
    /**
     * Set target type
     */
    public function setTargetType(string $type): self
    {
        return $this->set('target_type', $type);
    }
    
    /**
     * Get target employee ID
     */
    public function getTargetEmployeeId(): ?int
    {
        return $this->get('target_employee_id');
    }
    
    /**
     * Set target employee ID
     */
    public function setTargetEmployeeId(?int $id): self
    {
        return $this->set('target_employee_id', $id);
    }
    
    /**
     * Get target group ID
     */
    public function getTargetGroupId(): ?int
    {
        return $this->get('target_group_id');
    }
    
    /**
     * Set target group ID
     */
    public function setTargetGroupId(?int $id): self
    {
        return $this->set('target_group_id', $id);
    }
    
    /**
     * Get holiday ID
     */
    public function getHolidayId(): ?int
    {
        return $this->get('holiday_id');
    }
    
    /**
     * Set holiday ID
     */
    public function setHolidayId(?int $id): self
    {
        return $this->set('holiday_id', $id);
    }
    
    /**
     * Check if employee is required
     */
    public function requiresEmployee(): bool
    {
        return (bool) $this->get('requires_employee', false);
    }
    
    /**
     * Set requires employee flag
     */
    public function setRequiresEmployee(bool $required): self
    {
        return $this->set('requires_employee', $required);
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
     * Get metadata
     */
    public function getMetadata(): array
    {
        $metadata = $this->get('metadata');
        return is_string($metadata) ? json_decode($metadata, true) ?? [] : ($metadata ?? []);
    }
    
    /**
     * Set metadata
     */
    public function setMetadata(array $metadata): self
    {
        return $this->set('metadata', json_encode($metadata));
    }
    
    /**
     * Check if rule is valid at given time
     */
    public function isValidAt(\DateTimeImmutable $dateTime): bool
    {
        // Check active status
        if (!$this->isActive()) {
            return false;
        }
        
        // Check valid_from
        $validFrom = $this->getValidFrom();
        if ($validFrom !== null && $dateTime < $validFrom) {
            return false;
        }
        
        // Check valid_until
        $validUntil = $this->getValidUntil();
        if ($validUntil !== null && $dateTime > $validUntil) {
            return false;
        }
        
        // Check days of week
        $daysOfWeek = $this->getDaysOfWeek();
        if ($daysOfWeek !== null) {
            $currentDay = (int) $dateTime->format('w');
            $normalizedDays = array_map(
                static fn($day) => is_numeric($day) ? (int) $day : $day,
                $daysOfWeek
            );

            if (!in_array($currentDay, $normalizedDays, true)) {
                return false;
            }
        }
        
        // Check time range
        $startTime = $this->getStartTime();
        $endTime = $this->getEndTime();
        
        if ($startTime !== null && $endTime !== null) {
            $currentTime = $dateTime->format('H:i:s');
            $start = $startTime->format('H:i:s');
            $end = $endTime->format('H:i:s');
            
            // Handle overnight ranges (e.g., 22:00 - 06:00)
            if ($start > $end) {
                if ($currentTime < $start && $currentTime > $end) {
                    return false;
                }
            } else {
                if ($currentTime < $start || $currentTime > $end) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Get rule type priority class name for CSS/styling
     */
    public function getTypeClass(): string
    {
        return 'rule-' . str_replace('_', '-', $this->getRuleType());
    }
    
    /**
     * Create from database row
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
