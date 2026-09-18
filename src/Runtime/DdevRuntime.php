<?php

namespace Gaambo\DeployerUtils\Runtime;

use Deployer\Host\Host;
use Deployer\Host\Localhost;

use function Deployer\quote;

/**
 * Runs commands inside a host project's DDEV web container.
 */
class DdevRuntime extends Runtime
{
    protected array $defaults = [
        'ddev_deploy_path' => '/var/www/html',
    ];

    protected function name(): string
    {
        return 'ddev';
    }

    /**
     * Configures paths and the command wrapper as seen inside DDEV.
     *
     * The given host is the internal execution host, not the real source host.
     * Runtime overrides therefore do not change the source host's config.
     */
    protected function configure(Host $executionHost): void
    {
        $executionHost->set('deploy_path', '{{ddev_deploy_path}}');
        if (!$this->sourceHost() instanceof Localhost) {
            // SshClient applies working_path after starting the DDEV shell, so it
            // must be a container path. ProcessRunner applies it before DDEV.
            $executionHost->set('working_path', '{{deploy_path}}');
        }
        $this->configureShell($this->hostDeployPath(), false);
    }

    /**
     * Maps a source-host project path to the matching path inside DDEV.
     *
     * For example, `/srv/site/shared/dump.sql` on a source host with project
     * root `/srv/site` maps to `/var/www/html/shared/dump.sql` in DDEV.
     */
    protected function mapPath(string $hostPath): string
    {
        $hostProjectRoot = $this->normalizeAbsolutePath($this->hostDeployPath(), 'Host deploy path');
        $runtimeProjectRoot = $this->normalizeAbsolutePath(
            $this->executionHost()->get('deploy_path'),
            'DDEV deploy path'
        );
        $relativePath = $this->projectRelativePath($hostPath, $hostProjectRoot);

        return $this->joinPath($runtimeProjectRoot, $relativePath);
    }

    /**
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    protected function prepareRunOptions(array $options): array
    {
        $hostProjectRoot = $this->hostDeployPath();
        $hostCwd = $options['cwd'] ?? $hostProjectRoot;
        $this->configureShell($hostCwd);

        // ProcessRunner needs the host cwd to locate DDEV. SshClient applies cwd
        // after starting DDEV, where a host path would be invalid in the container.
        $options['cwd'] = $this->sourceHost() instanceof Localhost ? $hostProjectRoot : '';
        unset($options['shell']);

        return $options;
    }

    /**
     * Configures DDEV as the execution host's command wrapper.
     *
     * On remote source hosts, DDEV first starts from the host-side project root.
     */
    private function configureShell(string $hostCwd, bool $showDiagnostic = true): void
    {
        $runtimeCwd = $this->mapPath($hostCwd);
        if ($showDiagnostic) {
            $this->diagnostic("cwd: $hostCwd -> $runtimeCwd");
        }
        $shell = 'ddev exec --dir ' . quote($runtimeCwd) . ' bash -s';
        if (!$this->sourceHost() instanceof Localhost) {
            $shell = 'cd ' . quote($this->hostDeployPath()) . ' && ' . $shell;
        }
        $this->executionHost()->setShell($shell);
    }

    /**
     * Validates and normalizes an absolute project path.
     */
    private function normalizeAbsolutePath(string $path, string $description): string
    {
        if (!str_starts_with($path, '/')) {
            throw new \RuntimeException("$description must be an absolute path, got \"$path\".");
        }
        if (array_intersect(explode('/', $path), ['.', '..'])) {
            throw new \RuntimeException("$description must not contain traversal segments, got \"$path\".");
        }

        return rtrim($path, '/') ?: '/';
    }

    /**
     * Returns a source-host path relative to its project root.
     */
    private function projectRelativePath(string $hostPath, string $hostProjectRoot): string
    {
        $hostPath = $this->normalizeAbsolutePath($hostPath, 'Runtime path');
        if ($hostPath === $hostProjectRoot) {
            return '';
        }

        $projectPrefix = $hostProjectRoot === '/' ? '/' : $hostProjectRoot . '/';
        if (!str_starts_with($hostPath, $projectPrefix)) {
            throw new \RuntimeException(
                "Cannot map path \"$hostPath\" into DDEV: it is outside project root \"$hostProjectRoot\"."
            );
        }

        return substr($hostPath, strlen($projectPrefix));
    }

    /**
     * Joins a normalized absolute root with a project-relative path.
     */
    private function joinPath(string $root, string $relativePath): string
    {
        if ($relativePath === '') {
            return $root;
        }

        return ($root === '/' ? '' : $root) . '/' . $relativePath;
    }
}
