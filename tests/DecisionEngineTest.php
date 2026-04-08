<?php

declare(strict_types=1);

namespace HotPlan\Tests;

use PHPUnit\Framework\TestCase;
use HotPlan\Config\ConfigLoader;
use HotPlan\Decision\DecisionEngine;
use HotPlan\Entities\ForwardingRule;
use HotPlan\Entities\Employee;
use HotPlan\Entities\Holiday;
use HotPlan\Entities\WorkingHours;
use HotPlan\Entities\OverrideRule;
use HotPlan\Entities\RuleType;
use HotPlan\Entities\TargetType;
use HotPlan\Repositories\RuleRepository;
use HotPlan\Repositories\HolidayRepository;
use HotPlan\Repositories\WorkingHoursRepository;
use HotPlan\Repositories\OverrideRepository;
use HotPlan\Repositories\OnCallRepository;
use HotPlan\Repositories\EmployeeRepository;

/**
 * Decision Engine Unit Tests
 */
class DecisionEngineTest extends TestCase
{
    private DecisionEngine $engine;
    private ConfigLoader $config;
    
    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigLoader::class);
        $this->config->method('get')
            ->willReturnMap([
                ['behavior.on_no_rule', 'fallback', 'fallback'],
                ['behavior.on_multiple_match', 'priority', 'priority'],
            ]);
        
        // Create mock repositories
        $ruleRepo = $this->createMock(RuleRepository::class);
        $holidayRepo = $this->createMock(HolidayRepository::class);
        $workingHoursRepo = $this->createMock(WorkingHoursRepository::class);
        $overrideRepo = $this->createMock(OverrideRepository::class);
        $onCallRepo = $this->createMock(OnCallRepository::class);
        $employeeRepo = $this->createMock(EmployeeRepository::class);
        
        // Configure default returns
        $ruleRepo->method('findMatchingRules')->willReturn([]);
        $ruleRepo->method('findById')->willReturn(null);
        $holidayRepo->method('findForDate')->willReturn(null);
        $overrideRepo->method('findActive')->willReturn(null);
        $onCallRepo->method('findActiveForDateTime')->willReturn(null);
        $employeeRepo->method('findByRotationGroup')->willReturn([]);
        
        $this->engine = new DecisionEngine(
            $ruleRepo,
            $holidayRepo,
            $workingHoursRepo,
            $overrideRepo,
            $onCallRepo,
            $employeeRepo,
            $this->config
        );
    }
    
    public function testDecideWithNoMatchingRulesReturnsFallback(): void
    {
        $result = $this->engine->decide();
        
        $this->assertFalse($result->hasDecision());
        $this->assertEquals('Fallback (fallback)', $result->reason);
    }
    
    public function testOverrideHasHighestPriority(): void
    {
        // Create an active override
        $override = new OverrideRule([
            'id' => 1,
            'override_type' => 'indefinite',
            'is_active' => 1,
            'forward_to' => '999',
            'reason' => 'Test override',
        ]);
        
        // This would require setting up the mock to return the override
        $this->assertEquals(1, $override->getPriority()); // Highest priority
        $this->assertTrue($override->isActive());
        $this->assertEquals('999', $override->getForwardTo());
    }
    
    public function testRulePriorityOrdering(): void
    {
        // Test priority ranges
        $this->assertEquals([1, 10], RuleType::getPriorityRange(RuleType::OVERRIDE));
        $this->assertEquals([91, 100], RuleType::getPriorityRange(RuleType::FALLBACK));
        $this->assertTrue(RuleType::isPriorityInRange(RuleType::OVERRIDE, 5));
        $this->assertFalse(RuleType::isPriorityInRange(RuleType::OVERRIDE, 50));
    }
    
    public function testRuleValidityCheck(): void
    {
        $rule = new ForwardingRule([
            'id' => 1,
            'name' => 'Test Rule',
            'rule_type' => RuleType::WORKING_HOURS,
            'priority' => 45,
            'is_active' => 1,
            'valid_from' => '2024-01-01 00:00:00',
            'valid_until' => '2024-12-31 23:59:59',
            'days_of_week' => json_encode([1, 2, 3, 4, 5]), // Mon-Fri
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'forward_to' => '100',
        ]);
        
        // Valid time
        $validTime = new \DateTimeImmutable('2024-06-10 10:00:00'); // Monday 10:00
        $this->assertTrue($rule->isValidAt($validTime));
        
        // Weekend
        $weekend = new \DateTimeImmutable('2024-06-09 10:00:00'); // Sunday
        $this->assertFalse($rule->isValidAt($weekend));
        
        // Outside time range
        $afterHours = new \DateTimeImmutable('2024-06-10 18:00:00'); // Monday 18:00
        $this->assertFalse($rule->isValidAt($afterHours));
        
        // Inactive rule
        $rule->setActive(false);
        $this->assertFalse($rule->isValidAt($validTime));
    }
    
    public function testHolidayAppliesCheck(): void
    {
        $holiday = new Holiday([
            'id' => 1,
            'name' => 'Christmas',
            'date' => '2024-12-25',
            'is_recurring' => 1,
            'is_active' => 1,
        ]);
        
        // Same date in different year should match for recurring
        $christmas2025 = new \DateTimeImmutable('2025-12-25');
        $this->assertTrue($holiday->appliesTo($christmas2025));
        
        // Different date should not match
        $otherDate = new \DateTimeImmutable('2024-12-24');
        $this->assertFalse($holiday->appliesTo($otherDate));
    }
    
    public function testWorkingHoursTimeCheck(): void
    {
        $workingHours = new WorkingHours([
            'id' => 1,
            'day_of_week' => 1, // Monday
            'is_working_day' => 1,
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'forward_to_internal' => '100',
        ]);
        
        // Within working hours
        $duringWork = new \DateTimeImmutable('2024-06-10 10:00:00');
        $this->assertTrue($workingHours->isWithinWorkingHours($duringWork));
        
        // Outside working hours
        $afterWork = new \DateTimeImmutable('2024-06-10 17:00:00');
        $this->assertFalse($workingHours->isWithinWorkingHours($afterWork));
        
        // Before work
        $beforeWork = new \DateTimeImmutable('2024-06-10 07:00:00');
        $this->assertFalse($workingHours->isWithinWorkingHours($beforeWork));
    }
    
    public function testEmployeeForwardingNumber(): void
    {
        $employee = new Employee([
            'id' => 1,
            'name' => 'Test Employee',
            'phone_internal' => '101',
            'phone_mobile' => '+421901234567',
            'phone_primary' => '102',
            'is_active' => 1,
        ]);
        
        // Default to primary
        $this->assertEquals('102', $employee->getForwardingNumber());
        
        // Request mobile
        $this->assertEquals('+421901234567', $employee->getForwardingNumber(true));
    }
    
    public function testDecisionResultToArray(): void
    {
        $rule = new ForwardingRule([
            'id' => 1,
            'name' => 'Test Rule',
            'rule_type' => RuleType::WORKING_HOURS,
            'priority' => 45,
            'forward_to' => '100',
        ]);
        
        $result = new \HotPlan\Decision\DecisionResult(
            forwardTo: '100',
            matchedRule: $rule,
            targetEmployee: null,
            reason: 'Test reason',
            metadata: ['key' => 'value'],
        );
        
        $array = $result->toArray();
        
        $this->assertEquals('100', $array['forward_to']);
        $this->assertEquals(1, $array['rule_id']);
        $this->assertEquals('Test Rule', $array['rule_name']);
        $this->assertEquals('Test reason', $array['reason']);
        $this->assertTrue($array['changed']);
    }
}

/**
 * Entity Unit Tests
 */
class EntityTest extends TestCase
{
    public function testForwardingRuleCreation(): void
    {
        $rule = new ForwardingRule([
            'name' => 'Test Rule',
            'rule_type' => RuleType::ONCALL_ROTATION,
            'priority' => 25,
            'forward_to' => '101',
            'is_active' => true,
        ]);
        
        $this->assertEquals('Test Rule', $rule->getName());
        $this->assertEquals(RuleType::ONCALL_ROTATION, $rule->getRuleType());
        $this->assertEquals(25, $rule->getPriority());
        $this->assertEquals('101', $rule->getForwardTo());
        $this->assertTrue($rule->isActive());
    }
    
    public function testOverrideRuleInEffectCheck(): void
    {
        // Indefinite override should always be in effect
        $indefinite = new OverrideRule([
            'override_type' => 'indefinite',
            'is_active' => 1,
            'forward_to' => '999',
        ]);
        
        $now = new \DateTimeImmutable();
        $this->assertTrue($indefinite->isInEffect($now));
        
        // Time-limited override
        $limited = new OverrideRule([
            'override_type' => 'until_time',
            'is_active' => 1,
            'starts_at' => '2024-01-01 00:00:00',
            'ends_at' => '2024-12-31 23:59:59',
            'forward_to' => '888',
        ]);
        
        $validTime = new \DateTimeImmutable('2024-06-15 12:00:00');
        $this->assertTrue($limited->isInEffect($validTime));
        
        $expiredTime = new \DateTimeImmutable('2025-01-01 12:00:00');
        $this->assertFalse($limited->isInEffect($expiredTime));
    }
    
    public function testRotationGroupPosition(): void
    {
        $group = new RotationGroup([
            'name' => 'Test Group',
            'rotation_type' => 'weekly',
            'rotation_order' => json_encode([1, 2, 3]),
            'rotation_start_date' => '2024-01-01',
            'current_index' => 0,
        ]);
        
        // Week 1 should return first employee
        $week1 = new \DateTimeImmutable('2024-01-07'); // Sunday of week 1
        $position1 = $group->calculateCurrentPosition($week1);
        $this->assertEquals(0, $position1);
        
        // Week 2 should return second employee
        $week2 = new \DateTimeImmutable('2024-01-14');
        $position2 = $group->calculateCurrentPosition($week2);
        $this->assertEquals(1, $position2);
    }
}
