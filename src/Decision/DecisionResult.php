<?php

declare(strict_types=1);

namespace HotPlan\Decision;

use HotPlan\Entities\Employee;
use HotPlan\Entities\ForwardingRule;

/**
 * Contains the result of the decision process.
 */
class DecisionResult
{
    public readonly ?string $forwardTo;
    public readonly ?ForwardingRule $matchedRule;
    public readonly ?Employee $targetEmployee;
    public readonly string $reason;
    public readonly array $metadata;
    public readonly bool $changed;
    public readonly ?string $previousValue;

    public function __construct(
        ?string $forwardTo,
        ?ForwardingRule $matchedRule,
        ?Employee $targetEmployee,
        string $reason,
        array $metadata = [],
        ?string $previousValue = null,
        ?bool $changed = null,
    ) {
        $this->forwardTo = $forwardTo;
        $this->matchedRule = $matchedRule;
        $this->targetEmployee = $targetEmployee;
        $this->reason = $reason;
        $this->metadata = $metadata;
        $this->previousValue = $previousValue;

        $this->changed = $changed ?? ($previousValue === null ? true : $previousValue !== $forwardTo);
    }

    public function hasDecision(): bool
    {
        return $this->forwardTo !== null && $this->forwardTo !== '';
    }

    public function toArray(): array
    {
        return [
            'forward_to' => $this->forwardTo,
            'rule_id' => $this->matchedRule?->getId(),
            'rule_name' => $this->matchedRule?->getName(),
            'rule_type' => $this->matchedRule?->getRuleType(),
            'employee_id' => $this->targetEmployee?->getId(),
            'employee_name' => $this->targetEmployee?->getName(),
            'reason' => $this->reason,
            'changed' => $this->changed,
            'previous_value' => $this->previousValue,
            'metadata' => $this->metadata,
        ];
    }
}

