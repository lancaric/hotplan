<?php

declare(strict_types=1);

namespace HotPlan\VoIP\Grandstream;

use HotPlan\VoIP\AbstractVoIPProvider;
use HotPlan\VoIP\DeviceResponse;

class GrandstreamProvider extends AbstractVoIPProvider
{
    public function getType(): string
    {
        return 'grandstream';
    }

    public function getName(): string
    {
        return 'Grandstream (stub)';
    }

    public function getCapabilities(): array
    {
        return ['set_forward' => false, 'clear_forward' => false, 'read_forward' => false];
    }

    public function setForwardTo(string $number): DeviceResponse
    {
        return DeviceResponse::error('Grandstream provider not implemented', 501);
    }

    public function getCurrentForwardTo(): DeviceResponse
    {
        return DeviceResponse::error('Grandstream provider not implemented', 501);
    }

    public function clearForward(): DeviceResponse
    {
        return DeviceResponse::error('Grandstream provider not implemented', 501);
    }

    public function executeCommand(string $command, array $parameters = []): DeviceResponse
    {
        return DeviceResponse::error('Grandstream provider not implemented', 501);
    }
}

