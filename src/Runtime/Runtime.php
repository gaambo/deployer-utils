<?php

namespace Gaambo\DeployerUtils\Runtime;

use Deployer\Host\Host;
use Deployer\Host\Localhost as DeployerLocalhost;
use Deployer\Task\Context;
use Gaambo\DeployerUtils\Localhost;

use function Deployer\output;
use function Deployer\run;

/**
 * Adds a command environment to a Deployer host without taking over its transport.
 *
 * Runtime commands execute through a derived context host. That host inherits the
 * source host's config and has the same local or SSH transport type, while runtime
 * config such as the DDEV shell stays isolated from the source host.
 */
abstract class Runtime implements \JsonSerializable
{
    /**
     * Config values applied to the execution host before user options.
     *
     * @var array<string,mixed>
     */
    protected array $defaults = [];

    private ?Host $sourceHost = null;
    private ?Host $executionHost = null;

    /**
     * Creates an unbound runtime with user config overrides.
     *
     * @param array<string,mixed> $options
     */
    final public function __construct(private readonly array $options = [])
    {
    }

    /**
     * Resets host-specific state when a configured runtime template is cloned.
     */
    final public function __clone()
    {
        $this->sourceHost = null;
        $this->executionHost = null;
    }

    /**
     * Creates a runtime from its short name or class name.
     *
     * @param class-string<Runtime>|string $type
     * @param array<string,mixed> $options
     */
    final public static function make(string $type, array $options = []): self
    {
        $runtimeClass = match ($type) {
            'ddev' => DdevRuntime::class,
            default => $type,
        };
        if (!is_a($runtimeClass, self::class, true)) {
            throw new \InvalidArgumentException("Unknown runtime \"$type\".");
        }
        if (!(new \ReflectionClass($runtimeClass))->isInstantiable()) {
            throw new \InvalidArgumentException("Runtime \"$type\" must be instantiable.");
        }

        return new $runtimeClass($options);
    }

    /**
     * Serializes the runtime back to the definition Deployer can pass to workers.
     *
     * @return array{type:class-string<Runtime>,options:array<string,mixed>}
     */
    final public function jsonSerialize(): array
    {
        return [
            'type' => static::class,
            'options' => $this->options,
        ];
    }

    /**
     * Binds this runtime to the real configured host whose commands it decorates.
     *
     * Binding creates a separate execution host. The source host keeps its host
     * paths and config unchanged; the execution host receives runtime overrides.
     */
    final public function bind(Host $sourceHost): void
    {
        if ($this->sourceHost === $sourceHost) {
            return;
        }
        if ($this->sourceHost !== null) {
            throw new \LogicException('A runtime cannot be bound to more than one host.');
        }

        $this->sourceHost = $sourceHost;
        $this->executionHost = $this->createExecutionHost($sourceHost);
        $this->executionHost->config()->bind($sourceHost->config());
        $this->executionHost->set('working_path', $this->hostDeployPath());
        foreach (array_replace($this->defaults, $this->options) as $key => $value) {
            $this->executionHost->set($key, $value);
        }
        $this->executionHost->set('runtime', $this);
        $this->configure($this->executionHost);
    }

    /**
     * Runs a command through the current host's runtime, or natively when none is configured.
     *
     * @param array{
     *     cwd?:string|null,
     *     timeout?:int|null,
     *     idleTimeout?:int|null,
     *     env?:array<string,string>|null,
     *     secrets?:array<string,string>|null,
     *     nothrow?:bool,
     *     forceOutput?:bool
     * }|null $options
     */
    final public static function run(string $command, ?array $options = null): string
    {
        $contextHost = self::currentContextHost();
        $runtime = self::runtimeForContextHost($contextHost);
        if (isset($options['cwd'])) {
            $parseHost = $runtime?->sourceHost() ?? $contextHost;
            $options['cwd'] = $parseHost->config()->parse($options['cwd']);
        }

        if ($runtime === null) {
            return self::isConfiguredLocalhost($contextHost)
                ? Localhost::runNative($command, $options)
                : self::runOnCurrentHost($command, $options);
        }

        return $runtime->execute($command, $options);
    }

    /**
     * Maps a source-host path into the current runtime.
     *
     * Without a configured runtime, the source path is returned unchanged.
     */
    final public static function path(string $hostPath): string
    {
        $runtime = self::runtimeForContextHost(self::currentContextHost());
        if ($runtime === null) {
            return $hostPath;
        }

        $runtimePath = $runtime->mapPath($hostPath);
        $runtime->diagnostic("path: $hostPath -> $runtimePath");

        return $runtimePath;
    }

    /**
     * Reads config from the execution host when a runtime exists.
     *
     * Without a runtime, config is read from the current source host.
     */
    final public static function getConfig(string $key): mixed
    {
        $contextHost = self::currentContextHost();
        $runtime = self::runtimeForContextHost($contextHost);
        if ($runtime === null) {
            return self::isConfiguredLocalhost($contextHost)
                ? Localhost::getConfig($key)
                : $contextHost->get($key);
        }

        return $runtime->enter(fn() => $runtime->executionHost()->get($key));
    }

    /**
     * Runs a callback with runtime config and command execution active.
     *
     * @template T
     * @param callable():T $callback
     * @return T
     */
    final public static function within(callable $callback): mixed
    {
        $runtime = self::runtimeForContextHost(self::currentContextHost());
        if ($runtime === null) {
            return $callback();
        }

        return $runtime->enter($callback);
    }

    /**
     * Returns whether Deployer's current context host is an execution host.
     */
    final public static function isActive(): bool
    {
        if (!Context::has()) {
            return false;
        }

        $contextHost = Context::get()->getHost();
        $runtime = $contextHost->get('runtime', null);

        return $runtime instanceof self && $runtime->executionHost === $contextHost;
    }

    /**
     * Returns the runtime name used in diagnostics.
     */
    abstract protected function name(): string;

    /**
     * Applies runtime-specific config to the internal execution host.
     */
    abstract protected function configure(Host $executionHost): void;

    /**
     * Maps a source-host path into this runtime.
     */
    abstract protected function mapPath(string $hostPath): string;

    /**
     * Converts public run options into options for the derived execution host.
     *
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    protected function prepareRunOptions(array $options): array
    {
        return $options;
    }

    /**
     * Returns the internal host used as Deployer's runtime context.
     */
    final protected function executionHost(): Host
    {
        return $this->executionHost
            ?? throw new \LogicException('Runtime must be bound before use.');
    }

    /**
     * Returns the real configured Deployer host that owns this runtime.
     */
    final protected function sourceHost(): Host
    {
        return $this->sourceHost
            ?? throw new \LogicException('Runtime must be bound before use.');
    }

    /**
     * Returns the project root as seen by the source host.
     */
    final protected function hostDeployPath(): string
    {
        $deployPath = $this->sourceHost?->get('deploy_path');
        if (!is_string($deployPath)) {
            throw new \RuntimeException('A runtime requires host "deploy_path" to be a string.');
        }

        return $deployPath;
    }

    final protected function diagnostic(string $message): void
    {
        if (output()->isVerbose()) {
            output()->writeln("[runtime:{$this->name()}] $message");
        }
    }

    /**
     * Prepares the runtime and runs a command through its execution host.
     *
     * @param array<string,mixed>|null $options
     */
    private function execute(string $command, ?array $options): string
    {
        $runOptions = $this->prepareRunOptions($options ?? []);

        return $this->enter(fn() => self::runOnCurrentHost($command, $runOptions));
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    private function enter(callable $callback): mixed
    {
        Context::push(new Context($this->executionHost()));
        try {
            return $callback();
        } finally {
            Context::pop();
        }
    }

    /**
     * Builds a context host instead of mutating the source host's shell and config.
     * Deployer selects ProcessRunner or SshClient from the host's concrete type, so
     * the derived host must preserve whether the source is a Localhost.
     */
    private function createExecutionHost(Host $sourceHost): Host
    {
        $sourceAlias = $sourceHost->getAlias() ?? $sourceHost->getHostname() ?? 'host';
        $executionAlias = "$sourceAlias:{$this->name()}";
        if ($sourceHost instanceof DeployerLocalhost) {
            return new DeployerLocalhost($executionAlias);
        }

        return (new Host($executionAlias))->setHostname($sourceHost->getHostname() ?? $sourceAlias);
    }

    /**
     * Returns the runtime for either a real source host or an active execution host.
     */
    private static function runtimeForContextHost(Host $contextHost): ?self
    {
        $configuration = $contextHost->get('runtime', null);
        if ($configuration === null) {
            return null;
        }

        if ($configuration instanceof self) {
            if ($configuration->executionHost === $contextHost || $configuration->sourceHost === $contextHost) {
                return $configuration;
            }

            // A global runtime object is a template shared by host configs.
            // Clone it so binding one source host cannot affect another.
            $runtime = clone $configuration;
            $contextHost->set('runtime', $runtime);
            $runtime->bind($contextHost);

            return $runtime;
        }

        if (!is_array($configuration)) {
            throw new \InvalidArgumentException('Runtime configuration must be a Runtime or runtime definition.');
        }

        $type = $configuration['type'] ?? null;
        if (!is_string($type)) {
            throw new \InvalidArgumentException('Runtime configuration requires a string "type".');
        }
        $options = $configuration['options'] ?? [];
        if (!is_array($options)) {
            throw new \InvalidArgumentException('Runtime configuration "options" must be an array.');
        }

        $runtime = self::make($type, $options);
        $contextHost->set('runtime', $runtime);
        $runtime->bind($contextHost);

        return $runtime;
    }

    /**
     * Returns Deployer's active context host.
     *
     * Outside a task context, the source host is resolved through the localhost helper.
     */
    private static function currentContextHost(): Host
    {
        return Context::has() ? Context::get()->getHost() : Localhost::get();
    }

    /**
     * Returns whether a context host is the configured localhost helper target.
     */
    private static function isConfiguredLocalhost(Host $contextHost): bool
    {
        return $contextHost === Localhost::get();
    }

    /**
     * Runs a command through Deployer's current context host.
     *
     * @param array<string,mixed>|null $options
     */
    private static function runOnCurrentHost(string $command, ?array $options): string
    {
        $runOptions = $options ?? [];

        return run(
            $command,
            cwd: $runOptions['cwd'] ?? null,
            env: $runOptions['env'] ?? null,
            secrets: $runOptions['secrets'] ?? null,
            nothrow: $runOptions['nothrow'] ?? false,
            forceOutput: $runOptions['forceOutput'] ?? false,
            timeout: $runOptions['timeout'] ?? null,
            idleTimeout: $runOptions['idleTimeout'] ?? null,
        );
    }
}
