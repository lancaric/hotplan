<?php

declare(strict_types=1);

namespace HotPlan\VoIP\Generic;

use HotPlan\VoIP\AbstractVoIPProvider;
use HotPlan\VoIP\DeviceResponse;

/**
 * Generic HTTP provider.
 *
 * Uses the same querystring approach as the Sipura provider: `forward_param` is required.
 */
class GenericProvider extends AbstractVoIPProvider
{
    public function getType(): string
    {
        return 'generic';
    }

    public function getName(): string
    {
        return 'Generic HTTP';
    }

    public function getCapabilities(): array
    {
        return [
            'set_forward' => true,
            'clear_forward' => true,
            'read_forward' => false,
        ];
    }

    public function setForwardTo(string $number): DeviceResponse
    {
        $forwardParam = (string)($this->config['forward_param'] ?? '');
        if ($forwardParam === '') {
            return DeviceResponse::error('Missing config: voip.forward_param');
        }

        $prefix = (string)($this->config['forward_prefix'] ?? '');
        $value = $prefix . $number;

        $url = $this->buildUrl(null, [$forwardParam => $value]);
        return $this->request('GET', $url);
    }

    public function getCurrentForwardTo(): DeviceResponse
    {
        return DeviceResponse::error('Not implemented for generic provider', 501);
    }

    public function clearForward(): DeviceResponse
    {
        $forwardParam = (string)($this->config['forward_param'] ?? '');
        if ($forwardParam === '') {
            return DeviceResponse::error('Missing config: voip.forward_param');
        }

        $url = $this->buildUrl(null, [$forwardParam => '']);
        return $this->request('GET', $url);
    }

    public function executeCommand(string $command, array $parameters = []): DeviceResponse
    {
        $url = $this->buildUrl(null, array_merge(['cmd' => $command], $parameters));
        return $this->request('GET', $url);
    }
}

