<?php

declare(strict_types=1);

namespace HotPlan\VoIP;

/**
 * VoIP Provider Interface
 *
 * Defines the contract for VoIP device communication providers.
 */
interface VoIPProviderInterface
{
    public function getType(): string;

    public function getName(): string;

    public function isReachable(): bool;

    public function getDeviceStatus(): array;

    public function setForwardTo(string $number): DeviceResponse;

    public function getCurrentForwardTo(): DeviceResponse;

    public function clearForward(): DeviceResponse;

    public function testConnection(): DeviceResponse;

    public function getCapabilities(): array;

    public function executeCommand(string $command, array $parameters = []): DeviceResponse;

    public function setConfig(array $config): void;

    public function getConfig(): array;
}

