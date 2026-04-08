<?php

declare(strict_types=1);

namespace HotPlan\Decision;

use HotPlan\Entities\Employee;
use HotPlan\Entities\Holiday;
use HotPlan\Entities\OverrideRule;
use HotPlan\Entities\WorkingHours;

/**
 * Contains all the context information needed for decision making.
 */
class DecisionContext
{
    public function __construct(
        public readonly \DateTimeImmutable $dateTime,
        public readonly bool $isHoliday,
        public readonly bool $isWorkingHours,
        public readonly bool $isWeekend,
        public readonly ?Holiday $currentHoliday = null,
        public readonly ?WorkingHours $currentWorkingHours = null,
        public readonly ?Employee $currentOnCallEmployee = null,
        public readonly ?OverrideRule $activeOverride = null,
        public readonly array $matchingRules = [],
    ) {}

    /**
     * Get current day of week (0=Sunday, 6=Saturday)
     */
    public function getDayOfWeek(): int
    {
        return (int) $this->dateTime->format('w');
    }

    public function getTimeString(): string
    {
        return $this->dateTime->format('H:i:s');
    }

    public function getDateString(): string
    {
        return $this->dateTime->format('Y-m-d');
    }
}

