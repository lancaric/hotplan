<?php

declare(strict_types=1);

namespace HotPlan\Scheduler;

use HotPlan\Config\ConfigLoader;
use HotPlan\Services\ForwardingService;
use HotPlan\Logging\ForwardLogger;
use HotPlan\Repositories\StateRepository;

/**
 * Forwarding Scheduler
 * 
 * Manages the automatic execution of forwarding decisions.
 */
class ForwardingScheduler
{
    private bool $running = false;
    private bool $shouldStop = false;
    private int $checkInterval;
    private int $preloadMinutes;
    private bool $enabled;
    
    private ForwardingService $forwardingService;
    private StateRepository $stateRepository;
    private ForwardLogger $logger;
    
    /**
     * Scheduler status
     */
    public const STATUS_STOPPED = 'stopped';
    public const STATUS_RUNNING = 'running';
    public const STATUS_PAUSED = 'paused';
    
    public function __construct(
        ForwardingService $forwardingService,
        ?ConfigLoader $config = null,
        ?ForwardLogger $logger = null,
        ?StateRepository $stateRepository = null,
    ) {
        $this->forwardingService = $forwardingService;
        $this->logger = $logger ?? new ForwardLogger($config);
        $this->stateRepository = $stateRepository ?? new StateRepository();
        
        $config = $config ?? ConfigLoader::getInstance();
        $this->enabled = (bool) $config->get('scheduler.enabled', true);
        $this->checkInterval = (int) $config->get('scheduler.check_interval', 60);
        $this->preloadMinutes = (int) $config->get('scheduler.preload_minutes', 5);
    }
    
    /**
     * Start the scheduler
     */
    public function start(): void
    {
        if ($this->running) {
            $this->logger->warning('Scheduler is already running');
            return;
        }
        
        if (!$this->enabled) {
            $this->logger->warning('Scheduler is disabled in configuration');
            return;
        }
        
        $this->running = true;
        $this->shouldStop = false;
        $this->stateRepository->setSchedulerStatus(self::STATUS_RUNNING);
        
        $this->logger->info('Scheduler started', [
            'check_interval' => $this->checkInterval,
            'preload_minutes' => $this->preloadMinutes,
        ]);
        
        $this->run();
    }
    
    /**
     * Stop the scheduler
     */
    public function stop(): void
    {
        $this->shouldStop = true;
        $this->stateRepository->setSchedulerStatus(self::STATUS_STOPPED);
        
        $this->logger->info('Scheduler stop requested');
    }
    
    /**
     * Pause the scheduler
     */
    public function pause(): void
    {
        $this->running = false;
        $this->stateRepository->setSchedulerStatus(self::STATUS_PAUSED);
        
        $this->logger->info('Scheduler paused');
    }
    
    /**
     * Resume the scheduler
     */
    public function resume(): void
    {
        if (!$this->running) {
            $this->running = true;
            $this->stateRepository->setSchedulerStatus(self::STATUS_RUNNING);
            
            $this->logger->info('Scheduler resumed');
            $this->run();
        }
    }
    
    /**
     * Check if scheduler is running
     */
    public function isRunning(): bool
    {
        return $this->running;
    }
    
    /**
     * Execute a single cycle
     */
    public function executeCycle(): array
    {
        $this->logger->debug('Executing forwarding cycle');
        
        $result = $this->forwardingService->executeCycle();
        
        $this->logger->info('Forwarding cycle completed', [
            'success' => $result->isSuccess(),
            'forward_to' => $result->forwardTo,
            'has_changed' => $result->hasChanged,
            'decision_reason' => $result->decision->reason,
        ]);
        
        return $result->toArray();
    }
    
    /**
     * Run the scheduler loop
     */
    private function run(): void
    {
        $lastCheck = null;
        
        while (!$this->shouldStop) {
            $now = time();
            
            // Calculate next check time
            if ($lastCheck === null) {
                $nextCheck = $now;
            } else {
                $nextCheck = $lastCheck + $this->checkInterval;
            }
            
            // Check if we need to execute
            if ($now >= $nextCheck) {
                try {
                    // Check for preload activation
                    $this->checkPreloadActivation();
                    
                    // Execute cycle
                    $result = $this->forwardingService->executeCycle();
                    
                    // Log result
                    if ($result->hasAlert()) {
                        $this->logger->warning('Alert during forwarding cycle', [
                            'alert' => $result->alert,
                        ]);
                    }
                    
                    if (!$result->isSuccess() && $result->errorMessage !== null) {
                        $this->logger->error('Forwarding cycle failed', [
                            'error' => $result->errorMessage,
                        ]);
                    }
                } catch (\Throwable $e) {
                    $this->logger->error('Exception during forwarding cycle', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
                
                $lastCheck = $now;
            }
            
            // Sleep for a short time
            sleep(1);
        }
        
        $this->running = false;
        $this->logger->info('Scheduler stopped');
    }
    
    /**
     * Check for preload activation (rules that should start soon)
     */
    private function checkPreloadActivation(): void
    {
        $preloadTime = new \DateTimeImmutable("+{$this->preloadMinutes} minutes");
        
        // This would check for rules that should be activated within the preload window
        // and trigger them early if needed
        $this->logger->debug('Checking preload activation', [
            'preload_time' => $preloadTime->format('Y-m-d H:i:s'),
        ]);
    }
    
    /**
     * Get scheduler status
     */
    public function getStatus(): array
    {
        return [
            'running' => $this->running,
            'enabled' => $this->enabled,
            'check_interval' => $this->checkInterval,
            'preload_minutes' => $this->preloadMinutes,
            'system_status' => $this->stateRepository->getSchedulerStatus(),
            'last_check' => $this->stateRepository->getLastCheckAt()?->format('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * Set check interval
     */
    public function setCheckInterval(int $seconds): void
    {
        $this->checkInterval = max(1, $seconds);
        $this->logger->info('Check interval updated', [
            'interval' => $this->checkInterval,
        ]);
    }
    
    /**
     * Set preload minutes
     */
    public function setPreloadMinutes(int $minutes): void
    {
        $this->preloadMinutes = max(0, $minutes);
        $this->logger->info('Preload minutes updated', [
            'minutes' => $this->preloadMinutes,
        ]);
    }
    
    /**
     * Enable/disable scheduler
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
        $this->logger->info('Scheduler enabled status changed', [
            'enabled' => $this->enabled,
        ]);
        
        if (!$this->enabled && $this->running) {
            $this->pause();
        }
    }
    
    /**
     * Get next scheduled execution time
     */
    public function getNextExecutionTime(): ?\DateTimeImmutable
    {
        $lastCheck = $this->stateRepository->getLastCheckAt();
        
        if ($lastCheck === null) {
            return new \DateTimeImmutable();
        }
        
        return $lastCheck->modify("+{$this->checkInterval} seconds");
    }
    
    /**
     * Force an immediate execution
     */
    public function forceExecution(): array
    {
        $this->logger->info('Forcing immediate execution');
        
        return $this->executeCycle();
    }
    
    /**
     * Preview forwarding for a time range
     */
    public function preview(\DateTimeImmutable $start, \DateTimeImmutable $end, string $interval = '1 hour'): array
    {
        $this->logger->info('Generating forwarding preview', [
            'start' => $start->format('Y-m-d H:i'),
            'end' => $end->format('Y-m-d H:i'),
            'interval' => $interval,
        ]);
        
        $results = [];
        $current = $start;
        
        while ($current <= $end) {
            $result = $this->forwardingService->executeCycle($current);
            
            $results[] = [
                'datetime' => $current->format('Y-m-d H:i'),
                'forward_to' => $result->forwardTo,
                'reason' => $result->decision->reason,
                'rule_name' => $result->decision->matchedRule?->getName(),
                'rule_type' => $result->decision->matchedRule?->getRuleType(),
            ];
            
            $current = $current->modify("+{$interval}");
        }
        
        return $results;
    }
}
