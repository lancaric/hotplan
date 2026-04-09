<?php

declare(strict_types=1);

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
use HotPlan\Database\Connection;
use HotPlan\Decision\DecisionEngine;
use HotPlan\Logging\ForwardLogger;
use HotPlan\Repositories\EmployeeRepository;
use HotPlan\Repositories\HolidayRepository;
use HotPlan\Repositories\OnCallRepository;
use HotPlan\Repositories\OverrideRepository;
use HotPlan\Repositories\RuleRepository;
use HotPlan\Repositories\WorkingHoursRepository;
use HotPlan\Services\ForwardingService;
use HotPlan\VoIP\VoIPProviderFactory;
use HotPlan\Web\WebApp;

// PHP built-in server: allow static files through when they exist.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $fullPath = __DIR__ . $path;
    if (is_string($path) && $path !== '/' && file_exists($fullPath) && !is_dir($fullPath)) {
        return false;
    }
}

try {
    $config = ConfigLoader::getInstance();
    // Web server can keep PHP process alive; always reload so config edits apply immediately.
    $config->reload();
    $tz = (string) ($config->get('app.timezone', 'UTC') ?? 'UTC');
    if ($tz !== '') {
        date_default_timezone_set($tz);
    }
    $db = Connection::getInstance();

    $ruleRepo = new RuleRepository($db);
    $holidayRepo = new HolidayRepository($db);
    $workingHoursRepo = new WorkingHoursRepository($db);
    $overrideRepo = new OverrideRepository($db);
    $onCallRepo = new OnCallRepository($db);
    $employeeRepo = new EmployeeRepository($db);

    $decisionEngine = new DecisionEngine(
        $ruleRepo,
        $holidayRepo,
        $workingHoursRepo,
        $overrideRepo,
        $onCallRepo,
        $employeeRepo,
        $config
    );

    $logger = new ForwardLogger($config);

    $voipConfig = $config->getVoipConfig();
    $credentials = $config->getVoipCredentials();
    $voipConfig['username'] = $credentials['username'];
    $voipConfig['password'] = $credentials['password'];
    $voipProvider = VoIPProviderFactory::create($voipConfig['provider'], $voipConfig);

    $forwardingService = new ForwardingService($decisionEngine, $voipProvider, $config, null, $logger);

    $app = new WebApp($config, $db, $logger, $decisionEngine, $forwardingService);
    $app->handle();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "HotPlan Web error: " . $e->getMessage() . "\n";
}
