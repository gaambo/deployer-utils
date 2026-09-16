<?php

namespace Gaambo\DeployerUtils;

use Gaambo\DeployerUtils\Runtime\Runtime;

/**
 * Create a serializable runtime for Deployer configuration.
 *
 * @param class-string<Runtime>|string $runtime
 * @param array<string,mixed> $options
 */
function runtime(string $runtime, array $options = []): Runtime
{
    return Runtime::make($runtime, $options);
}
