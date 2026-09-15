<?php

namespace Gaambo\DeployerUtils;

use function Deployer\run;

class NPM
{
    public static function runScript(string $path, string $script, string $arguments = ''): string
    {
        return self::runCommand($path, "run-script $script", $arguments);
    }

    public static function runCommand(string $path, string $action, string $arguments = ''): string
    {
        $verbosityArgument = str_replace('v', 'd', Utils::getVerbosityArgument());

        $command = "cd $path && {{bin/npm}} $action";
        if ($arguments !== '') {
            $command .= " $arguments";
        }
        if ($verbosityArgument !== '') {
            $command .= " $verbosityArgument";
        }

        return run($command);
    }

    public static function runInstall(string $path, string $arguments = ''): string
    {
        return self::runCommand($path, 'install', $arguments);
    }
}
