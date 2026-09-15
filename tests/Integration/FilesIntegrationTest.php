<?php

namespace Gaambo\DeployerUtils\Tests\Integration;

use Deployer\ProcessRunner\ProcessRunner;
use Deployer\Ssh\RunParams;
use Deployer\Utility\Rsync as DeployerRsync;
use Gaambo\DeployerUtils\Files;
use PHPUnit\Framework\MockObject\MockObject;

class FilesIntegrationTest extends IntegrationTestCase
{
    private MockObject $processRunnerMock;
    private MockObject $rsyncMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processRunnerMock = $this->createMock(ProcessRunner::class);
        $this->rsyncMock = $this->createMock(DeployerRsync::class);
        $this->deployer['processRunner'] = $this->processRunnerMock;
        $this->deployer['rsync'] = $this->rsyncMock;
        $this->host->set('zip_options', '--exclude=*.zip');
    }

    /** @dataProvider pathProvider */
    public function testResolvePath(string $path, string $expected): void
    {
        $this->assertSame($expected, Files::resolvePath($path, '/var/www/current'));
    }

    public static function pathProvider(): array
    {
        return [
            'relative' => ['data/files', '/var/www/current/data/files'],
            'trailing slash' => ['data/files/', '/var/www/current/data/files/'],
            'absolute' => ['/tmp/files', '/tmp/files'],
            'home relative' => ['~/files', '~/files'],
            'empty' => ['', '/var/www/current/'],
        ];
    }

    public function testPushFilesResolvesPathsAndCreatesDestination(): void
    {
        $this->expectCommand('mkdir -p /var/www/current/assets');
        $this->expectRsync(
            '/var/www/current/build/',
            '/var/www/current/assets/',
            ['options' => ['--delete']]
        );

        Files::pushFiles('build', 'assets', ['--delete']);
    }

    public function testPullFilesPreservesAbsolutePaths(): void
    {
        $this->expectCommand('mkdir -p /tmp/local-assets', false);
        $this->expectRsync('/tmp/remote-assets/', '/tmp/local-assets/', ['options' => ['--checksum']]);

        Files::pullFiles('/tmp/remote-assets', '/tmp/local-assets', ['--checksum']);
    }

    public function testPushFileResolvesPathsAndCreatesParent(): void
    {
        $this->expectCommand('mkdir -p /var/www/current/data');
        $this->expectRsync(
            '/var/www/current/data/app.json',
            '/var/www/current/data/app.json',
            ['options' => ['--checksum']]
        );

        Files::pushFile('data/app.json', 'data/app.json', ['--checksum']);
    }

    public function testPullFilePreservesHomeRelativePaths(): void
    {
        $this->expectCommand('mkdir -p ~/data', false);
        $this->expectRsync('~/remote/app.json', '~/data/app.json', ['options' => []]);

        Files::pullFile('~/remote/app.json', '~/data/app.json');
    }

    public function testZipFilesWithTrailingSlashArchivesContents(): void
    {
        $this->expectCommands(function (array $commands): void {
            $this->assertSame('mkdir -p /var/www/backups', $commands[0]);
            $this->assertMatchesRegularExpression(
                '#^cd /var/www/current/ && zip -r app_.*\\.zip \\. --exclude=\\*\\.zip && mv app_.*\\.zip /var/www/backups/app_.*\\.zip$#',
                $commands[1]
            );
        });

        $result = Files::zipFiles('/var/www/current/', '/var/www/backups', 'app');

        $this->assertMatchesRegularExpression('#^/var/www/backups/app_.*\.zip$#', $result);
    }

    public function testZipFilesWithoutTrailingSlashArchivesDirectory(): void
    {
        $this->expectCommands(function (array $commands): void {
            $this->assertSame('mkdir -p /var/www/backups', $commands[0]);
            $this->assertMatchesRegularExpression(
                '#^cd /var/www && zip -r app_.*\\.zip current --exclude=\\*\\.zip && mv app_.*\\.zip /var/www/backups/app_.*\\.zip$#',
                $commands[1]
            );
        });

        Files::zipFiles('/var/www/current', '/var/www/backups', 'app');
    }

    private function expectCommand(string $expectedCommand, bool $sameHost = true): void
    {
        $this->processRunnerMock->expects($this->once())
            ->method('run')
            ->willReturnCallback(function ($host, $command, RunParams $options) use ($expectedCommand, $sameHost) {
                if ($sameHost) {
                    $this->assertSame($this->host, $host);
                }
                $this->assertSame($expectedCommand, $command);
                return '';
            });
    }

    /** @param array<string,mixed> $options */
    private function expectRsync(string $source, string $destination, array $options): void
    {
        $this->rsyncMock->expects($this->once())
            ->method('call')
            ->with($this->anything(), $source, $destination, $options);
    }

    /** @param callable(list<string>):void $assertion */
    private function expectCommands(callable $assertion): void
    {
        $commands = [];
        $this->processRunnerMock->expects($this->exactly(2))
            ->method('run')
            ->willReturnCallback(function ($host, $command, RunParams $options) use (&$commands, $assertion) {
                $commands[] = $command;
                if (count($commands) === 2) {
                    $assertion($commands);
                }
                return '';
            });
    }
}
