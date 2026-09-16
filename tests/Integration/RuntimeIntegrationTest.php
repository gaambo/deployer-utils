<?php

namespace Gaambo\DeployerUtils\Tests\Integration;

use Deployer\Host\Localhost as DeployerLocalhost;
use Deployer\ProcessRunner\ProcessRunner;
use Deployer\Ssh\RunParams;
use Gaambo\DeployerUtils\Localhost;
use Gaambo\DeployerUtils\Runtime\DdevRuntimeHost;
use Gaambo\DeployerUtils\Runtime\Runtime;
use PHPUnit\Framework\MockObject\MockObject;

use function Gaambo\DeployerUtils\runtime;

class RuntimeIntegrationTest extends IntegrationTestCase
{
    private MockObject $processRunnerMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processRunnerMock = $this->createMock(ProcessRunner::class);
        $this->deployer['processRunner'] = $this->processRunnerMock;
    }

    public function testFactoryCreatesLazyRuntimeAndConfiguredRuntimeBindsIt(): void
    {
        $this->host->set('current_path', '{{deploy_path}}/current');
        $runtimeFactory = runtime(DdevRuntimeHost::class);

        $this->assertIsCallable($runtimeFactory);
        $this->assertSame($this->host, $this->deployer->hosts->get('localhost'));
        $this->assertCount(1, $this->deployer->hosts);

        $this->host->set('runtime', $runtimeFactory);
        $runtime = $this->host->get('runtime');
        $this->assertInstanceOf(DeployerLocalhost::class, $runtime);
        $this->assertSame('/var/www/html', Runtime::getConfig('deploy_path'));
        $this->assertSame('/var/www/html/current', Runtime::getConfig('current_path'));
    }

    public function testFactoryResolvesRuntimeAliasAndOptions(): void
    {
        $this->host->set('runtime', runtime('ddev', [
            'ddev_deploy_path' => '/srv/app',
        ]));

        $this->assertSame('/srv/app', Runtime::getConfig('deploy_path'));
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

    public function testDdevUsesContainerShellAndHostProjectCwd(): void
    {
        $this->host->set('runtime', runtime(DdevRuntimeHost::class));
        $runtime = $this->host->get('runtime');
        $this->processRunnerMock->expects($this->once())
            ->method('run')
            ->willReturnCallback(function ($host, $command, RunParams $options) use ($runtime) {
                $this->assertSame($runtime, $host);
                $this->assertSame('php --version', $command);
                $this->assertSame('/var/www', $this->runCwd($options));
                $this->assertSame($this->ddevShell('/var/www/html/current'), $this->runShell($options));
                return 'PHP 8.3';
            });

        Runtime::run('{{bin/php}} --version', ['cwd' => '/var/www/current']);
    }

    public function testDdevMapsExplicitAndConfiguredPaths(): void
    {
        $this->host->set('runtime', runtime(DdevRuntimeHost::class, [
            'ddev_deploy_path' => '/srv/app',
        ]));

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
        $this->host->set('runtime', runtime(DdevRuntimeHost::class));
        $this->output->method('isVerbose')->willReturn(true);
        $this->output->expects($this->once())
            ->method('writeln')
            ->with('[runtime:ddev] path: /var/www/data/dump.sql -> /var/www/html/data/dump.sql');

        Runtime::path('/var/www/data/dump.sql');
    }

    public function testDdevRejectsPathsOutsideProject(): void
    {
        $this->host->set('runtime', runtime(DdevRuntimeHost::class));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('outside project root "/var/www"');

        Runtime::path('/tmp/dump.sql');
    }

    public function testDdevRejectsRelativePaths(): void
    {
        $this->host->set('runtime', runtime(DdevRuntimeHost::class));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Runtime path must be an absolute path');

        Runtime::path('data/dump.sql');
    }

    public function testInheritedCallableConfigResolvesAndCachesOnRuntime(): void
    {
        $calls = 0;
        $this->host->set('bin/php', function () use (&$calls) {
            $calls++;
            return \Deployer\which('php');
        });
        $this->host->set('runtime', runtime(DdevRuntimeHost::class));
        $runtime = $this->host->get('runtime');
        $this->processRunnerMock->expects($this->exactly(3))
            ->method('run')
            ->willReturnCallback(function ($host, $command, RunParams $options) use ($runtime) {
                if ($host === $runtime) {
                    $this->assertSame($this->ddevShell('/var/www/html'), $this->runShell($options));
                } else {
                    $this->assertSame($this->host, $host);
                }

                return match (str_replace("'", '', $command)) {
                    'command -v php || which php || type -p php' => '/runtime/bin/php',
                    '/runtime/bin/php --version' => 'PHP 8.3',
                };
            });

        $this->assertSame('PHP 8.3', Runtime::run('{{bin/php}} --version'));
        $this->assertSame('/runtime/bin/php', $runtime->get('bin/php'));
        $this->assertSame(1, $calls);
        $this->assertSame('/runtime/bin/php', $this->host->get('bin/php'));
        $this->assertSame(2, $calls);
    }

    public function testDistinctBinaryCallbacksResolveThroughRuntime(): void
    {
        foreach (['composer', 'npm', 'php'] as $binary) {
            $this->host->set("bin/$binary", fn() => \Deployer\which($binary));
        }
        $this->host->set('runtime', runtime(DdevRuntimeHost::class));
        $runtime = $this->host->get('runtime');
        $this->processRunnerMock->expects($this->exactly(3))
            ->method('run')
            ->willReturnCallback(function ($host, $command, RunParams $options) use ($runtime) {
                $this->assertSame($runtime, $host);
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
        $this->host->set('runtime', runtime(DdevRuntimeHost::class));
        $runtime = $this->host->get('runtime');
        $this->processRunnerMock->expects($this->exactly(3))
            ->method('run')
            ->willReturnCallback(function ($host, $command) use ($runtime) {
                static $call = 0;
                $expectedHosts = [$runtime, $runtime, null];
                $expectedCommands = ['php app.php one', 'php app.php two', 'php app.php three'];
                if ($expectedHosts[$call] === null) {
                    $this->assertInstanceOf(DeployerLocalhost::class, $host);
                } else {
                    $this->assertSame($expectedHosts[$call], $host);
                }
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
        $this->host->set('runtime', runtime(DdevRuntimeHost::class));
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
}
