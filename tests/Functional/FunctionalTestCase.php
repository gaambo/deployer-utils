<?php

namespace Gaambo\DeployerUtils\Tests\Functional;

use Deployer\Deployer;
use Deployer\Executor\Server;
use Deployer\Host\Host;
use Deployer\Host\Localhost;
use Deployer\ProcessRunner\ProcessRunner;
use Deployer\Ssh\SshClient;
use Deployer\Utility\Rsync;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;

abstract class FunctionalTestCase extends TestCase
{
    protected const RECIPE_PATH = __DIR__ . '/../Fixtures/recipes/common.php';

    protected Deployer $deployer;
    protected ApplicationTester $tester;
    protected Host $localHost;
    protected Host $remoteHost;
    protected string $testDir;
    protected string $localDir;
    protected string $localCurrentDir;
    protected string $remoteDir;
    protected string $remoteCurrentDir;
    protected SshClient|MockObject $sshClient;
    protected ProcessRunner $originalProcessRunner;
    protected ProcessRunner|MockObject $mockedRunner;
    protected Rsync|MockObject|null $rsyncMock = null;

    /** @var array<string, callable|int> */
    private array $mockedCommands = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestDirectories();

        $console = new Application();
        $console->setAutoExit(false);
        $this->tester = new ApplicationTester($console);
        $this->deployer = new Deployer($console);
        $this->deployer->importer->import(static::RECIPE_PATH);
        $this->deployer->init();

        $this->setUpHosts();
        $this->setUpMockedServices();
    }

    private function createTestDirectories(): void
    {
        $this->testDir = __TEMP_DIR__ . '/' . uniqid();
        $this->localDir = $this->testDir . '/local';
        $this->remoteDir = $this->testDir . '/remote';
        $this->localCurrentDir = $this->localDir . '/current';
        $this->remoteCurrentDir = $this->remoteDir . '/current';

        mkdir($this->localCurrentDir, 0755, true);
        mkdir($this->remoteCurrentDir, 0755, true);
    }

    private function setUpHosts(): void
    {
        $this->localHost = new Localhost();
        $this->localHost->set('deploy_path', $this->localDir);
        $this->localHost->set('current_path', $this->localCurrentDir);
        $this->localHost->set('release_or_current_path', $this->localCurrentDir);
        $this->localHost->set('backup_path', $this->localDir . '/backups');
        $this->localHost->set('bin/php', 'php');
        $this->localHost->set('zip_options', '--exclude=*.zip');

        $this->remoteHost = new Localhost('testremote');
        $this->remoteHost->set('deploy_path', $this->remoteDir);
        $this->remoteHost->set('current_path', $this->remoteCurrentDir);
        $this->remoteHost->set('release_or_current_path', $this->remoteCurrentDir);
        $this->remoteHost->set('backup_path', $this->remoteDir . '/backups');
        $this->remoteHost->set('bin/php', 'php');
        $this->remoteHost->set('zip_options', '--exclude=*.zip');

        $this->deployer->hosts->set('localhost', $this->localHost);
        $this->deployer->hosts->set('testremote', $this->remoteHost);
    }

    protected function setUpMockedServices(): void
    {
        $this->sshClient = $this->createMock(SshClient::class);
        $this->deployer['sshClient'] = $this->sshClient;
        $this->deployer['server'] = $this->createMock(Server::class);
        $this->mockedRunner = $this->createMock(ProcessRunner::class);
    }

    /** @param array<string, callable|int> $commandsToMock */
    protected function mockCommands(array $commandsToMock, ?string $hostAlias = null): void
    {
        $this->mockedCommands = array_merge($this->mockedCommands, $commandsToMock);
        $this->mockedRunner->expects($this->any())
            ->method('run')
            ->willReturnCallback(function ($host, $command, $options) use ($hostAlias) {
                if (!$hostAlias || $host->getAlias() === $hostAlias) {
                    foreach ($this->mockedCommands as $pattern => $handler) {
                        if (str_contains($command, $pattern)) {
                            return is_callable($handler) ? $handler($host, $command, $options) : $handler;
                        }
                    }
                }

                return $this->originalProcessRunner->run($host, $command, $options);
            });

        $this->deployer['processRunner'] = function ($container) {
            $this->originalProcessRunner = new ProcessRunner($container['logger']);
            return $this->mockedRunner;
        };
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }

    protected function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    protected function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            return;
        }

        foreach (
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            ) as $item
        ) {
            $relativePath = substr($item->getPathname(), strlen($source) + 1);
            $targetPath = $destination . DIRECTORY_SEPARATOR . $relativePath;
            $item->isDir() ? mkdir($targetPath) : copy($item, $targetPath);
        }
    }

    protected function getFixturePath(string $path): string
    {
        return __FIXTURES__ . '/' . $path;
    }

    /** @param array<string,mixed> $args */
    protected function dep(string $task, ?string $host = 'testremote', array $args = []): int
    {
        $input = [$task];
        if (!empty($host)) {
            $input['selector'] = [$host];
        }
        $input['--file'] = static::RECIPE_PATH;

        return $this->tester->run(array_merge($input, $args), [
            'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            'interactive' => false,
        ]);
    }

    protected function mockRsyncFailure(?string $checkSource = null, ?string $checkDestination = null): void
    {
        $this->rsyncMock = $this->createMock(Rsync::class);
        $this->deployer['rsync'] = $this->rsyncMock;
        $this->rsyncMock->expects($this->once())
            ->method('call')
            ->willReturnCallback(function (Host $host, $source, $destination) use (
                $checkSource,
                $checkDestination
            ) {
                if ($checkSource && $source !== $checkSource) {
                    return;
                }
                if ($checkDestination && $destination !== $checkDestination) {
                    return;
                }
                throw new Exception('File transfer failed');
            });
    }
}
