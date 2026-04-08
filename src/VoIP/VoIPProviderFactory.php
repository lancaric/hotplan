<?php

declare(strict_types=1);

namespace HotPlan\VoIP;

/**
 * Creates VoIP provider instances based on type.
 */
class VoIPProviderFactory
{
    /**
     * Registered provider classes
     */
    private static array $providers = [
        'sipura' => \HotPlan\VoIP\Sipura\SipuraProvider::class,
        'cisco' => \HotPlan\VoIP\Cisco\CiscoProvider::class,
        'grandstream' => \HotPlan\VoIP\Grandstream\GrandstreamProvider::class,
        'generic' => \HotPlan\VoIP\Generic\GenericProvider::class,
    ];

    public static function create(string $type, array $config = []): VoIPProviderInterface
    {
        $type = strtolower($type);

        if (!isset(self::$providers[$type])) {
            throw new \InvalidArgumentException("Unknown VoIP provider type: {$type}");
        }

        $className = self::$providers[$type];

        if (!class_exists($className)) {
            throw new \RuntimeException("VoIP provider class not found for type '{$type}': {$className}");
        }

        if (!is_a($className, VoIPProviderInterface::class, true)) {
            throw new \RuntimeException("VoIP provider class does not implement VoIPProviderInterface: {$className}");
        }

        return new $className($config);
    }

    public static function register(string $type, string $className): void
    {
        if (!is_a($className, VoIPProviderInterface::class, true)) {
            throw new \InvalidArgumentException("Class must implement VoIPProviderInterface");
        }

        self::$providers[strtolower($type)] = $className;
    }

    public static function getRegisteredTypes(): array
    {
        return array_keys(self::$providers);
    }

    public static function isRegistered(string $type): bool
    {
        return isset(self::$providers[strtolower($type)]);
    }
}

