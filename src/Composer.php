<?php

namespace Gaambo\DeployerUtils;

use function Deployer\run;

class Composer
{
    private const INSTALLER_DOWNLOAD = 'https://getcomposer.org/installer';

    public static function runDefault(string $path): string
    {
        return self::runCommand($path, '{{composer_action}}', '{{composer_options}}');
    }

    public static function runCommand(string $path, string $command, string $arguments = ''): string
    {
        $verbosityArgument = Utils::getVerbosityArgument();

        $runCommand = "cd $path && {{bin/composer}} $command";
        if ($arguments !== '') {
            $runCommand .= " $arguments";
        }
        if ($verbosityArgument !== '') {
            $runCommand .= " $verbosityArgument";
        }

        return run($runCommand);
    }

    public static function runScript(string $path, string $script, string $arguments = ''): string
    {
        return self::runCommand($path, "run-script $script", $arguments);
    }

    public static function install(
        string $installPath,
        string $binaryName = 'composer.phar',
        bool $sudo = false
    ): string {
        $sudoPrefix = $sudo ? 'sudo ' : '';

        run($sudoPrefix . "mkdir -p $installPath");
        run($sudoPrefix . "cd $installPath && curl -sS " . self::INSTALLER_DOWNLOAD . " | {{bin/php}}");

        if ($binaryName !== 'composer.phar') {
            run($sudoPrefix . "mv $installPath/composer.phar $installPath/$binaryName");
        }

        return "$installPath/$binaryName";
    }
}
