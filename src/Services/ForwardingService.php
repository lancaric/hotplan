<?php

declare(strict_types=1);

namespace HotPlan\Services;

use HotPlan\Config\ConfigLoader;
use HotPlan\Decision\DecisionEngine;
use HotPlan\Decision\DecisionResult;
use HotPlan\VoIP\VoIPProviderInterface;
use HotPlan\VoIP\DeviceResponse;
use HotPlan\Repositories\StateRepository;
use HotPlan\Logging\ForwardLogger;

/**
 * Forwarding Service
 * 
 * Main service that coordinates forwarding decisions and device updates.
 * Handles change detection, retry logic, and state management.
 */
class ForwardingService
{
    /**
     * State repository for persistent state
     */
    private StateRepository $stateRepository;
    
    /**
     * Forward logger
     */
    private ForwardLogger $logger;
    
    /**
     * Current forwarding value
     */
    private ?string $currentForwardTo = null;
    
    /**
     * Last successful forwarding value
     */
    private ?string $lastSuccessfulForwardTo = null;
    
    /**
     * Consecutive failure count
     */
    private int $consecutiveFailures = 0;
    
    /**
     * Maximum consecutive failures before alerting
     */
    private int $maxConsecutiveFailures = 5;
    
    /**
     * Device status
     */
    private string $deviceStatus = 'unknown';
    
    public function __construct(
        private readonly DecisionEngine $decisionEngine,
        private readonly VoIPProviderInterface $voipProvider,
        private readonly ConfigLoader $config,
        ?StateRepository $stateRepository = null,
        ?ForwardLogger $logger = null,
    ) {
        $this->stateRepository = $stateRepository ?? new StateRepository();
        $this->logger = $logger ?? new ForwardLogger($config);
        $this->loadState();
    }
    
    /**
     * Load persisted state
     */
    private function loadState(): void
    {
        $this->currentForwardTo = $this->stateRepository->get('current_forward_to');
        $this->lastSuccessfulForwardTo = $this->stateRepository->get('last_successful_forward_to');
        $this->consecutiveFailures = (int) $this->stateRepository->get('consecutive_failures', 0);
        $this->deviceStatus = $this->stateRepository->get('device_status', 'unknown');
    }
    
    /**
     * Save current state
     */
    private function saveState(): void
    {
        $this->stateRepository->set('current_forward_to', $this->currentForwardTo ?? '');
        $this->stateRepository->set('last_successful_forward_to', $this->lastSuccessfulForwardTo ?? '');
        $this->stateRepository->set('consecutive_failures', (string) $this->consecutiveFailures);
        $this->stateRepository->set('device_status', $this->deviceStatus);
        $this->stateRepository->set('last_check_at', date('Y-m-d H:i:s'));
    }
    
    /**
     * Execute a forwarding decision cycle
     * 
     * This is the main method called by the scheduler.
     * 
     * @param \DateTimeImmutable|null $dateTime The datetime to evaluate for
     * @return ForwardingResult
     */
    public function executeCycle(?\DateTimeImmutable $dateTime = null): ForwardingResult
    {
        $dateTime = $dateTime ?? new \DateTimeImmutable();
        
        // Step 1: Make a decision
        $decision = $this->decisionEngine->decide($dateTime);
        
        // Step 2: Check if the value has changed
        $previousValue = $this->currentForwardTo;
        $hasChanged = $this->hasForwardChanged($decision->forwardTo, $previousValue);
        
        // Step 3: If changed, send to device
        $deviceResponse = null;
        $success = true;
        $errorMessage = null;
        
        if ($hasChanged && $decision->hasDecision()) {
            $deviceResponse = $this->sendToDevice($decision->forwardTo);
            $success = $deviceResponse->isSuccess();
            $errorMessage = $deviceResponse->error;
            
            if ($success) {
                $this->currentForwardTo = $decision->forwardTo;
                $this->lastSuccessfulForwardTo = $decision->forwardTo;
                $this->consecutiveFailures = 0;
                $this->deviceStatus = 'ok';
            } else {
                $this->consecutiveFailures++;
                $this->deviceStatus = 'error';
            }
        } elseif (!$decision->hasDecision() && $previousValue !== null) {
            // Decision is to not forward, clear current setting
            $behavior = $this->config->get('behavior.on_no_rule', 'fallback');
            
            if ($behavior === 'nothing' || $behavior === 'last_known') {
                // Keep current setting
                $success = true;
            } elseif ($behavior === 'clear') {
                $deviceResponse = $this->clearDevice();
                $success = $deviceResponse->isSuccess();
                
                if ($success) {
                    $this->currentForwardTo = '';
                    $this->deviceStatus = 'cleared';
                }
            }
        }
        
        // Step 4: Save state
        $this->saveState();
        
        // Step 5: Log the action
        $this->logAction($dateTime, $decision, $previousValue, $deviceResponse);
        
        // Step 6: Check for alert conditions
        $alert = $this->checkAlertConditions();
        
        return new ForwardingResult(
            success: $success,
            forwardTo: $this->currentForwardTo,
            previousForwardTo: $previousValue,
            hasChanged: $hasChanged,
            decision: $decision,
            deviceResponse: $deviceResponse,
            errorMessage: $errorMessage,
            consecutiveFailures: $this->consecutiveFailures,
            alert: $alert,
        );
    }
    
    /**
     * Check if forwarding value has changed
     */
    private function hasForwardChanged(?string $newValue, ?string $currentValue): bool
    {
        // Both null or empty - no change
        if (empty($newValue) && empty($currentValue)) {
            return false;
        }
        
        // One is empty, other is not - change
        if (empty($newValue) !== empty($currentValue)) {
            return true;
        }
        
        // Both have values - compare
        return $newValue !== $currentValue;
    }
    
    /**
     * Send forwarding value to device
     */
    private function sendToDevice(string $forwardTo): DeviceResponse
    {
        $this->logger->debug('Sending forward to device', [
            'forward_to' => $forwardTo,
            'device' => $this->voipProvider->getType(),
        ]);

        $resp = $this->voipProvider->setForwardTo($forwardTo);

        $meta = $resp->metadata;
        $meta['provider'] = $this->voipProvider->getType();
        $meta['http_code'] = $resp->httpCode;

        $this->logger->logDevice(
            'set_forward',
            $forwardTo,
            $resp->isSuccess(),
            $resp->error,
            $meta
        );

        $this->logger->debug('Device response', [
            'success' => $resp->success,
            'http_code' => $resp->httpCode,
            'error' => $resp->error,
            'metadata' => $resp->metadata,
        ]);

        return $resp;
    }
    
    /**
     * Clear forwarding on device
     */
    private function clearDevice(): DeviceResponse
    {
        $this->logger->debug('Clearing forward on device');
        
        return $this->voipProvider->clearForward();
    }
    
    /**
     * Log the forwarding action
     */
    private function logAction(
        \DateTimeImmutable $dateTime,
        DecisionResult $decision,
        ?string $previousValue,
        ?DeviceResponse $deviceResponse,
    ): void {
        $this->logger->info('Forwarding decision', [
            'datetime' => $dateTime->format('Y-m-d H:i:s'),
            'forward_to' => $decision->forwardTo,
            'previous_forward_to' => $previousValue,
            'has_changed' => $this->hasForwardChanged($decision->forwardTo, $previousValue),
            'rule_name' => $decision->matchedRule?->getName(),
            'rule_type' => $decision->matchedRule?->getRuleType(),
            'reason' => $decision->reason,
            'device_success' => $deviceResponse?->isSuccess(),
            'device_error' => $deviceResponse?->error,
            'device_http_code' => $deviceResponse?->httpCode,
            'device_effective_url' => $deviceResponse?->metadata['effective_url'] ?? null,
            'device_verified' => $deviceResponse?->metadata['verified'] ?? null,
            'consecutive_failures' => $this->consecutiveFailures,
        ]);
    }
    
    /**
     * Check for alert conditions
     */
    private function checkAlertConditions(): ?array
    {
        $alerts = [];
        
        // Check consecutive failures
        if ($this->consecutiveFailures >= $this->maxConsecutiveFailures) {
            $alerts[] = [
                'type' => 'device_failure',
                'severity' => 'critical',
                'message' => "Device has failed {$this->consecutiveFailures} consecutive times",
                'data' => [
                    'consecutive_failures' => $this->consecutiveFailures,
                    'last_successful' => $this->lastSuccessfulForwardTo,
                ],
            ];
        }
        
        // Check device unreachable
        if ($this->deviceStatus === 'error' && !$this->voipProvider->isReachable()) {
            $alerts[] = [
                'type' => 'device_unreachable',
                'severity' => 'critical',
                'message' => 'VoIP device is not reachable',
            ];
        }
        
        // Check if using fallback for extended time
        $lastChange = $this->stateRepository->get('last_successful_change_at');
        if ($lastChange !== null) {
            $lastChangeTime = new \DateTimeImmutable($lastChange);
            $hoursSinceChange = (time() - $lastChangeTime->getTimestamp()) / 3600;
            
            if ($hoursSinceChange > 24) {
                $alerts[] = [
                    'type' => 'prolonged_fallback',
                    'severity' => 'warning',
                    'message' => 'Using current setting for over 24 hours',
                    'data' => [
                        'hours' => round($hoursSinceChange, 1),
                    ],
                ];
            }
        }
        
        return empty($alerts) ? null : $alerts;
    }
    
    /**
     * Manually set forwarding (override)
     */
    public function setForward(string $forwardTo, ?string $reason = null): DeviceResponse
    {
        $response = $this->sendToDevice($forwardTo);
        
        if ($response->isSuccess()) {
            $this->currentForwardTo = $forwardTo;
            $this->lastSuccessfulForwardTo = $forwardTo;
            $this->consecutiveFailures = 0;
            $this->deviceStatus = 'ok';
            $this->stateRepository->set('last_successful_change_at', date('Y-m-d H:i:s'));
            $this->saveState();
            
            $this->logger->info('Manual forwarding set', [
                'forward_to' => $forwardTo,
                'reason' => $reason,
            ]);
        }
        
        return $response;
    }
    
    /**
     * Clear forwarding
     */
    public function clearForward(?string $reason = null): DeviceResponse
    {
        $response = $this->clearDevice();
        
        if ($response->isSuccess()) {
            $this->currentForwardTo = '';
            $this->deviceStatus = 'cleared';
            $this->saveState();
            
            $this->logger->info('Forwarding cleared', [
                'reason' => $reason,
            ]);
        }
        
        return $response;
    }
    
    /**
     * Get current state
     */
    public function getState(): array
    {
        return [
            'current_forward_to' => $this->currentForwardTo,
            'last_successful_forward_to' => $this->lastSuccessfulForwardTo,
            'consecutive_failures' => $this->consecutiveFailures,
            'device_status' => $this->deviceStatus,
            'device_reachable' => $this->voipProvider->isReachable(),
            'last_check_at' => $this->stateRepository->get('last_check_at'),
        ];
    }
    
    /**
     * Get current forwarding number
     */
    public function getCurrentForwardTo(): ?string
    {
        return $this->currentForwardTo;
    }
    
    /**
     * Check if device is healthy
     */
    public function isHealthy(): bool
    {
        return $this->deviceStatus === 'ok' && $this->consecutiveFailures < $this->maxConsecutiveFailures;
    }
    
    /**
     * Force a refresh (ignore change detection)
     */
    public function forceRefresh(?\DateTimeImmutable $dateTime = null): ForwardingResult
    {
        // Clear current value to force update
        $previousValue = $this->currentForwardTo;
        $this->currentForwardTo = null;
        
        return $this->executeCycle($dateTime);
    }
    
    /**
     * Get device status
     */
    public function getDeviceStatus(): array
    {
        return $this->voipProvider->getDeviceStatus();
    }
    
    /**
     * Test device connection
     */
    public function testConnection(): DeviceResponse
    {
        return $this->voipProvider->testConnection();
    }
}

/**
 * Forwarding Result
 * 
 * Result of a forwarding decision cycle.
 */
class ForwardingResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $forwardTo,
        public readonly ?string $previousForwardTo,
        public readonly bool $hasChanged,
        public readonly DecisionResult $decision,
        public readonly ?DeviceResponse $deviceResponse,
        public readonly ?string $errorMessage,
        public readonly int $consecutiveFailures,
        public readonly ?array $alert,
    ) {}
    
    /**
     * Check if operation was successful
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }
    
    /**
     * Check if there was an alert
     */
    public function hasAlert(): bool
    {
        return $this->alert !== null;
    }
    
    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'forward_to' => $this->forwardTo,
            'previous_forward_to' => $this->previousForwardTo,
            'has_changed' => $this->hasChanged,
            'decision' => $this->decision->toArray(),
            'device_success' => $this->deviceResponse?->isSuccess(),
            'device_error' => $this->deviceResponse?->error,
            'error_message' => $this->errorMessage,
            'consecutive_failures' => $this->consecutiveFailures,
            'alert' => $this->alert,
        ];
    }
}
