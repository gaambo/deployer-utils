<?php

namespace Gaambo\DeployerUtils;

use Gaambo\DeployerUtils\Runtime\Runtime;

/**
 * Create a serializable runtime definition for Deployer configuration.
 *
 * The plain array keeps host config dumpable by `dep config` and
 * transferable to Deployer worker processes.
 *
 * @param class-string<Runtime>|string $runtime
 * @param array<string,mixed> $options
 * @return array{type:class-string<Runtime>,options:array<string,mixed>}
 */
function runtime(string $runtime, array $options = []): array
{
    return Runtime::define($runtime, $options);
}
