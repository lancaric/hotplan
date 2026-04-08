<?php

declare(strict_types=1);

namespace HotPlan\Entities;

/**
 * Working Hours Entity
 * 
 * Defines standard working hours for each day of the week.
 */
class WorkingHours extends BaseEntity
{
    /**
     * Day of week constants (ISO 8601)
     */
    public const SUNDAY = 0;
    public const MONDAY = 1;
    public const TUESDAY = 2;
    public const WEDNESDAY = 3;
    public const THURSDAY = 4;
    public const FRIDAY = 5;
    public const SATURDAY = 6;
    
    /**
     * Day names
     */
    public const DAY_NAMES = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];
    
    /**
     * Short day names
     */
    public const DAY_NAMES_SHORT = [
        0 => 'Sun',
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
    ];
    
    /**
     * Get day of week
     */
    public function getDayOfWeek(): int
    {
        return (int) $this->get('day_of_week', 1);
    }
    
    /**
     * Set day of week
     */
    public function setDayOfWeek(int $day): self
    {
        return $this->set('day_of_week', $day);
    }
    
    /**
     * Get day name
     */
    public function getDayName(): string
    {
        return self::DAY_NAMES[$this->getDayOfWeek()] ?? 'Unknown';
    }
    
    /**
     * Check if this is a working day
     */
    public function isWorkingDay(): bool
    {
        return (bool) $this->get('is_working_day', true);
    }
    
    /**
     * Set working day flag
     */
    public function setWorkingDay(bool $isWorking): self
    {
        return $this->set('is_working_day', $isWorking);
    }
    
    /**
     * Get start time
     */
    public function getStartTime(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->get('start_time', '08:00:00'));
    }
    
    /**
     * Set start time
     */
    public function setStartTime(string $time): self
    {
        return $this->set('start_time', $time);
    }
    
    /**
     * Get end time
     */
    public function getEndTime(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->get('end_time', '17:00:00'));
    }
    
    /**
     * Set end time
     */
    public function setEndTime(string $time): self
    {
        return $this->set('end_time', $time);
    }
    
    /**
     * Get internal forwarding number
     */
    public function getForwardToInternal(): ?string
    {
        return $this->get('forward_to_internal');
    }
    
    /**
     * Set internal forwarding number
     */
    public function setForwardToInternal(?string $number): self
    {
        return $this->set('forward_to_internal', $number);
    }
    
    /**
     * Get external forwarding number
     */
    public function getForwardToExternal(): ?string
    {
        return $this->get('forward_to_external');
    }
    
    /**
     * Set external forwarding number
     */
    public function setForwardToExternal(?string $number): self
    {
        return $this->set('forward_to_external', $number);
    }
    
    /**
     * Get effective from date
     */
    public function getEffectiveFrom(): ?\DateTimeImmutable
    {
        $value = $this->get('effective_from');
        return $value ? new \DateTimeImmutable($value) : null;
    }
    
    /**
     * Set effective from date
     */
    public function setEffectiveFrom(?\DateTimeImmutable $date): self
    {
        return $this->set('effective_from', $date?->format('Y-m-d'));
    }
    
    /**
     * Get effective until date
     */
    public function getEffectiveUntil(): ?\DateTimeImmutable
    {
        $value = $this->get('effective_until');
        return $value ? new \DateTimeImmutable($value) : null;
    }
    
    /**
     * Set effective until date
     */
    public function setEffectiveUntil(?\DateTimeImmutable $date): self
    {
        return $this->set('effective_until', $date?->format('Y-m-d'));
    }
    
    /**
     * Check if working hours are active at given time
     */
    public function isActiveAt(\DateTimeImmutable $dateTime): bool
    {
        if (!$this->isWorkingDay()) {
            return false;
        }
        
        // Check effective dates
        $effectiveFrom = $this->getEffectiveFrom();
        if ($effectiveFrom !== null && $dateTime < $effectiveFrom) {
            return false;
        }
        
        $effectiveUntil = $this->getEffectiveUntil();
        if ($effectiveUntil !== null && $dateTime > $effectiveUntil) {
            return false;
        }
        
        // Check if day of week matches
        if ($dateTime->format('w') != $this->getDayOfWeek()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if given time is within working hours
     */
    public function isWithinWorkingHours(\DateTimeImmutable $dateTime): bool
    {
        if (!$this->isWorkingDay()) {
            return false;
        }
        
        $currentTime = $dateTime->format('H:i:s');
        $startTime = $this->getStartTime()->format('H:i:s');
        $endTime = $this->getEndTime()->format('H:i:s');
        
        return $currentTime >= $startTime && $currentTime <= $endTime;
    }
    
    /**
     * Get time until end of working hours
     */
    public function getTimeUntilEnd(\DateTimeImmutable $dateTime): \DateInterval
    {
        $endDateTime = $dateTime->setTime(
            (int) $this->getEndTime()->format('H'),
            (int) $this->getEndTime()->format('i'),
            (int) $this->getEndTime()->format('s')
        );
        
        return $dateTime->diff($endDateTime);
    }
    
    /**
     * Get time until start of working hours
     */
    public function getTimeUntilStart(\DateTimeImmutable $dateTime): \DateInterval
    {
        $startDateTime = $dateTime->setTime(
            (int) $this->getStartTime()->format('H'),
            (int) $this->getStartTime()->format('i'),
            (int) $this->getStartTime()->format('s')
        );
        
        return $dateTime->diff($startDateTime);
    }
    
    /**
     * Create from database row
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
