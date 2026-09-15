<?php

namespace Gaambo\DeployerUtils\Tests\Integration;

use Deployer\Host\Localhost as DeployerLocalhost;
use Deployer\ProcessRunner\ProcessRunner;
use Deployer\Ssh\RunParams;
use Deployer\Task\Context;
use Gaambo\DeployerUtils\Localhost;

class LocalhostIntegrationTest extends IntegrationTestCase
{
    public function testGetConfig(): void
    {
        $this->host->set('test_key', 'test_value');

        $this->assertSame('test_value', Localhost::getConfig('test_key'));
        $this->assertSame('fallback', Localhost::getConfig('missing_key', 'fallback'));
    }

    public function testDynamicConfigUsesLocalhostContext(): void
    {
        $this->host->set('test_key', 'local_value');
        $this->host->set('dynamic_key', fn() => \Deployer\get('test_key'));
        $remoteHost = new DeployerLocalhost('remote');
        $remoteHost->set('test_key', 'remote_value');
        Context::push(new Context($remoteHost));

        try {
            $this->assertSame('local_value', Localhost::getConfig('dynamic_key'));
            $this->assertSame('/var/www/current', Localhost::parse('{{current_path}}'));
        } finally {
            Context::pop();
        }
    }

    public function testGetReturnsConfiguredLocalhost(): void
    {
        $this->assertSame($this->host, Localhost::get());
    }

    public function testRunUsesLocalhostAndNamedOptions(): void
    {
        $runner = $this->createMock(ProcessRunner::class);
        $this->deployer['processRunner'] = $runner;
        $runner->expects($this->once())
            ->method('run')
            ->willReturnCallback(function ($host, $command, RunParams $options) {
                $this->assertInstanceOf(DeployerLocalhost::class, $host);
                $this->assertSame('php --version', $command);
                $this->assertSame('/var/www/current', $options->cwd);
                $this->assertSame(['APP_ENV' => 'test'], $options->env);
                return 'PHP 8.3';
            });

        $result = Localhost::run('{{bin/php}} --version', [
            'cwd' => '/var/www/current',
            'env' => ['APP_ENV' => 'test'],
        ]);

        $this->assertSame('PHP 8.3', $result);
    }
}
