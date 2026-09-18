<?php

namespace Gaambo\DeployerUtils;

use function Deployer\output;

class Utils
{
    public static function getVerbosityArgument(): string
    {
        $outputInterface = output();
        $verbosityArgument = '';

        if ($outputInterface->isVerbose()) {
            $verbosityArgument = '-v';
        }
        if ($outputInterface->isVeryVerbose()) {
            $verbosityArgument = '-vv';
        }
        if ($outputInterface->isDebug()) {
            $verbosityArgument = '-vvv';
        }

        return $verbosityArgument;
    }
}
