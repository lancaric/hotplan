#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * HotPlan CLI Entry Point
 * 
 * Command-line interface for managing hotline forwarding.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use HotPlan\Config\ConfigLoader;
use HotPlan\Services\ForwardingService;
use HotPlan\Scheduler\ForwardingScheduler;
use HotPlan\Scheduler\CronRunner;
use HotPlan\VoIP\VoIPProviderFactory;
use HotPlan\Database\Connection;
use HotPlan\Repositories\RuleRepository;
use HotPlan\Repositories\HolidayRepository;
use HotPlan\Repositories\WorkingHoursRepository;
use HotPlan\Repositories\OverrideRepository;
use HotPlan\Repositories\OnCallRepository;
use HotPlan\Repositories\EmployeeRepository;
use HotPlan\Logging\ForwardLogger;

// Parse command line arguments
$command = $argv[1] ?? 'help';
$args = array_slice($argv, 2);

/**
 * Print help message
 */
function printHelp(): void
{
    echo <<<HELP
HotPlan CLI - Hotline Forwarding Management System

Usage: php public/cli.php <command> [options]

Commands:
    forward:execute    Execute a forwarding decision cycle
    forward:preview    Preview forwarding for a date range
    forward:status     Get current forwarding status
    forward:set        Set forwarding number manually
    forward:clear      Clear forwarding
    
    scheduler:start    Start the scheduler daemon
    scheduler:stop     Stop the scheduler daemon
    scheduler:status   Get scheduler status
    
    override:create    Create a temporary override
    override:list      List active overrides
    override:clear     Clear all overrides
    
    rules:list         List all forwarding rules
    rules:create       Create a new rule
    rules:delete       Delete a rule
    
    log:show           Show recent log entries
    log:rotate         Rotate log files
    
    device:test        Test device connection
    device:status      Get device status
    
    db:migrate         Run database migrations
    db:seed            Seed database with sample data
    
    help               Show this help message

Options:
    --date=DATE        Specify date for preview (YYYY-MM-DD)
    --start=DATE       Start date for range preview
    --end=DATE         End date for range preview
    --number=NUMBER    Forwarding number
    --reason=TEXT      Reason for manual action
    --force            Force action without confirmation

Examples:
    php public/cli.php forward:execute
    php public/cli.php forward:preview --start="2024-01-01" --end="2024-01-07"
    php public/cli.php forward:set --number="+421901234567" --reason="Testing"
    php public/cli.php scheduler:start
    php public/cli.php device:test

HELP;
}

/**
 * Parse command line arguments
 */
function parseArgs(array $args): array
{
    $result = [];
    foreach ($args as $arg) {
        if (strpos($arg, '--') === 0) {
            $parts = explode('=', substr($arg, 2), 2);
            $key = $parts[0];
            $value = $parts[1] ?? true;
            $result[$key] = $value;
        }
    }
    return $result;
}

/**
 * Get service instances
 */
function getServices(): array
{
    $config = ConfigLoader::getInstance();
    $db = Connection::getInstance();
    
    $ruleRepo = new RuleRepository($db);
    $holidayRepo = new HolidayRepository($db);
    $workingHoursRepo = new WorkingHoursRepository($db);
    $overrideRepo = new OverrideRepository($db);
    $onCallRepo = new OnCallRepository($db);
    $employeeRepo = new EmployeeRepository($db);
    
    $logger = new ForwardLogger($config);
    
    $voipConfig = $config->getVoipConfig();
    $credentials = $config->getVoipCredentials();
    $voipConfig['username'] = $credentials['username'];
    $voipConfig['password'] = $credentials['password'];
    
    $voipProvider = VoIPProviderFactory::create($voipConfig['provider'], $voipConfig);
    
    $decisionEngine = new \HotPlan\Decision\DecisionEngine(
        $ruleRepo,
        $holidayRepo,
        $workingHoursRepo,
        $overrideRepo,
        $onCallRepo,
        $employeeRepo,
        $config
    );
    
    $forwardingService = new ForwardingService($decisionEngine, $voipProvider, $config);
    $scheduler = new ForwardingScheduler($forwardingService, $config, $logger);
    
    return [
        'config' => $config,
        'logger' => $logger,
        'voip' => $voipProvider,
        'forwarding' => $forwardingService,
        'scheduler' => $scheduler,
    ];
}

/**
 * Execute command
 */
try {
    $args = parseArgs($args);
    
    switch ($command) {
        case 'forward:execute':
            $services = getServices();
            echo "Executing forwarding cycle...\n";
            $result = $services['forwarding']->executeCycle();
            echo json_encode($result->toArray(), JSON_PRETTY_PRINT) . "\n";
            break;
            
        case 'forward:preview':
            $services = getServices();
            $start = $args['start'] ?? date('Y-m-d');
            $end = $args['end'] ?? date('Y-m-d', strtotime('+7 days'));
            
            echo "Previewing forwarding from {$start} to {$end}...\n";
            $results = $services['scheduler']->preview(
                new \DateTimeImmutable($start),
                new \DateTimeImmutable($end),
                $args['interval'] ?? '1 hour'
            );
            
            foreach ($results as $row) {
                echo sprintf(
                    "%s -> %s (%s)\n",
                    $row['datetime'],
                    $row['forward_to'] ?? '(none)',
                    $row['reason']
                );
            }
            break;
            
        case 'forward:status':
            $services = getServices();
            $state = $services['forwarding']->getState();
            echo json_encode($state, JSON_PRETTY_PRINT) . "\n";
            break;
            
        case 'forward:set':
            if (!isset($args['number'])) {
                echo "Error: --number is required\n";
                exit(1);
            }
            $services = getServices();
            $result = $services['forwarding']->setForward(
                $args['number'],
                $args['reason'] ?? 'Manual'
            );
            echo $result->isSuccess() ? "Forwarding set successfully\n" : "Error: " . $result->error . "\n";
            break;
            
        case 'forward:clear':
            $services = getServices();
            $result = $services['forwarding']->clearForward($args['reason'] ?? 'Manual');
            echo $result->isSuccess() ? "Forwarding cleared\n" : "Error: " . $result->error . "\n";
            break;
            
        case 'scheduler:start':
            $services = getServices();
            echo "Starting scheduler...\n";
            $services['scheduler']->start();
            break;
            
        case 'scheduler:stop':
            $services = getServices();
            $services['scheduler']->stop();
            echo "Scheduler stop requested\n";
            break;
            
        case 'scheduler:status':
            $services = getServices();
            $status = $services['scheduler']->getStatus();
            echo json_encode($status, JSON_PRETTY_PRINT) . "\n";
            break;
            
        case 'device:test':
            $services = getServices();
            echo "Testing device connection...\n";
            $result = $services['voip']->testConnection();
            echo json_encode($result->toArray(), JSON_PRETTY_PRINT) . "\n";
            break;
            
        case 'device:status':
            $services = getServices();
            $status = $services['voip']->getDeviceStatus();
            echo json_encode($status, JSON_PRETTY_PRINT) . "\n";
            break;
            
        case 'log:show':
            $services = getServices();
            $logs = $services['logger']->getRecentLogs((int) ($args['lines'] ?? 100));
            foreach ($logs as $log) {
                echo $log . "\n";
            }
            break;
            
        case 'log:rotate':
            $services = getServices();
            $services['logger']->rotateLogs();
            echo "Logs rotated\n";
            break;
            
        case 'db:migrate':
            $config = ConfigLoader::getInstance();
            $db = Connection::getInstance();
            $migrationsPath = dirname(__DIR__) . '/database/migrations';
            $db->migrate($migrationsPath);
            echo "Database migrated\n";
            break;

        case 'db:seed':
            $db = Connection::getInstance();
            $seedFile = dirname(__DIR__) . '/database/seeds/sample_data.sql';
            if (!file_exists($seedFile)) {
                throw new \RuntimeException("Seed file not found: {$seedFile}");
            }
            $sql = file_get_contents($seedFile);
            $db->getPdo()->exec($sql);
            echo "Database seeded\n";
            break;
            
        case 'help':
        default:
            printHelp();
            break;
    }
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Run 'php public/cli.php help' for usage information.\n";
    exit(1);
}
