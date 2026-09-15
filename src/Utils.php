<?php

namespace Gaambo\DeployerUtils;

use function Deployer\output;
use function Deployer\quote as deployerQuote;

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

    /**
     * @param array<mixed> $array
     * @return array<string>
     */
    public static function parseStringArray(array $array): array
    {
        return array_values(array_filter(array_map(
            fn($value) => is_string($value) ? $value : null,
            $array
        )));
    }

    public static function parseStringOrNull(mixed $string): ?string
    {
        return is_string($string) ? $string : null;
    }

    public static function quote(string $arg): string
    {
        return deployerQuote($arg);
    }
}
