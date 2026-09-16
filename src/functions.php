<?php

namespace Gaambo\DeployerUtils;

use Gaambo\DeployerUtils\Runtime\DdevRuntimeHost;
use Gaambo\DeployerUtils\Runtime\RuntimeHost;
use Closure;

/**
 * Create a lazy runtime host factory for Deployer configuration.
 *
 * @param class-string<RuntimeHost>|string $runtime
 * @param array<string,mixed> $options
 * @return Closure():RuntimeHost
 */
function runtime(string $runtime, array $options = []): Closure
{
    return static function () use ($runtime, $options): RuntimeHost {
        $runtimeClass = match ($runtime) {
            'ddev' => DdevRuntimeHost::class,
            default => $runtime,
        };

        if (!is_a($runtimeClass, RuntimeHost::class, true)) {
            throw new \InvalidArgumentException("Unknown runtime \"$runtime\".");
        }

        $runtimeHost = new $runtimeClass();
        foreach ($options as $key => $value) {
            $runtimeHost->set($key, $value);
        }

        return $runtimeHost;
    };
}
