<?php

namespace Gaambo\DeployerUtils;

use Gaambo\DeployerUtils\Runtime\RuntimeHost;

/**
 * Create an unregistered runtime host that inherits localhost configuration.
 *
 * @template T of RuntimeHost
 * @param class-string<T> $runtimeHost
 * @return T
 */
function runtime(string $runtimeHost): RuntimeHost
{
    $runtime = new $runtimeHost();
    $runtime->bind(Localhost::get());

    return $runtime;
}
