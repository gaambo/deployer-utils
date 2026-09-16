<?php

namespace Gaambo\DeployerUtils;

use Deployer\Deployer;
use Deployer\Host\Host;
use Deployer\Task\Context;
use Gaambo\DeployerUtils\Runtime\Runtime;

use function Deployer\on;
use function Deployer\runLocally;

class Localhost
{
    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public static function within(callable $callback): mixed
    {
        Context::push(new Context(self::get()));
        try {
            return $callback();
        } finally {
            Context::pop();
        }
    }

    public static function getConfig(string $key, mixed $default = null): mixed
    {
        Context::push(new Context(self::get()));
        try {
            return func_num_args() >= 2
                ? self::get()->get($key, $default)
                : self::get()->get($key);
        } finally {
            Context::pop();
        }
    }

    public static function parse(string $value): string
    {
        Context::push(new Context(self::get()));
        try {
            return self::get()->config()->parse($value);
        } finally {
            Context::pop();
        }
    }

    public static function get(): Host
    {
        return Deployer::get()->hosts->get('localhost');
    }

    /**
     * Runs a command natively, unless called from an active runtime execution context.
     *
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
        // Runtime::within() replaces Deployer's context host with an internal
        // execution host. Keep nested localhost calls in that active runtime.
        if (Runtime::isActive()) {
            return Runtime::run($command, $options);
        }

        return self::runNative($command, $options);
    }

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
    public static function runNative(string $command, ?array $options = null): string
    {
        $result = null;
        on(self::get(), function () use ($command, $options, &$result) {
            $runOptions = $options ?? [];
            $result = runLocally(
                $command,
                cwd: $runOptions['cwd'] ?? null,
                timeout: $runOptions['timeout'] ?? null,
                idleTimeout: $runOptions['idleTimeout'] ?? null,
                secrets: $runOptions['secrets'] ?? null,
                env: $runOptions['env'] ?? null,
                forceOutput: $runOptions['forceOutput'] ?? false,
                nothrow: $runOptions['nothrow'] ?? false,
                shell: $runOptions['shell'] ?? null,
            );
        });

        return $result;
    }
}
