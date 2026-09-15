<?php

namespace Gaambo\DeployerUtils\Tests\Integration;

use Deployer\Exception\ConfigurationException;
use Deployer\ProcessRunner\ProcessRunner;
use Deployer\Ssh\RunParams;
use Gaambo\DeployerUtils\NPM;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Output\OutputInterface;

class NPMIntegrationTest extends IntegrationTestCase
{
    private MockObject $processRunnerMock;
    private MockObject $outputMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processRunnerMock = $this->createMock(ProcessRunner::class);
        $this->outputMock = $this->createMock(OutputInterface::class);
        $this->deployer['processRunner'] = $this->processRunnerMock;
        $this->deployer['output'] = $this->outputMock;
        $this->deployer->config->set('bin/npm', 'npm');
    }

    public function testRunCommandScriptAndInstall(): void
    {
        $this->expectCommands([
            'cd /var/www/current && npm exec tool --flag',
            'cd /var/www/current && npm run-script build --env=prod',
            'cd /var/www/current && npm install --ignore-scripts',
        ]);

        $this->assertSame('command output', NPM::runCommand('/var/www/current', 'exec', 'tool --flag'));
        $this->assertSame('command output', NPM::runScript('/var/www/current', 'build', '--env=prod'));
        $this->assertSame('command output', NPM::runInstall('/var/www/current', '--ignore-scripts'));
    }

    /** @dataProvider verbosityProvider */
    public function testRunCommandAddsNpmVerbosity(
        bool $verbose,
        bool $veryVerbose,
        bool $debug,
        string $flag
    ): void {
        $this->outputMock->method('isVerbose')->willReturn($verbose);
        $this->outputMock->method('isVeryVerbose')->willReturn($veryVerbose);
        $this->outputMock->method('isDebug')->willReturn($debug);
        $this->expectCommands(["cd /var/www/current && npm install $flag"]);

        NPM::runInstall('/var/www/current');
    }

    public static function verbosityProvider(): array
    {
        return [
            'verbose' => [true, false, false, '-d'],
            'very verbose' => [false, true, false, '-dd'],
            'debug' => [false, false, true, '-ddd'],
        ];
    }

    public function testRunCommandKeepsComplexArgumentsAndPath(): void
    {
        $path = '/var/www/app with spaces';
        $arguments = '--save-dev "package@1.0.0" --save-exact';
        $this->expectCommands(["cd $path && npm install $arguments"]);

        NPM::runInstall($path, $arguments);
    }

    public function testRunCommandUsesCustomBinary(): void
    {
        $this->deployer->config->set('bin/npm', '/usr/local/bin/npm');
        $this->expectCommands(['cd /var/www/current && /usr/local/bin/npm install']);

        NPM::runInstall('/var/www/current');
    }

    public function testRunCommandRejectsMissingBinary(): void
    {
        $this->deployer->config->set('bin/npm', null);
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Config option "bin/npm" does not exist');

        NPM::runInstall('/var/www/current');
    }

    /** @param list<string> $expectedCommands */
    private function expectCommands(array $expectedCommands): void
    {
        $this->processRunnerMock->expects($this->exactly(count($expectedCommands)))
            ->method('run')
            ->willReturnCallback(function ($host, $command, RunParams $options) use (&$expectedCommands) {
                $this->assertSame($this->host, $host);
                $this->assertSame(array_shift($expectedCommands), $command);
                return 'command output';
            });
    }
}
