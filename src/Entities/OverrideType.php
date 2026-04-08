<?php

declare(strict_types=1);

namespace HotPlan\Entities;

/**
 * Override Types
 */
class OverrideType
{
    public const TEMPORARY = 'temporary';         // Time-based override
    public const INDEFINITE = 'indefinite';       // Until manually disabled
    public const UNTIL_TIME = 'until_time';       // Until specific time
    public const UNTIL_EMPLOYEE = 'until_employee'; // Until specific employee takes over
}

