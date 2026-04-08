<?php

declare(strict_types=1);

namespace HotPlan\Entities;

/**
 * Employee Entity
 * 
 * Represents an employee who can be assigned to on-call duties.
 */
class Employee extends BaseEntity
{
    public const TYPE = 'employee';
    
    /**
     * Get employee name
     */
    public function getName(): string
    {
        return $this->get('name', '');
    }
    
    /**
     * Set employee name
     */
    public function setName(string $name): self
    {
        return $this->set('name', $name);
    }
    
    /**
     * Get email
     */
    public function getEmail(): ?string
    {
        return $this->get('email');
    }
    
    /**
     * Set email
     */
    public function setEmail(string $email): self
    {
        return $this->set('email', $email);
    }
    
    /**
     * Get internal phone number
     */
    public function getPhoneInternal(): ?string
    {
        return $this->get('phone_internal');
    }
    
    /**
     * Set internal phone number
     */
    public function setPhoneInternal(string $phone): self
    {
        return $this->set('phone_internal', $phone);
    }
    
    /**
     * Get mobile phone number
     */
    public function getPhoneMobile(): ?string
    {
        return $this->get('phone_mobile');
    }
    
    /**
     * Set mobile phone number
     */
    public function setPhoneMobile(string $phone): self
    {
        return $this->set('phone_mobile', $phone);
    }
    
    /**
     * Get primary phone number
     */
    public function getPhonePrimary(): ?string
    {
        return $this->get('phone_primary');
    }
    
    /**
     * Set primary phone number
     */
    public function setPhonePrimary(string $phone): self
    {
        return $this->set('phone_primary', $phone);
    }
    
    /**
     * Check if employee is active
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
     * Check if employee is currently on-call
     */
    public function isOnCall(): bool
    {
        return (bool) $this->get('is_oncall', false);
    }
    
    /**
     * Set on-call status
     */
    public function setOnCall(bool $onCall): self
    {
        return $this->set('is_oncall', $onCall);
    }
    
    /**
     * Get priority (lower = higher priority)
     */
    public function getPriority(): int
    {
        return (int) $this->get('priority', 100);
    }
    
    /**
     * Set priority
     */
    public function setPriority(int $priority): self
    {
        return $this->set('priority', $priority);
    }
    
    /**
     * Get rotation group ID
     */
    public function getRotationGroupId(): ?int
    {
        return $this->get('rotation_group_id');
    }
    
    /**
     * Set rotation group ID
     */
    public function setRotationGroupId(int $groupId): self
    {
        return $this->set('rotation_group_id', $groupId);
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
     * Get created timestamp
     */
    public function getCreatedAt(): ?string
    {
        return $this->get('created_at');
    }
    
    /**
     * Get updated timestamp
     */
    public function getUpdatedAt(): ?string
    {
        return $this->get('updated_at');
    }
    
    /**
     * Get the best phone number for forwarding based on context
     */
    public function getForwardingNumber(bool $useMobile = false): ?string
    {
        if ($useMobile && $this->getPhoneMobile()) {
            return $this->getPhoneMobile();
        }
        
        return $this->getPhonePrimary() ?? $this->getPhoneInternal();
    }
    
    /**
     * Create from database row
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
