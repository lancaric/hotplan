<?php

declare(strict_types=1);

namespace HotPlan\Tests;

use HotPlan\Config\ConfigLoader;
use HotPlan\Logging\ForwardLogger;
use PHPUnit\Framework\TestCase;

class ForwardLoggerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/hotplan-forward-logger-' . uniqid('', true);
        mkdir($this->tempDir . '/logs', 0777, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->tempDir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tempDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());
                continue;
            }

            unlink($fileInfo->getPathname());
        }

        rmdir($this->tempDir);
    }

    public function testRecentLogsUseOnlyRequestedTailAndFilterByLevel(): void
    {
        $logger = $this->createLogger([
            'main' => $this->tempDir . '/logs/hotplan.log',
            'device' => $this->tempDir . '/logs/device.log',
            'audit' => $this->tempDir . '/logs/audit.log',
        ]);

        file_put_contents($this->tempDir . '/logs/hotplan.log', implode("\n", [
            '[2026-10-15 10:00:00] [info] first',
            '[2026-10-15 10:01:00] [warning] second',
            '[2026-10-15 10:02:00] [info] third',
            '[2026-10-15 10:03:00] [error] fourth',
        ]) . "\n");

        $entries = $logger->getRecentLogs(3, ForwardLogger::INFO);

        $this->assertSame([
            '[2026-10-15 10:02:00] [info] third',
        ], $entries);
    }

    public function testRecentDeviceLogsReturnLastRequestedLines(): void
    {
        $logger = $this->createLogger([
            'main' => $this->tempDir . '/logs/hotplan.log',
            'device' => $this->tempDir . '/logs/device.log',
            'audit' => $this->tempDir . '/logs/audit.log',
        ]);

        file_put_contents($this->tempDir . '/logs/device.log', implode("\n", [
            '[2026-10-15 10:00:00] [SUCCESS] one',
            '[2026-10-15 10:01:00] [SUCCESS] two',
            '[2026-10-15 10:02:00] [FAILED] three',
        ]) . "\n");

        $entries = $logger->getRecentDeviceLogs(2);

        $this->assertSame([
            '[2026-10-15 10:01:00] [SUCCESS] two',
            '[2026-10-15 10:02:00] [FAILED] three',
        ], $entries);
    }

    public function testStatsCountLinesWithoutLoadingWholeFiles(): void
    {
        $logger = $this->createLogger([
            'main' => $this->tempDir . '/logs/hotplan.log',
            'device' => $this->tempDir . '/logs/device.log',
            'audit' => $this->tempDir . '/logs/audit.log',
        ]);

        file_put_contents($this->tempDir . '/logs/hotplan.log', "main-1\nmain-2\n");
        file_put_contents($this->tempDir . '/logs/device.log', "device-1\ndevice-2\ndevice-3\n");
        file_put_contents($this->tempDir . '/logs/audit.log', "audit-1\naudit-2");

        $stats = $logger->getStats();

        $this->assertSame(2, $stats['main_log']['lines']);
        $this->assertSame(3, $stats['device_log']['lines']);
        $this->assertSame(2, $stats['audit_log']['lines']);
    }

    public function testRecentLogsHandleEmptyMissingAndNonTerminatedFiles(): void
    {
        $paths = $this->paths();
        $logger = $this->createLogger($paths);

        $this->assertSame([], $logger->getRecentLogs());

        file_put_contents($paths['main'], "first\nsecond");

        $this->assertSame(['second'], $logger->getRecentLogs(1));
        $this->assertSame([], $logger->getRecentLogs(0));
    }

    public function testRecentLogsReadAcrossChunkBoundaries(): void
    {
        $paths = $this->paths();
        $logger = $this->createLogger($paths);
        $longLine = str_repeat('x', 20000);

        file_put_contents($paths['main'], "old\n{$longLine}\nlast\n");

        $this->assertSame([$longLine, 'last'], $logger->getRecentLogs(2));
    }

    public function testLargeLogTailDoesNotRequireLoadingTheWholeFile(): void
    {
        $paths = $this->paths();
        $logger = $this->createLogger($paths);
        $handle = fopen($paths['main'], 'wb');

        $this->assertIsResource($handle);

        $line = str_repeat('x', 126) . "\n";
        $oneMegabyte = str_repeat($line, 8192);

        for ($i = 0; $i < 32; $i++) {
            fwrite($handle, $oneMegabyte);
        }

        fwrite($handle, "penultimate\nlast\n");
        fclose($handle);

        $this->assertSame(['penultimate', 'last'], $logger->getRecentLogs(2));
        $this->assertSame(262146, $logger->getStats()['main_log']['lines']);
    }

    public function testWritesRotateBeforeConfiguredSizeIsExceeded(): void
    {
        $paths = $this->paths();
        $logger = $this->createLogger($paths, [
            'logging.max_size' => '100',
            'logging.max_files' => 2,
        ]);

        $logger->info(str_repeat('a', 60));
        $logger->info(str_repeat('b', 60));

        $this->assertFileExists($paths['main'] . '.1');
        $this->assertStringContainsString(str_repeat('a', 60), (string) file_get_contents($paths['main'] . '.1'));
        $this->assertStringContainsString(str_repeat('b', 60), (string) file_get_contents($paths['main']));
    }

    public function testRotationKeepsOnlyConfiguredNumberOfArchives(): void
    {
        $paths = $this->paths();
        $logger = $this->createLogger($paths, [
            'logging.max_size' => '100',
            'logging.max_files' => 2,
        ]);

        foreach (['a', 'b', 'c', 'd'] as $letter) {
            $logger->info(str_repeat($letter, 60));
        }

        $this->assertFileExists($paths['main'] . '.1');
        $this->assertFileExists($paths['main'] . '.2');
        $this->assertFileDoesNotExist($paths['main'] . '.3');
    }

    public function testCreatesEveryConfiguredLogDirectory(): void
    {
        $paths = [
            'main' => $this->tempDir . '/main/hotplan.log',
            'device' => $this->tempDir . '/device/device.log',
            'audit' => $this->tempDir . '/audit/audit.log',
        ];

        $logger = $this->createLogger($paths);
        $logger->logDevice('forward', '100', true);
        $logger->logAudit('update', 'rule', 1);

        $this->assertFileExists($paths['device']);
        $this->assertFileExists($paths['audit']);
    }

    private function paths(): array
    {
        return [
            'main' => $this->tempDir . '/logs/hotplan.log',
            'device' => $this->tempDir . '/logs/device.log',
            'audit' => $this->tempDir . '/logs/audit.log',
        ];
    }

    private function createLogger(array $paths, array $overrides = []): ForwardLogger
    {
        $config = $this->createMock(ConfigLoader::class);
        $config->method('get')->willReturnCallback(
            static function (string $path, mixed $default = null) use ($paths, $overrides): mixed {
                if (array_key_exists($path, $overrides)) {
                    return $overrides[$path];
                }

                return match ($path) {
                    'behavior.enable_logging' => true,
                    'logging.file' => $paths['main'],
                    'logging.device_log' => $paths['device'],
                    'logging.audit_log' => $paths['audit'],
                    'app.log_level' => 'debug',
                    default => $default,
                };
            }
        );

        return new ForwardLogger($config);
    }
}
