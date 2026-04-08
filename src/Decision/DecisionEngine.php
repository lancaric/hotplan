<?php

declare(strict_types=1);

namespace HotPlan\Decision;

use HotPlan\Config\ConfigLoader;
use HotPlan\Entities\Employee;
use HotPlan\Entities\ForwardingRule;
use HotPlan\Entities\Holiday;
use HotPlan\Entities\OverrideRule;
use HotPlan\Entities\WorkingHours;
use HotPlan\Entities\OnCallRotation;
use HotPlan\Entities\RuleType;
use HotPlan\Entities\TargetType;
use HotPlan\Repositories\RuleRepository;
use HotPlan\Repositories\HolidayRepository;
use HotPlan\Repositories\WorkingHoursRepository;
use HotPlan\Repositories\OverrideRepository;
use HotPlan\Repositories\OnCallRepository;
use HotPlan\Repositories\EmployeeRepository;

/**
 * Decision Engine
 * 
 * Core engine that evaluates rules and determines the forwarding number.
 * 
 * Priority Order (lower number = higher priority):
 * 1-10:   Override/Manual
 * 11-20:  Event/Specific time
 * 21-30:  On-call rotation
 * 31-40:  Holiday
 * 41-50:  Working hours
 * 91-100: Fallback
 */
class DecisionEngine
{
    public function __construct(
        private readonly RuleRepository $ruleRepository,
        private readonly HolidayRepository $holidayRepository,
        private readonly WorkingHoursRepository $workingHoursRepository,
        private readonly OverrideRepository $overrideRepository,
        private readonly OnCallRepository $onCallRepository,
        private readonly EmployeeRepository $employeeRepository,
        private readonly ConfigLoader $config,
    ) {}
    
    /**
     * Make a forwarding decision for the given datetime
     */
    public function decide(?\DateTimeImmutable $dateTime = null): DecisionResult
    {
        $dateTime = $dateTime ?? new \DateTimeImmutable();
        $context = $this->buildContext($dateTime);
        
        // Step 1: Check for active override (highest priority)
        if ($context->activeOverride !== null) {
            return $this->createResult(
                $context->activeOverride->getForwardTo(),
                null,
                null,
                'Override active: ' . ($context->activeOverride->getReason() ?? 'Manual override'),
                ['override_id' => $context->activeOverride->getId()]
            );
        }
        
        // Step 2: Check for one-time events
        $eventRule = $this->findMatchingEvent($context);
        if ($eventRule !== null) {
            $forwardTo = $this->resolveRuleTarget($eventRule, $context);
            if ($forwardTo !== null) {
                return $this->createResult(
                    $forwardTo,
                    $eventRule,
                    null,
                    'Event rule: ' . $eventRule->getName()
                );
            }
        }
        
        // Step 3: Check on-call rotation
        $onCallResult = $this->resolveOnCall($context);
        if ($onCallResult !== null) {
            return $onCallResult;
        }
        
        // Step 4: Check holiday rules
        if ($context->isHoliday && $context->currentHoliday !== null) {
            $holidayRule = $this->findHolidayRule($context);
            if ($holidayRule !== null) {
                $forwardTo = $this->resolveRuleTarget($holidayRule, $context);
                if ($forwardTo !== null) {
                    return $this->createResult(
                        $forwardTo,
                        $holidayRule,
                        null,
                        'Holiday: ' . $context->currentHoliday->getName()
                    );
                }
                
                // Holiday has specific forwarding number
                $holidayForward = $context->currentHoliday->getForwardTo();
                if ($holidayForward !== null) {
                    return $this->createResult(
                        $holidayForward,
                        null,
                        null,
                        'Holiday forwarding: ' . $context->currentHoliday->getName(),
                        ['holiday_id' => $context->currentHoliday->getId()]
                    );
                }
            }
        }
        
        // Step 5: Check working hours rules
        if ($context->isWorkingHours && $context->currentWorkingHours !== null) {
            $workingHoursRule = $this->findWorkingHoursRule($context);
            if ($workingHoursRule !== null) {
                $forwardTo = $this->resolveRuleTarget($workingHoursRule, $context);
                if ($forwardTo !== null) {
                    return $this->createResult(
                        $forwardTo,
                        $workingHoursRule,
                        null,
                        'Working hours: ' . $context->currentWorkingHours->getDayName()
                    );
                }
            }
            
            // Use working hours default
            $internalForward = $context->currentWorkingHours->getForwardToInternal();
            if ($internalForward !== null) {
                return $this->createResult(
                    $internalForward,
                    null,
                    null,
                    'Working hours default: ' . $context->currentWorkingHours->getDayName()
                );
            }
        }
        
        // Step 6: After hours handling
        if (!$context->isWorkingHours && $context->currentOnCallEmployee !== null) {
            $mobile = $context->currentOnCallEmployee->getPhoneMobile();
            if ($mobile !== null) {
                return $this->createResult(
                    $mobile,
                    null,
                    $context->currentOnCallEmployee,
                    'After hours mobile: ' . $context->currentOnCallEmployee->getName()
                );
            }
        }
        
        // Step 7: Use fallback
        return $this->resolveFallback($context);
    }
    
    /**
     * Build the decision context
     */
    private function buildContext(\DateTimeImmutable $dateTime): DecisionContext
    {
        // Check for holidays
        $currentHoliday = $this->holidayRepository->findForDate($dateTime);
        $isHoliday = $currentHoliday !== null && !$currentHoliday->isWorkday();
        
        // Get working hours for today
        $dayOfWeek = (int) $dateTime->format('w');
        $currentWorkingHours = $this->workingHoursRepository->findForDay($dayOfWeek);
        $isWorkingHours = $currentWorkingHours !== null 
            && $currentWorkingHours->isWorkingDay() 
            && $currentWorkingHours->isWithinWorkingHours($dateTime);
        
        // Check for active override
        $activeOverride = $this->overrideRepository->findActive($dateTime);
        
        // Get current on-call employee
        $currentOnCallEmployee = $this->findCurrentOnCallEmployee($dateTime);
        
        // Get all matching rules
        $matchingRules = $this->ruleRepository->findMatchingRules($dateTime);
        
        return new DecisionContext(
            dateTime: $dateTime,
            isHoliday: $isHoliday,
            isWorkingHours: $isWorkingHours,
            isWeekend: in_array($dayOfWeek, [0, 6]),
            currentHoliday: $currentHoliday,
            currentWorkingHours: $currentWorkingHours,
            currentOnCallEmployee: $currentOnCallEmployee,
            activeOverride: $activeOverride,
            matchingRules: $matchingRules,
        );
    }
    
    /**
     * Find current on-call employee based on rotation
     */
    private function findCurrentOnCallEmployee(\DateTimeImmutable $dateTime): ?Employee
    {
        $rotation = $this->onCallRepository->findActiveForDateTime($dateTime);
        
        if ($rotation === null) {
            return null;
        }
        
        $group = $rotation->getGroupId();
        $employees = $this->employeeRepository->findByRotationGroup($group);
        
        if (empty($employees)) {
            return null;
        }
        
        // Calculate rotation position
        $position = $rotation->getCurrentWeekNumber($dateTime);
        $index = $position % count($employees);
        
        return $employees[$index] ?? null;
    }
    
    /**
     * Find matching event rule
     */
    private function findMatchingEvent(DecisionContext $context): ?ForwardingRule
    {
        foreach ($context->matchingRules as $rule) {
            if ($rule->getRuleType() === RuleType::EVENT && $rule->isValidAt($context->dateTime)) {
                return $rule;
            }
        }
        
        return null;
    }
    
    /**
     * Find holiday-specific rule
     */
    private function findHolidayRule(DecisionContext $context): ?ForwardingRule
    {
        if ($context->currentHoliday === null) {
            return null;
        }
        
        foreach ($context->matchingRules as $rule) {
            if ($rule->getRuleType() === RuleType::HOLIDAY) {
                $holidayId = $rule->getHolidayId();
                if ($holidayId === $context->currentHoliday->getId()) {
                    return $rule;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Find working hours rule
     */
    private function findWorkingHoursRule(DecisionContext $context): ?ForwardingRule
    {
        foreach ($context->matchingRules as $rule) {
            if ($rule->getRuleType() === RuleType::WORKING_HOURS && $rule->isValidAt($context->dateTime)) {
                return $rule;
            }
        }
        
        return null;
    }
    
    /**
     * Resolve on-call rotation
     */
    private function resolveOnCall(DecisionContext $context): ?DecisionResult
    {
        $rotation = $this->onCallRepository->findActiveForDateTime($context->dateTime);
        
        if ($rotation === null) {
            return null;
        }
        
        // Get current on-call employee
        $employee = $this->findCurrentOnCallEmployee($context->dateTime);
        
        if ($employee === null) {
            // Check for fallback
            if ($rotation->getFallbackRuleId() !== null) {
                $fallbackRule = $this->ruleRepository->findById($rotation->getFallbackRuleId());
                if ($fallbackRule !== null) {
                    $forwardTo = $this->resolveRuleTarget($fallbackRule, $context);
                    if ($forwardTo !== null) {
                        return $this->createResult(
                            $forwardTo,
                            $fallbackRule,
                            null,
                            'On-call fallback: ' . $fallbackRule->getName()
                        );
                    }
                }
            }
            
            return null;
        }
        
        // Determine forwarding number based on working hours
        if ($context->isWorkingHours) {
            $forwardTo = $rotation->getDuringHoursForwardTo();
            
            if ($forwardTo === null && $rotation->useEmployeeMobile()) {
                $forwardTo = $employee->getPhonePrimary();
            }
        } else {
            $forwardTo = $rotation->getAfterHoursForwardTo();
            
            if ($forwardTo === null && $rotation->useEmployeeMobile()) {
                $forwardTo = $employee->getPhoneMobile();
            }
        }
        
        if ($forwardTo === null) {
            $forwardTo = $employee->getForwardingNumber(!$context->isWorkingHours);
        }
        
        if ($forwardTo !== null) {
            return $this->createResult(
                $forwardTo,
                null,
                $employee,
                'On-call: ' . $employee->getName()
            );
        }
        
        return null;
    }
    
    /**
     * Resolve rule target number
     */
    private function resolveRuleTarget(ForwardingRule $rule, DecisionContext $context): ?string
    {
        $targetType = $rule->getTargetType();
        $forwardTo = $rule->getForwardTo();
        
        switch ($targetType) {
            case TargetType::NUMBER:
                return $forwardTo;
                
            case TargetType::EMPLOYEE:
                $employeeId = $rule->getTargetEmployeeId();
                if ($employeeId !== null) {
                    $employee = $this->employeeRepository->findById($employeeId);
                    if ($employee !== null) {
                        $useMobile = !$context->isWorkingHours;
                        return $employee->getForwardingNumber($useMobile);
                    }
                }
                return $forwardTo;
                
            case TargetType::GROUP:
                $groupId = $rule->getTargetGroupId();
                if ($groupId !== null) {
                    $employee = $this->resolveGroupTarget($groupId, $context);
                    if ($employee !== null) {
                        $useMobile = !$context->isWorkingHours;
                        return $employee->getForwardingNumber($useMobile);
                    }
                }
                return $forwardTo;
                
            case TargetType::VOICEMAIL:
                return $this->config->get('defaults.forward_voicemail', '*97');
                
            case TargetType::QUEUE:
                // Queue handling would be implemented here
                return $forwardTo;
                
            default:
                return $forwardTo;
        }
    }
    
    /**
     * Resolve group target to employee
     */
    private function resolveGroupTarget(int $groupId, DecisionContext $context): ?Employee
    {
        $behavior = $this->config->get('behavior.on_multiple_match', 'priority');
        $employees = $this->employeeRepository->findByRotationGroup($groupId);
        
        if (empty($employees)) {
            return null;
        }
        
        switch ($behavior) {
            case 'priority':
                usort($employees, fn($a, $b) => $a->getPriority() <=> $b->getPriority());
                return $employees[0];
                
            case 'random':
                return $employees[array_rand($employees)];
                
            case 'roundrobin':
                // Would need state tracking for round-robin
                return $employees[0];
                
            case 'first_match':
                return $employees[0];
                
            default:
                return $employees[0];
        }
    }
    
    /**
     * Resolve fallback number
     */
    private function resolveFallback(DecisionContext $context): DecisionResult
    {
        $behavior = $this->config->get('behavior.on_no_rule', 'fallback');
        $defaults = $this->config->getDefaults();
        
        $forwardTo = match ($behavior) {
            'fallback' => $defaults['fallback'] ?? '',
            'voicemail' => $defaults['forward_voicemail'] ?? '',
            'nothing' => '',
            'last_known' => $this->getLastKnownForwardTo(),
            default => $defaults['fallback'] ?? '',
        };
        
        return $this->createResult(
            $forwardTo,
            null,
            null,
            'Fallback (' . $behavior . ')'
        );
    }
    
    /**
     * Get last known forwarding number
     */
    private function getLastKnownForwardTo(): ?string
    {
        // This would be retrieved from state storage
        return null;
    }
    
    /**
     * Create a decision result
     */
    private function createResult(
        ?string $forwardTo,
        ?ForwardingRule $rule,
        ?Employee $employee,
        string $reason,
        array $metadata = [],
    ): DecisionResult {
        return new DecisionResult(
            forwardTo: $forwardTo,
            matchedRule: $rule,
            targetEmployee: $employee,
            reason: $reason,
            metadata: $metadata,
        );
    }
    
    /**
     * Preview decisions for a time range
     */
    public function previewRange(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        \DateInterval $interval,
    ): array {
        $results = [];
        $current = $start;
        
        while ($current <= $end) {
            $results[] = [
                'datetime' => $current->format('Y-m-d H:i'),
                'decision' => $this->decide($current),
            ];
            $current = $current->add($interval);
        }
        
        return $results;
    }
    
    /**
     * Get the next expected change time
     */
    public function getNextChangeTime(?\DateTimeImmutable $from = null): ?\DateTimeImmutable
    {
        $from = $from ?? new \DateTimeImmutable();
        
        // Check for upcoming rule changes
        $upcomingRules = $this->ruleRepository->findUpcomingChanges($from);
        
        // Check for working hours transitions
        $workingHours = $this->workingHoursRepository->findForDay((int) $from->format('w'));
        
        $changeTimes = [];
        
        if ($workingHours !== null) {
            // End of working hours
            $endTime = $workingHours->getEndTime();
            if ($endTime > $from) {
                $changeTimes[] = $from->setTime(
                    (int) $endTime->format('H'),
                    (int) $endTime->format('i'),
                    0
                );
            }
            
            // Start of next working day
            $nextDay = $from->modify('+1 day');
            $nextWorkingHours = $this->workingHoursRepository->findForDay((int) $nextDay->format('w'));
            if ($nextWorkingHours !== null && $nextWorkingHours->isWorkingDay()) {
                $startTime = $nextWorkingHours->getStartTime();
                $changeTimes[] = $nextDay->setTime(
                    (int) $startTime->format('H'),
                    (int) $startTime->format('i'),
                    0
                );
            }
        }
        
        // Add upcoming rule changes
        foreach ($upcomingRules as $rule) {
            $validFrom = $rule->getValidFrom();
            if ($validFrom !== null && $validFrom > $from) {
                $changeTimes[] = $validFrom;
            }
        }
        
        if (empty($changeTimes)) {
            return null;
        }
        
        sort($changeTimes);
        return $changeTimes[0];
    }
}
