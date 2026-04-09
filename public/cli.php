#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * HotPlan CLI Entry Point
 * 
 * Command-line interface for managing hotline forwarding.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables from .env if present (optional).
if (class_exists(\Dotenv\Dotenv::class)) {
    $dotenvPath = dirname(__DIR__);
    if (is_file($dotenvPath . '/.env')) {
        // "Unsafe" loads into getenv()/putenv as well (needed because ConfigLoader uses getenv()).
        \Dotenv\Dotenv::createUnsafeImmutable($dotenvPath)->safeLoad();
    }
}

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

// Apply configured timezone as early as possible (affects DateTimeImmutable('now'), scheduling, etc.).
try {
    $cfg = ConfigLoader::getInstance();
    $tz = (string) ($cfg->get('app.timezone', 'UTC') ?? 'UTC');
    if ($tz !== '') {
        date_default_timezone_set($tz);
    }
} catch (\Throwable) {
    // Ignore timezone errors; CLI will fall back to php.ini/system timezone.
}

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

    setup:bootstrap    Reset DB + apply baseline setup
    setup:apply-v2     Apply updated default schedule (non-destructive)
    
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
    php public/cli.php setup:bootstrap --force
    php public/cli.php setup:apply-v2 --force

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
        'decision' => $decisionEngine,
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
        case 'setup:apply-v2':
            if (!isset($args['force']) || $args['force'] !== true) {
                echo "Error: This command updates working hours/rotations/rules. Re-run with --force\n";
                exit(1);
            }

            $db = Connection::getInstance();
            $pdo = $db->getPdo();

            // Ensure schema exists
            $migrationsPath = dirname(__DIR__) . '/database/migrations';
            $db->migrate($migrationsPath);

            $now = new \DateTimeImmutable('now');

            $db->beginTransaction();
            try {
                // 1) Working hours: Mon-Fri 08:00-16:00 => 366, weekend non-working
                foreach ([1, 2, 3, 4, 5] as $day) {
                    $row = $db->fetch('SELECT id FROM working_hours WHERE day_of_week = ? AND is_active = 1 LIMIT 1', [$day]);
                    $data = [
                        'day_of_week' => $day,
                        'is_working_day' => 1,
                        'start_time' => '08:00:00',
                        'end_time' => '16:00:00',
                        'forward_to_internal' => '366',
                        'forward_to_external' => null,
                        'effective_from' => null,
                        'effective_until' => null,
                        'is_active' => 1,
                    ];
                    if ($row) {
                        $db->update('working_hours', $data + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [(int) $row['id']]);
                    } else {
                        $db->insert('working_hours', $data + ['created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                    }
                }
                foreach ([6, 0] as $day) {
                    $row = $db->fetch('SELECT id FROM working_hours WHERE day_of_week = ? AND is_active = 1 LIMIT 1', [$day]);
                    $data = [
                        'day_of_week' => $day,
                        'is_working_day' => 0,
                        'start_time' => '00:00:00',
                        'end_time' => '00:00:00',
                        'forward_to_internal' => null,
                        'forward_to_external' => null,
                        'effective_from' => null,
                        'effective_until' => null,
                        'is_active' => 1,
                    ];
                    if ($row) {
                        $db->update('working_hours', $data + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [(int) $row['id']]);
                    } else {
                        $db->insert('working_hours', $data + ['created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                    }
                }

                // 2) Ensure Marek exists
                $marek = $db->fetch('SELECT id, phone_mobile, phone_internal FROM employees WHERE name = ? LIMIT 1', ['Marek Piaček']);
                if ($marek) {
                    $marekId = (int) $marek['id'];
                    $marekNumber = (string) (($marek['phone_mobile'] ?: ($marek['phone_internal'] ?: '104')));
                } else {
                    $marekId = $db->insert('employees', [
                        'name' => 'Marek Piaček',
                        'email' => null,
                        'phone_internal' => '104',
                        'phone_mobile' => '104',
                        'phone_primary' => null,
                        'is_active' => 1,
                        'is_oncall' => 0,
                        'priority' => 1,
                        'rotation_group_id' => null,
                        'metadata' => null,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $marekNumber = '104';
                }

                // 3) Restrict on-call rotations to Mon-Fri 16:00-22:00 (still weekly rotation)
                $db->update('oncall_rotations', [
                    'is_24x7' => 0,
                    'default_start_time' => '16:00:00',
                    'default_end_time' => '22:00:00',
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'is_active = 1', []);

                // 4) Event rules for Marek:
                // - Weekend: Sat+Sun whole day
                // - Night: Mon-Fri 22:00-08:00 (overnight)
                $rules = [
                    [
                        'name' => 'Weekend - Marek',
                        'priority' => 11,
                        'days_of_week' => json_encode([6, 0]),
                        'start_time' => null,
                        'end_time' => null,
                        'description' => 'Víkend (sobota/nedeľa) vždy Marek',
                    ],
                    [
                        'name' => 'Night - Marek',
                        'priority' => 12,
                        'days_of_week' => json_encode([1, 2, 3, 4, 5]),
                        'start_time' => '22:00:00',
                        'end_time' => '08:00:00',
                        'description' => 'Po–Pi 22:00–08:00 vždy Marek',
                    ],
                ];

                foreach ($rules as $r) {
                    $existing = $db->fetch('SELECT id FROM forwarding_rules WHERE name = ? LIMIT 1', [$r['name']]);
                    $data = [
                        'name' => $r['name'],
                        'rule_type' => 'event',
                        'priority' => (int) $r['priority'],
                        'is_active' => 1,
                        'valid_from' => null,
                        'valid_until' => null,
                        'days_of_week' => $r['days_of_week'],
                        'start_time' => $r['start_time'],
                        'end_time' => $r['end_time'],
                        'forward_to' => $marekNumber,
                        'target_type' => 'employee',
                        'target_employee_id' => $marekId,
                        'target_group_id' => null,
                        'holiday_id' => null,
                        'requires_employee' => 1,
                        'description' => $r['description'],
                        'created_by' => null,
                        'metadata' => null,
                    ];

                    if ($existing) {
                        $db->update('forwarding_rules', $data + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [(int) $existing['id']]);
                    } else {
                        $db->insert('forwarding_rules', $data + ['created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                    }
                }

                // Reset state (optional but helpful)
                $db->update('system_state', ['state_value' => date('Y-m-d H:i:s'), 'updated_by' => 'setup:apply-v2'], 'state_key = ?', ['last_check_at']);

                $db->commit();
            } catch (\Throwable $e) {
                $db->rollback();
                throw $e;
            }

            echo "Applied schedule v2.\n";
            echo "Mon-Fri 08:00-16:00 -> 366.\n";
            echo "Mon-Fri 16:00-22:00 -> weekly rotation (active oncall_rotations).\n";
            echo "Mon-Fri 22:00-08:00 + weekend -> Marek Piaček ({$marekNumber}).\n";
            break;

        case 'setup:bootstrap':
            if (!isset($args['force']) || $args['force'] !== true) {
                echo "Error: This command deletes existing data. Re-run with --force\n";
                exit(1);
            }

            $db = Connection::getInstance();
            $pdo = $db->getPdo();

            // Ensure schema exists
            $migrationsPath = dirname(__DIR__) . '/database/migrations';
            $db->migrate($migrationsPath);

            $today = new \DateTimeImmutable('now');
            $rotationStart = $today->modify('monday this week')->format('Y-m-d');

            $db->beginTransaction();
            try {
                // Wipe config-like tables (keep options/api_keys etc.)
                $pdo->exec('DELETE FROM override_rules');
                $pdo->exec('DELETE FROM forwarding_rules');
                $pdo->exec('DELETE FROM oncall_rotations');
                $pdo->exec('DELETE FROM rotation_groups');
                $pdo->exec('DELETE FROM holidays');
                $pdo->exec('DELETE FROM working_hours');
                $pdo->exec('DELETE FROM employees');

                // Reset state (leave rows present)
                $resetState = [
                    'current_forward_to' => '',
                    'last_successful_forward_to' => '',
                    'last_device_response' => '',
                    'last_successful_change_at' => null,
                    'device_status' => 'unknown',
                    'consecutive_failures' => '0',
                    'last_check_at' => null,
                    'scheduler_status' => 'stopped',
                ];

                foreach ($resetState as $key => $value) {
                    $existing = $db->fetch('SELECT id FROM system_state WHERE state_key = ?', [$key]);
                    if ($existing) {
                        $db->update(
                            'system_state',
                            ['state_value' => $value, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => 'setup:bootstrap'],
                            'id = ?',
                            [(int) $existing['id']]
                        );
                    } else {
                        $db->insert('system_state', [
                            'state_key' => $key,
                            'state_value' => $value,
                            'state_type' => is_int($value) ? 'integer' : 'string',
                            'updated_at' => date('Y-m-d H:i:s'),
                            'updated_by' => 'setup:bootstrap',
                        ]);
                    }
                }

                // Working hours: Mon-Fri 08:00-16:00 => 366
                foreach ([1, 2, 3, 4, 5] as $day) {
                    $db->insert('working_hours', [
                        'day_of_week' => $day,
                        'is_working_day' => 1,
                        'start_time' => '08:00',
                        'end_time' => '16:00',
                        'forward_to_internal' => '366',
                        'forward_to_external' => null,
                        'effective_from' => null,
                        'effective_until' => null,
                        'is_active' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }

                // Weekend: non-working days
                foreach ([6, 0] as $day) {
                    $db->insert('working_hours', [
                        'day_of_week' => $day,
                        'is_working_day' => 0,
                        'start_time' => '00:00',
                        'end_time' => '00:00',
                        'forward_to_internal' => null,
                        'forward_to_external' => null,
                        'effective_from' => null,
                        'effective_until' => null,
                        'is_active' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }

                // Rotation group + employees (placeholders for extensions)
                $groupId = $db->insert('rotation_groups', [
                    'name' => 'After-hours on-call',
                    'description' => 'Weekly rotation outside working hours',
                    'rotation_type' => 'weekly',
                    'rotation_order' => null,
                    'current_index' => 0,
                    'rotation_start_date' => $rotationStart,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                $employees = [
                    ['name' => 'Milan Toráč', 'ext' => '101', 'priority' => 10],
                    ['name' => 'Peter Linha', 'ext' => '102', 'priority' => 20],
                    ['name' => 'Peter Petrus', 'ext' => '103', 'priority' => 30],
                ];

                $employeeIds = [];
                foreach ($employees as $emp) {
                    $employeeIds[] = $db->insert('employees', [
                        'name' => $emp['name'],
                        'email' => null,
                        'phone_internal' => null,
                        // We intentionally set only phone_mobile so on-call doesn't apply during working hours.
                        // You can later change to real mobile numbers in the web UI.
                        'phone_mobile' => $emp['ext'],
                        'phone_primary' => null,
                        'is_active' => 1,
                        'is_oncall' => 0,
                        'priority' => $emp['priority'],
                        'rotation_group_id' => $groupId,
                        'metadata' => null,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }

                $db->update('rotation_groups', [
                    'rotation_order' => json_encode($employeeIds),
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$groupId]);

                // On-call rotation: weekly; only applies after-hours based on employee phone_mobile.
                $db->insert('oncall_rotations', [
                    'name' => 'After-hours rotation',
                    'group_id' => $groupId,
                    'rotation_pattern' => 'weekly',
                    'rotation_start_date' => $rotationStart,
                    'rotation_direction' => 'forward',
                    'is_24x7' => 1,
                    'default_start_time' => null,
                    'default_end_time' => null,
                    'during_hours_forward_to' => null,
                    'after_hours_forward_to' => null,
                    'use_employee_mobile' => 0,
                    'fallback_rule_id' => null,
                    'is_active' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                $db->commit();
            } catch (\Throwable $e) {
                $db->rollback();
                throw $e;
            }

            echo "Bootstrap complete.\n";
            echo "Working hours (Mon-Fri 08:00-16:00) forward_to=366.\n";
            echo "After-hours weekly rotation start={$rotationStart}: Milan Toráč -> Peter Linha -> Peter Petrus.\n";
            echo "Note: employee on-call numbers are placeholders (101/102/103) stored in phone_mobile.\n";
            break;

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
            $intervalRaw = (string) ($args['interval'] ?? '1 hour');

            // Parse intervals like "15 minutes", "2 hours", "1 day"
            $intervalRaw = trim($intervalRaw);
            $interval = null;
            if (preg_match('/^(\d+)\s*(minute|minutes|min|hour|hours|day|days)$/i', $intervalRaw, $m)) {
                $n = (int) $m[1];
                $unit = strtolower($m[2]);
                $interval = match (true) {
                    $unit === 'min' || $unit === 'minute' || $unit === 'minutes' => new \DateInterval('PT' . max(1, $n) . 'M'),
                    $unit === 'hour' || $unit === 'hours' => new \DateInterval('PT' . max(1, $n) . 'H'),
                    $unit === 'day' || $unit === 'days' => new \DateInterval('P' . max(1, $n) . 'D'),
                    default => null,
                };
            }

            if ($interval === null) {
                echo "Error: invalid --interval. Use like \"1 hour\", \"15 minutes\", \"1 day\".\n";
                exit(1);
            }
            
            echo "Previewing forwarding from {$start} to {$end}...\n";
            $results = $services['decision']->previewRange(
                new \DateTimeImmutable($start . ' 00:00:00'),
                new \DateTimeImmutable($end . ' 23:59:59'),
                $interval
            );
            
            foreach ($results as $row) {
                echo sprintf(
                    "%s -> %s (%s)\n",
                    $row['datetime'],
                    $row['decision']->forwardTo ?? '(none)',
                    $row['decision']->reason
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
