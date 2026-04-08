<?php

declare(strict_types=1);

namespace HotPlan\Entities;

/**
 * Holiday Entity
 * 
 * Represents a holiday or non-working day.
 */
class Holiday extends BaseEntity
{
    /**
     * Get holiday name
     */
    public function getName(): string
    {
        return $this->get('name', '');
    }
    
    /**
     * Set holiday name
     */
    public function setName(string $name): self
    {
        return $this->set('name', $name);
    }
    
    /**
     * Get holiday date
     */
    public function getDate(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->get('date'));
    }
    
    /**
     * Set holiday date
     */
    public function setDate(\DateTimeImmutable $date): self
    {
        return $this->set('date', $date->format('Y-m-d'));
    }
    
    /**
     * Get year of holiday
     */
    public function getYear(): int
    {
        $year = $this->get('year');
        if ($year !== null && $year !== '') {
            return (int) $year;
        }

        return (int) $this->getDate()->format('Y');
    }
    
    /**
     * Check if holiday is recurring annually
     */
    public function isRecurring(): bool
    {
        return (bool) $this->get('is_recurring', false);
    }
    
    /**
     * Set recurring flag
     */
    public function setRecurring(bool $recurring): self
    {
        return $this->set('is_recurring', $recurring);
    }
    
    /**
     * Get country code
     */
    public function getCountry(): ?string
    {
        return $this->get('country');
    }
    
    /**
     * Set country code
     */
    public function setCountry(?string $country): self
    {
        return $this->set('country', $country);
    }
    
    /**
     * Get region code
     */
    public function getRegion(): ?string
    {
        return $this->get('region');
    }
    
    /**
     * Set region code
     */
    public function setRegion(?string $region): self
    {
        return $this->set('region', $region);
    }
    
    /**
     * Get forward to override number
     */
    public function getForwardTo(): ?string
    {
        return $this->get('forward_to');
    }
    
    /**
     * Set forward to number
     */
    public function setForwardTo(?string $number): self
    {
        return $this->set('forward_to', $number);
    }
    
    /**
     * Check if this is a working day (override)
     */
    public function isWorkday(): bool
    {
        return (bool) $this->get('is_workday', false);
    }
    
    /**
     * Set workday override
     */
    public function setWorkday(bool $isWorkday): self
    {
        return $this->set('is_workday', $isWorkday);
    }
    
    /**
     * Get priority
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
     * Check if holiday is active
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
     * Check if this holiday applies to given date
     */
    public function appliesTo(\DateTimeImmutable $date): bool
    {
        if (!$this->isActive()) {
            return false;
        }
        
        if ($this->isRecurring()) {
            // For recurring holidays, match by month and day only
            return $this->getDate()->format('m-d') === $date->format('m-d');
        }
        
        // For non-recurring, match exact date
        return $this->getDate()->format('Y-m-d') === $date->format('Y-m-d');
    }
    
    /**
     * Get effective date for current year (for recurring holidays)
     */
    public function getEffectiveDate(int $year): \DateTimeImmutable
    {
        if ($this->isRecurring()) {
            $original = $this->getDate();
            return new \DateTimeImmutable(sprintf('%d-%02d-%02d', $year, $original->format('m'), $original->format('d')));
        }
        
        return $this->getDate();
    }
    
    /**
     * Create from database row
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
