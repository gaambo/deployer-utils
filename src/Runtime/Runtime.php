<?php

namespace Gaambo\DeployerUtils\Runtime;

use Deployer\Host\Host;
use Deployer\Task\Context;
use Gaambo\DeployerUtils\Localhost;

use function Gaambo\DeployerUtils\runtime;

class Runtime
{
    /**
     * @param array{
     *     cwd?:string|null,
     *     timeout?:int|null,
     *     idleTimeout?:int|null,
     *     env?:array<string,string>|null,
     *     secrets?:array<string,string>|null,
     *     nothrow?:bool,
     *     forceOutput?:bool,
     *     shell?:string|null
     * }|null $options
     */
    public static function run(string $command, ?array $options = null): string
    {
        $runtime = self::configured();
        if (isset($options['cwd'])) {
            $options['cwd'] = self::configuredHost()->config()->parse($options['cwd']);
        }

        if ($runtime === null) {
            return Localhost::runNative($command, $options);
        }

        return $runtime->run($command, $options);
    }

    public static function path(string $hostPath): string
    {
        return self::configured()?->path($hostPath) ?? $hostPath;
    }

    public static function getConfig(string $key): mixed
    {
        $runtime = self::configured();
        if ($runtime === null) {
            return Localhost::getConfig($key);
        }

        return $runtime->within(fn() => $runtime->get($key));
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public static function within(callable $callback): mixed
    {
        $runtime = self::configured();
        if ($runtime === null) {
            return $callback();
        }

        return $runtime->within($callback);
    }

    public static function isActive(): bool
    {
        return Context::has() && Context::get()->getHost() instanceof RuntimeHost;
    }

    private static function configured(): ?RuntimeHost
    {
        $host = self::configuredHost();
        if ($host instanceof RuntimeHost) {
            return $host;
        }

        $configuration = $host->get('runtime', null);
        if ($configuration === null) {
            return null;
        }

        if (is_array($configuration)) {
            $type = $configuration['type'] ?? null;
            if (!is_string($type)) {
                throw new \InvalidArgumentException('Runtime configuration requires a string "type".');
            }
            $options = $configuration['options'] ?? [];
            if (!is_array($options)) {
                throw new \InvalidArgumentException('Runtime configuration "options" must be an array.');
            }
            $configuration = runtime($type, $options)();
        }

        if (!$configuration instanceof RuntimeHost) {
            throw new \InvalidArgumentException(
                'Runtime configuration must be a RuntimeHost or runtime definition.'
            );
        }

        $configuration->bind($host);

        return $configuration;
    }

    private static function configuredHost(): Host
    {
        return Context::has() ? Context::get()->getHost() : Localhost::get();
    }
}
