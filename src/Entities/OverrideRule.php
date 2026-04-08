<?php

declare(strict_types=1);

namespace HotPlan\Entities;

/**
 * Override Rule Entity
 * 
 * Represents a temporary manual override that takes precedence over all other rules.
 */
class OverrideRule extends BaseEntity
{
    /**
     * Get override type
     */
    public function getOverrideType(): string
    {
        return $this->get('override_type', OverrideType::TEMPORARY);
    }
    
    /**
     * Set override type
     */
    public function setOverrideType(string $type): self
    {
        return $this->set('override_type', $type);
    }
    
    /**
     * Check if override is active
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
     * Get starts at timestamp
     */
    public function getStartsAt(): ?\DateTimeImmutable
    {
        $value = $this->get('starts_at');
        return $value ? new \DateTimeImmutable($value) : null;
    }
    
    /**
     * Set starts at
     */
    public function setStartsAt(?\DateTimeImmutable $time): self
    {
        return $this->set('starts_at', $time?->format('Y-m-d H:i:s'));
    }
    
    /**
     * Get ends at timestamp
     */
    public function getEndsAt(): ?\DateTimeImmutable
    {
        $value = $this->get('ends_at');
        return $value ? new \DateTimeImmutable($value) : null;
    }
    
    /**
     * Set ends at
     */
    public function setEndsAt(?\DateTimeImmutable $time): self
    {
        return $this->set('ends_at', $time?->format('Y-m-d H:i:s'));
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
     * Get reason
     */
    public function getReason(): ?string
    {
        return $this->get('reason');
    }
    
    /**
     * Set reason
     */
    public function setReason(?string $reason): self
    {
        return $this->set('reason', $reason);
    }
    
    /**
     * Get created by user ID
     */
    public function getCreatedBy(): ?int
    {
        return $this->get('created_by');
    }
    
    /**
     * Set created by
     */
    public function setCreatedBy(?int $userId): self
    {
        return $this->set('created_by', $userId);
    }
    
    /**
     * Get source employee ID
     */
    public function getSourceEmployeeId(): ?int
    {
        return $this->get('source_employee_id');
    }
    
    /**
     * Set source employee
     */
    public function setSourceEmployeeId(?int $employeeId): self
    {
        return $this->set('source_employee_id', $employeeId);
    }
    
    /**
     * Get restored rule ID
     */
    public function getRestoredRuleId(): ?int
    {
        return $this->get('restored_rule_id');
    }
    
    /**
     * Set restored rule ID
     */
    public function setRestoredRuleId(?int $ruleId): self
    {
        return $this->set('restored_rule_id', $ruleId);
    }
    
    /**
     * Get created at timestamp
     */
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        $value = $this->get('created_at');
        return $value ? new \DateTimeImmutable($value) : null;
    }
    
    /**
     * Get expires at timestamp
     */
    public function getExpiresAt(): ?\DateTimeImmutable
    {
        $value = $this->get('expires_at');
        return $value ? new \DateTimeImmutable($value) : null;
    }
    
    /**
     * Set expires at
     */
    public function setExpiresAt(?\DateTimeImmutable $time): self
    {
        return $this->set('expires_at', $time?->format('Y-m-d H:i:s'));
    }
    
    /**
     * Check if override is currently in effect
     */
    public function isInEffect(\DateTimeImmutable $dateTime): bool
    {
        if (!$this->isActive()) {
            return false;
        }
        
        // Check indefinite overrides
        if ($this->getOverrideType() === OverrideType::INDEFINITE) {
            return true;
        }
        
        // Check starts_at
        $startsAt = $this->getStartsAt();
        if ($startsAt !== null && $dateTime < $startsAt) {
            return false;
        }
        
        // Check ends_at or expires_at
        $endsAt = $this->getEndsAt() ?? $this->getExpiresAt();
        if ($endsAt !== null && $dateTime > $endsAt) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get remaining time until override expires
     */
    public function getRemainingTime(\DateTimeImmutable $dateTime): ?\DateInterval
    {
        $endsAt = $this->getEndsAt() ?? $this->getExpiresAt();
        
        if ($endsAt === null) {
            return null;
        }
        
        if ($dateTime > $endsAt) {
            return null;
        }
        
        return $dateTime->diff($endsAt);
    }
    
    /**
     * Check if override has expired
     */
    public function isExpired(\DateTimeImmutable $dateTime): bool
    {
        if ($this->getOverrideType() === OverrideType::INDEFINITE) {
            return false;
        }
        
        $endsAt = $this->getEndsAt() ?? $this->getExpiresAt();
        
        if ($endsAt === null) {
            return false;
        }
        
        return $dateTime > $endsAt;
    }
    
    /**
     * Get priority (overrides are always highest priority)
     */
    public function getPriority(): int
    {
        return 1; // Highest priority
    }
    
    /**
     * Create from database row
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
