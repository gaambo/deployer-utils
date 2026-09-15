<?php

namespace Gaambo\DeployerUtils\Runtime;

use Deployer\Task\Context;
use Gaambo\DeployerUtils\Localhost;

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
        if (isset($options['cwd'])) {
            $options['cwd'] = Localhost::parse($options['cwd']);
        }

        $runtime = self::configured();
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
        $runtime = Localhost::getConfig('runtime', null);
        if ($runtime === null) {
            return null;
        }
        if (!$runtime instanceof RuntimeHost) {
            throw new \InvalidArgumentException(
                'The localhost "runtime" configuration must be an instance of RuntimeHost.'
            );
        }

        return $runtime;
    }
}
