<?php

namespace Gaambo\DeployerUtils;

use function Deployer\get;

/**
 * @phpstan-type RsyncConfig array{
 *      exclude?: array<int, string>,
 *      'exclude-file'?: string|false,
 *      include?: array<int, string>,
 *      'include-file'?: string|false,
 *      filter?: array<int, string>,
 *      'filter-file'?: string|false,
 *      'filter-perdir'?: string|false,
 *      options?: array<int, string>
 * }
 * @phpstan-type RsyncOptions list<string>
 */
class Rsync
{
    public const DEFAULT_CONFIG = [
        'exclude' => ['.git', 'deploy.php'],
        'exclude-file' => false,
        'include' => [],
        'include-file' => false,
        'filter' => [],
        'filter-file' => false,
        'filter-perdir' => false,
        'options' => ['delete-after'],
    ];

    /**
     * @param RsyncConfig $config
     * @return RsyncOptions
     */
    public static function buildOptionsArray(array $config = []): array
    {
        $defaultConfig = get('rsync');
        if (!$defaultConfig || !is_array($defaultConfig)) {
            $defaultConfig = [...self::DEFAULT_CONFIG];
        }

        $options = array_key_exists('options', $config) ? $config['options'] : ($defaultConfig['options'] ?? []);
        $options = Utils::parseStringArray((array) $options);
        $exclude = array_key_exists('exclude', $config) ? $config['exclude'] : ($defaultConfig['exclude'] ?? []);
        $exclude = Utils::parseStringArray((array) $exclude);
        $excludeFile = array_key_exists('exclude-file', $config)
            ? $config['exclude-file'] : ($defaultConfig['exclude-file'] ?? null);
        $excludeFile = Utils::parseStringOrNull($excludeFile);
        $include = array_key_exists('include', $config) ? $config['include'] : ($defaultConfig['include'] ?? []);
        $include = Utils::parseStringArray((array) $include);
        $includeFile = array_key_exists('include-file', $config)
            ? $config['include-file'] : ($defaultConfig['include-file'] ?? null);
        $includeFile = Utils::parseStringOrNull($includeFile);
        $filter = array_key_exists('filter', $config) ? $config['filter'] : ($defaultConfig['filter'] ?? []);
        $filter = Utils::parseStringArray((array) $filter);
        $filterFile = array_key_exists('filter-file', $config)
            ? $config['filter-file'] : ($defaultConfig['filter-file'] ?? null);
        $filterFile = Utils::parseStringOrNull($filterFile);
        $filterPerDir = array_key_exists('filter-perdir', $config)
            ? $config['filter-perdir'] : ($defaultConfig['filter-perdir'] ?? null);
        $filterPerDir = Utils::parseStringOrNull($filterPerDir);

        return array_filter([
            ...self::buildOptions($options),
            ...self::buildIncludes($include, $includeFile),
            ...self::buildExcludes($exclude, $excludeFile),
            ...self::buildFilter($filter, $filterFile, $filterPerDir),
        ]);
    }

    /**
     * @param list<string> $options
     * @return list<string>
     */
    public static function buildOptions(array $options): array
    {
        $result = [];
        foreach ($options as $option) {
            $result[] = '--' . $option;
        }

        return $result;
    }

    /**
     * @param list<string> $includes
     * @return list<string>
     */
    public static function buildIncludes(array $includes = [], ?string $includeFile = null): array
    {
        $result = [];
        foreach ($includes as $include) {
            $result[] = '--include=' . $include;
        }
        if (!empty($includeFile) && file_exists($includeFile) && is_file($includeFile) && is_readable($includeFile)) {
            $result[] = '--include-from=' . $includeFile;
        }

        return $result;
    }

    /**
     * @param list<string> $excludes
     * @return list<string>
     */
    public static function buildExcludes(array $excludes = [], ?string $excludeFile = null): array
    {
        $result = [];
        foreach ($excludes as $exclude) {
            $result[] = '--exclude=' . $exclude;
        }
        if (!empty($excludeFile) && file_exists($excludeFile) && is_file($excludeFile) && is_readable($excludeFile)) {
            $result[] = '--exclude-from=' . $excludeFile;
        }

        return $result;
    }

    /**
     * @param list<string> $filters
     * @return list<string>
     */
    public static function buildFilter(
        array $filters = [],
        ?string $filterFile = null,
        ?string $filterPerDir = null
    ): array {
        $result = [];
        foreach ($filters as $filter) {
            $result[] = '--filter=' . $filter;
        }
        if (!empty($filterFile) && file_exists($filterFile) && is_file($filterFile) && is_readable($filterFile)) {
            $result[] = '--filter=merge ' . $filterFile;
        }
        if (!empty($filterPerDir)) {
            $result[] = '--filter=dir-merge ' . $filterPerDir;
        }

        return $result;
    }
}
