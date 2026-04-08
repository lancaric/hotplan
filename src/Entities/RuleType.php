<?php

declare(strict_types=1);

namespace HotPlan\Entities;

/**
 * Forwarding Rule Types
 */
class RuleType
{
    public const OVERRIDE = 'override';           // Manual override (highest priority)
    public const EVENT = 'event';                 // One-time event
    public const ONCALL_ROTATION = 'oncall_rotation'; // Recurring on-call rotation
    public const WORKING_HOURS = 'working_hours'; // Based on working hours
    public const HOLIDAY = 'holiday';             // Holiday-specific
    public const FALLBACK = 'fallback';           // Default fallback (lowest priority)

    /**
     * Priority ranges for each rule type
     */
    public const PRIORITIES = [
        self::OVERRIDE => [1, 10],
        self::EVENT => [11, 20],
        self::ONCALL_ROTATION => [21, 30],
        self::HOLIDAY => [31, 40],
        self::WORKING_HOURS => [41, 50],
        self::FALLBACK => [91, 100],
    ];

    /**
     * Get priority range for rule type
     */
    public static function getPriorityRange(string $type): array
    {
        return self::PRIORITIES[$type] ?? [50, 50];
    }

    /**
     * Check if priority is within range for type
     */
    public static function isPriorityInRange(string $type, int $priority): bool
    {
        [$min, $max] = self::getPriorityRange($type);
        return $priority >= $min && $priority <= $max;
    }
}

