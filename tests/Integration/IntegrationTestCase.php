<?php

namespace Gaambo\DeployerUtils\Tests\Integration;

use Deployer\Deployer;
use Deployer\Host\Host;
use Deployer\Host\Localhost;
use Deployer\Ssh\RunParams;
use Deployer\Task\Context;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Output\Output;

use function Deployer\quote;

abstract class IntegrationTestCase extends TestCase
{
    protected Deployer $deployer;
    protected MockObject|Input $input;
    protected MockObject|Output $output;
    protected Host $host;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deployer = new Deployer(new Application());
        $this->input = $this->createMock(Input::class);
        $this->output = $this->createMock(Output::class);
        $this->deployer['input'] = $this->input;
        $this->deployer['output'] = $this->output;

        $this->host = new Localhost();
        $this->host->set('deploy_path', '/var/www');
        $this->host->set('bin/php', 'php');
        $this->host->set('current_path', '/var/www/current');
        $this->host->set('release_or_current_path', '/var/www/current');
        $this->deployer->hosts->set('localhost', $this->host);

        Context::push(new Context($this->host));
    }

    protected function tearDown(): void
    {
        Context::pop();
        unset($this->deployer);

        parent::tearDown();
    }

    protected function runCwd(RunParams $options): ?string
    {
        return $options->cwd;
    }

    protected function runShell(RunParams $options): string
    {
        return $options->shell;
    }

    protected function ddevShell(string $path): string
    {
        return 'ddev exec --dir ' . quote($path) . ' bash -s';
    }

    protected function remoteDdevShell(string $hostPath, string $runtimePath): string
    {
        return 'cd ' . quote($hostPath) . ' && ' . $this->ddevShell($runtimePath);
    }
}
