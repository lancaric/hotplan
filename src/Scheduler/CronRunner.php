<?php

declare(strict_types=1);

namespace HotPlan\Scheduler;

use HotPlan\Logging\ForwardLogger;

/**
 * Cron-based Scheduler Runner
 *
 * Can be triggered by system cron or Windows Task Scheduler.
 */
class CronRunner
{
    private ForwardingScheduler $scheduler;
    private ForwardLogger $logger;

    public function __construct(ForwardingScheduler $scheduler, ?ForwardLogger $logger = null)
    {
        $this->scheduler = $scheduler;
        $this->logger = $logger ?? new ForwardLogger();
    }

    /**
     * Run a single cycle (for cron-based execution)
     */
    public function runOnce(): void
    {
        $this->logger->info('Cron runner executing single cycle');

        try {
            $result = $this->scheduler->executeCycle();

            $this->logger->info('Cron cycle completed', [
                'success' => $result['success'],
                'forward_to' => $result['forward_to'],
                'changed' => $result['has_changed'],
            ]);

            if (!$result['success']) {
                exit(1);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Cron cycle exception', [
                'error' => $e->getMessage(),
            ]);
            exit(1);
        }
    }

    /**
     * Run as daemon (continuous)
     */
    public function runDaemon(): void
    {
        $this->logger->info('Starting daemon mode');
        $this->scheduler->start();
    }
}

