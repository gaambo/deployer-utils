<?php

namespace Gaambo\DeployerUtils\Tests\Integration;

use Deployer\Host\Host;
use Deployer\Host\Localhost as DeployerLocalhost;
use Deployer\ProcessRunner\ProcessRunner;
use Deployer\Ssh\RunParams;
use Deployer\Ssh\SshClient;
use Deployer\Task\Context;
use Gaambo\DeployerUtils\Localhost;
use Gaambo\DeployerUtils\Runtime\DdevRuntime;
use Gaambo\DeployerUtils\Runtime\Runtime;
use PHPUnit\Framework\MockObject\MockObject;

use function Gaambo\DeployerUtils\runtime;

class RuntimeIntegrationTest extends IntegrationTestCase
{
    private MockObject $processRunnerMock;
    private MockObject $sshClientMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processRunnerMock = $this->createMock(ProcessRunner::class);
        $this->deployer['processRunner'] = $this->processRunnerMock;
        $this->sshClientMock = $this->createMock(SshClient::class);
        $this->deployer['sshClient'] = $this->sshClientMock;
    }

    public function testHelperCreatesSerializableRuntimeWithoutBecomingAHost(): void
    {
        $this->host->set('current_path', '{{deploy_path}}/current');
        $runtimeTemplate = runtime(DdevRuntime::class);

        $this->assertInstanceOf(DdevRuntime::class, $runtimeTemplate);
        $this->assertNotInstanceOf(Host::class, $runtimeTemplate);
        $this->assertSame(
            ['type' => DdevRuntime::class, 'options' => []],
            $runtimeTemplate->jsonSerialize()
        );
        $this->assertSame($this->host, $this->deployer->hosts->get('localhost'));
        $this->assertCount(1, $this->deployer->hosts);

        $this->host->set('runtime', $runtimeTemplate);
        $this->assertSame('/var/www/html', Runtime::getConfig('deploy_path'));
        $this->assertSame('/var/www/html/current', Runtime::getConfig('current_path'));
        $boundRuntime = $this->host->get('runtime');
        $this->assertInstanceOf(DdevRuntime::class, $boundRuntime);
        $this->assertNotSame($runtimeTemplate, $boundRuntime);
        $this->assertJsonStringEqualsJsonString(
            json_encode($runtimeTemplate, JSON_THROW_ON_ERROR),
            json_encode($boundRuntime, JSON_THROW_ON_ERROR)
        );
    }

    public function testFactoryResolvesRuntimeAliasAndOptions(): void
    {
        $this->host->set('runtime', runtime('ddev', [
            'ddev_deploy_path' => '/srv/app',
        ]));

        $this->assertSame('/srv/app', Runtime::getConfig('deploy_path'));
    }

    public function testGlobalRuntimeTemplateBindsIndependentRuntimePerSourceHost(): void
    {
        $runtimeTemplate = runtime('ddev');
        $this->host->set('runtime', $runtimeTemplate);
        $remote = $this->remoteHost();
        $remote->set('runtime', $runtimeTemplate);

        $this->assertSame('/var/www/html', Runtime::getConfig('deploy_path'));
        $this->assertSame(
            '/var/www/html',
            $this->onHost($remote, fn() => Runtime::getConfig('deploy_path'))
        );

        $localRuntime = $this->host->get('runtime');
        $remoteRuntime = $remote->get('runtime');
        $this->assertInstanceOf(DdevRuntime::class, $localRuntime);
        $this->assertInstanceOf(DdevRuntime::class, $remoteRuntime);
        $this->assertNotSame($runtimeTemplate, $localRuntime);
        $this->assertNotSame($runtimeTemplate, $remoteRuntime);
        $this->assertNotSame($localRuntime, $remoteRuntime);
    }

    public function testYamlRuntimeDefinitionIsResolvedForTheActiveHost(): void
    {
        $this->host->set('runtime', [
            'type' => 'ddev',
            'options' => [
                'ddev_deploy_path' => '/srv/app',
            ],
        ]);

        $this->assertSame('/srv/app', Runtime::getConfig('deploy_path'));
        $this->assertInstanceOf(DdevRuntime::class, $this->host->get('runtime'));
    }

    public function testSerializedRuntimeDefinitionExecutesAfterWorkerRoundTrip(): void
    {
        $definition = json_decode(
            json_encode(runtime('ddev'), JSON_THROW_ON_ERROR),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $remote = $this->remoteHost();
        $remote->set('runtime', $definition);
        $this->sshClientMock->expects($this->once())
            ->method('run')
            ->willReturnCallback(function ($executionHost, $command, RunParams $options) {
                $this->assertSame('production:ddev', $executionHost->getAlias());
                $this->assertSame('wp --info', $command);
                $this->assertSame(
                    $this->remoteDdevShell('/srv/www', '/var/www/html/current'),
                    $this->runShell($options)
                );
                return 'WP-CLI 2.12';
            });

        $result = $this->onHost(
            $remote,
            fn() => Runtime::run('{{bin/wp}} --info', ['cwd' => '{{current_path}}'])
        );

        $this->assertSame('WP-CLI 2.12', $result);
        $this->assertInstanceOf(DdevRuntime::class, $remote->get('runtime'));
    }

    public function testRunFallsBackToNativeLocalhostExecution(): void
    {
        $this->processRunnerMock->expects($this->once())
            ->method('run')
            ->willReturnCallback(function ($host, $command, RunParams $options) {
                $this->assertInstanceOf(DeployerLocalhost::class, $host);
                $this->assertSame('php --version', $command);
                $this->assertSame('/var/www/current', $this->runCwd($options));
                return 'PHP 8.3';
            });

        $this->assertSame(
            'PHP 8.3',
            Runtime::run('{{bin/php}} --version', ['cwd' => '{{current_path}}'])
        );
    }

    public function testRunWithoutRuntimeUsesCurrentRemoteHost(): void
    {
        $remote = $this->remoteHost();
        $this->sshClientMock->expects($this->once())
            ->method('run')
            ->willReturnCallback(function ($host, $command, RunParams $options) use ($remote) {
                $this->assertSame($remote, $host);
                $this->assertSame('php --version', $command);
                $this->assertSame('/srv/www/current', $this->runCwd($options));
                return 'PHP 8.3';
            });

        $result = $this->onHost(
            $remote,
            fn() => Runtime::run('{{bin/php}} --version', ['cwd' => '{{current_path}}'])
        );

        $this->assertSame('PHP 8.3', $result);
    }

    public function testRunWithoutRuntimeUsesCurrentNamedLocalhost(): void
    {
        $namedLocalhost = new DeployerLocalhost('testremote');
        $namedLocalhost->set('deploy_path', '/srv/www');
        $namedLocalhost->set('current_path', '/srv/www/current');
        $namedLocalhost->set('bin/php', 'php');
        $this->processRunnerMock->expects($this->once())
            ->method('run')
            ->willReturnCallback(function ($executionHost, $command, RunParams $options) use ($namedLocalhost) {
                $this->assertSame($namedLocalhost, $executionHost);
                $this->assertSame('php --version', $command);
                $this->assertSame('/srv/www/current', $this->runCwd($options));
                return 'PHP 8.3';
            });

        $result = $this->onHost(
            $namedLocalhost,
            fn() => Runtime::run('{{bin/php}} --version', ['cwd' => '{{current_path}}'])
        );

        $this->assertSame('PHP 8.3', $result);
    }

    public function testDdevUsesContainerShellAndHostProjectCwd(): void
    {
        $this->host->set('runtime', runtime(DdevRuntime::class));
        $this->processRunnerMock->expects($this->once())
            ->method('run')
            ->willReturnCallback(function ($host, $command, RunParams $options) {
                $this->assertInstanceOf(DeployerLocalhost::class, $host);
                $this->assertSame('php --version', $command);
                $this->assertSame('/var/www', $this->runCwd($options));
                $this->assertSame($this->ddevShell('/var/www/html/current'), $this->runShell($options));
                return 'PHP 8.3';
            });

        Runtime::run('{{bin/php}} --version', ['cwd' => '/var/www/current']);
    }

    public function testDdevUsesSshForRemoteHost(): void
    {
        $remote = $this->remoteHost();
        $remote->set('runtime', runtime(DdevRuntime::class));
        $this->sshClientMock->expects($this->once())
            ->method('run')
            ->willReturnCallback(function ($host, $command, RunParams $options) use ($remote) {
                $this->assertInstanceOf(Host::class, $host);
                $this->assertNotInstanceOf(DeployerLocalhost::class, $host);
                $this->assertNotSame($remote, $host);
                $this->assertSame('production:ddev', $host->getAlias());
                $this->assertSame('example.com', $host->getHostname());
                $this->assertSame('deploy', $host->getRemoteUser());
                $this->assertSame('wp --info', $command);
                $this->assertSame('', $this->runCwd($options));
                $this->assertSame(
                    $this->remoteDdevShell('/srv/www', '/var/www/html/current'),
                    $this->runShell($options)
                );
                return 'WP-CLI 2.12';
            });

        $result = $this->onHost(
            $remote,
            fn() => Runtime::run('{{bin/wp}} --info', ['cwd' => '{{current_path}}'])
        );

        $this->assertSame('WP-CLI 2.12', $result);
    }

    public function testRemoteExecutionHostInheritsSshConnectionConfig(): void
    {
        $remote = $this->remoteHost();
        $remote->setPort(2222);
        $remote->setIdentityFile('/home/deploy/.ssh/id_ed25519');
        $remote->setForwardAgent(true);
        $remote->setSshArguments(['-o StrictHostKeyChecking=yes']);
        $remote->set('runtime', runtime('ddev'));
        $this->sshClientMock->expects($this->once())
            ->method('run')
            ->willReturnCallback(function ($executionHost) {
                $this->assertSame(2222, $executionHost->getPort());
                $this->assertSame('/home/deploy/.ssh/id_ed25519', $executionHost->getIdentityFile());
                $this->assertTrue($executionHost->getForwardAgent());
                $this->assertSame(
                    ['-o StrictHostKeyChecking=yes'],
                    $executionHost->getSshArguments()
                );
                return '';
            });

        $this->onHost($remote, fn() => Runtime::run('wp --info'));
    }

    public function testRemoteWithinKeepsNestedCommandsInSameRuntime(): void
    {
        $remote = $this->remoteHost();
        $remote->set('runtime', runtime('ddev'));
        $executionHost = null;
        $this->sshClientMock->expects($this->exactly(2))
            ->method('run')
            ->willReturnCallback(function ($currentExecutionHost, $command, RunParams $options) use (&$executionHost) {
                $executionHost ??= $currentExecutionHost;
                $this->assertSame($executionHost, $currentExecutionHost);
                $this->assertSame(
                    $this->remoteDdevShell('/srv/www', '/var/www/html'),
                    $this->runShell($options)
                );
                $this->assertContains($command, ['wp option get home', 'wp option get siteurl']);
                return '';
            });

        $this->onHost($remote, function (): void {
            Runtime::within(function (): void {
                Runtime::within(fn() => \Deployer\run('wp option get home'));
                \Deployer\run('wp option get siteurl');
            });
        });

        $this->assertFalse(Runtime::isActive());
    }

    public function testRemoteWpBinaryTestUsesContainerWorkingPath(): void
    {
        $remote = $this->remoteHost();
        $remote->set('bin/php', '/usr/bin/php');
        $remote->set('bin/wp', function () {
            if (\Deployer\test('[ -f {{deploy_path}}/.dep/wp-cli.phar ]')) {
                return '{{bin/php}} {{deploy_path}}/.dep/wp-cli.phar';
            }

            return 'wp';
        });
        $remote->set('runtime', runtime(DdevRuntime::class));
        $executionHost = null;
        $this->sshClientMock->expects($this->exactly(2))
            ->method('run')
            ->willReturnCallback(function ($host, $command, RunParams $options) use (&$executionHost) {
                $executionHost ??= $host;
                $this->assertSame($executionHost, $host);
                $this->assertNotInstanceOf(DeployerLocalhost::class, $host);
                $this->assertSame(
                    $this->remoteDdevShell('/srv/www', '/var/www/html/current'),
                    $this->runShell($options)
                );

                if (str_starts_with($command, 'if [ -f /var/www/html/.dep/wp-cli.phar ]; then echo +')) {
                    $this->assertSame('/var/www/html', $this->runCwd($options));
                    preg_match('/echo (\+\w+); fi$/', $command, $matches);
                    return $matches[1];
                }

                $this->assertSame('/usr/bin/php /var/www/html/.dep/wp-cli.phar --info', $command);
                $this->assertSame('', $this->runCwd($options));
                return 'WP-CLI 2.12';
            });

        $result = $this->onHost(
            $remote,
            fn() => Runtime::run('{{bin/wp}} --info', ['cwd' => '{{current_path}}'])
        );

        $this->assertSame('WP-CLI 2.12', $result);
    }

    public function testDdevMapsExplicitAndConfiguredPaths(): void
    {
        $this->host->set('runtime', runtime(DdevRuntime::class, [
            'ddev_deploy_path' => '/srv/app',
        ]));

        $this->assertSame('/srv/app', Runtime::path('/var/www'));
        $this->assertSame('/srv/app/data/dump.sql', Runtime::path('/var/www/data/dump.sql'));
        $this->assertSame('/srv/app', Runtime::getConfig('deploy_path'));
    }

    public function testNativeRuntimePathFallsBackToHostPath(): void
    {
        $this->assertSame('/var/www/data/dump.sql', Runtime::path('/var/www/data/dump.sql'));
        $this->assertSame('/var/www/current', Runtime::getConfig('current_path'));
    }

    public function testWithinWithoutRuntimeRunsCallbackOnce(): void
    {
        $calls = 0;

        $result = Runtime::within(function () use (&$calls) {
            $calls++;
        });

        $this->assertNull($result);
        $this->assertSame(1, $calls);
    }

    public function testDdevReportsPathMappingAtVerboseOutput(): void
    {
        $this->host->set('runtime', runtime(DdevRuntime::class));
        $this->output->method('isVerbose')->willReturn(true);
        $this->output->expects($this->once())
            ->method('writeln')
            ->with('[runtime:ddev] path: /var/www/data/dump.sql -> /var/www/html/data/dump.sql');

        Runtime::path('/var/www/data/dump.sql');
    }

    public function testDdevRejectsPathsOutsideProject(): void
    {
        $this->host->set('runtime', runtime(DdevRuntime::class));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('outside project root "/var/www"');

        Runtime::path('/tmp/dump.sql');
    }

    public function testDdevRejectsRelativePaths(): void
    {
        $this->host->set('runtime', runtime(DdevRuntime::class));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Runtime path must be an absolute path');

        Runtime::path('data/dump.sql');
    }

    public function testDdevRejectsPathTraversal(): void
    {
        $this->host->set('runtime', runtime(DdevRuntime::class));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must not contain traversal segments');

        Runtime::path('/var/www/../tmp/dump.sql');
    }

    public function testInheritedCallableConfigResolvesAndCachesOnRuntime(): void
    {
        $calls = 0;
        $this->host->set('bin/php', function () use (&$calls) {
            $calls++;
            return \Deployer\which('php');
        });
        $this->host->set('runtime', runtime(DdevRuntime::class));
        $this->processRunnerMock->expects($this->exactly(3))
            ->method('run')
            ->willReturnCallback(function ($host, $command, RunParams $options) {
                if ($host !== $this->host) {
                    $this->assertInstanceOf(DeployerLocalhost::class, $host);
                    $this->assertSame('/var/www', $this->runCwd($options));
                    $this->assertSame($this->ddevShell('/var/www/html'), $this->runShell($options));
                }

                return match (str_replace("'", '', $command)) {
                    'command -v php || which php || type -p php' => '/runtime/bin/php',
                    '/runtime/bin/php --version' => 'PHP 8.3',
                };
            });

        $this->assertSame('PHP 8.3', Runtime::run('{{bin/php}} --version'));
        $this->assertSame('/runtime/bin/php', Runtime::getConfig('bin/php'));
        $this->assertSame(1, $calls);
        $this->assertSame('/runtime/bin/php', $this->host->get('bin/php'));
        $this->assertSame(2, $calls);
    }

    public function testDistinctBinaryCallbacksResolveThroughRuntime(): void
    {
        foreach (['composer', 'npm', 'php'] as $binary) {
            $this->host->set("bin/$binary", fn() => \Deployer\which($binary));
        }
        $this->host->set('runtime', runtime(DdevRuntime::class));
        $this->processRunnerMock->expects($this->exactly(3))
            ->method('run')
            ->willReturnCallback(function ($host, $command, RunParams $options) {
                $this->assertInstanceOf(DeployerLocalhost::class, $host);
                $this->assertNotSame($this->host, $host);
                $this->assertSame($this->ddevShell('/var/www/html'), $this->runShell($options));
                preg_match("/command -v '?(\w+)'?/", $command, $matches);
                return "/runtime/bin/{$matches[1]}";
            });

        foreach (['composer', 'npm', 'php'] as $binary) {
            $this->assertSame("/runtime/bin/$binary", Runtime::getConfig("bin/$binary"));
        }
    }

    public function testWithinUsesRuntimeAndRestoresNestedContexts(): void
    {
        $this->host->set('runtime', runtime(DdevRuntime::class));
        $this->processRunnerMock->expects($this->exactly(3))
            ->method('run')
            ->willReturnCallback(function ($host, $command, RunParams $options) {
                static $call = 0;
                $expectedRuntime = [true, true, false];
                $expectedCommands = ['php app.php one', 'php app.php two', 'php app.php three'];
                $this->assertInstanceOf(DeployerLocalhost::class, $host);
                $isRuntimeExecution = str_starts_with($this->runShell($options), 'ddev exec');
                $this->assertSame($expectedRuntime[$call], $isRuntimeExecution);
                $this->assertSame($expectedCommands[$call], $command);
                $call++;
                return '';
            });

        Runtime::within(function () {
            Runtime::within(fn() => Localhost::run('{{bin/php}} app.php one', ['cwd' => '/var/www/current']));
            Localhost::run('{{bin/php}} app.php two');
        });
        Localhost::run('{{bin/php}} app.php three');

        $this->assertFalse(Runtime::isActive());
    }

    public function testWithinRestoresContextAfterFailure(): void
    {
        $this->host->set('runtime', runtime(DdevRuntime::class));
        $this->processRunnerMock->expects($this->once())
            ->method('run')
            ->willThrowException(new \RuntimeException('command failed'));

        try {
            Runtime::within(fn() => Localhost::run('{{bin/php}} app.php'));
        } catch (\RuntimeException) {
        }

        $this->assertFalse(Runtime::isActive());
        $this->assertSame($this->host, \Deployer\currentHost());
    }

    public function testInvalidRuntimeConfigurationIsRejected(): void
    {
        $this->host->set('runtime', [
            'type' => 'unknown',
        ]);
        $this->expectException(\InvalidArgumentException::class);

        Runtime::run('php --version');
    }

    private function remoteHost(): Host
    {
        $host = new Host('production');
        $host->setHostname('example.com');
        $host->setRemoteUser('deploy');
        $host->set('deploy_path', '/srv/www');
        $host->set('current_path', '/srv/www/current');
        $host->set('bin/php', 'php');
        $host->set('bin/wp', 'wp');

        return $host;
    }

    private function onHost(Host $host, callable $callback): mixed
    {
        Context::push(new Context($host));
        try {
            return $callback();
        } finally {
            Context::pop();
        }
    }
}
