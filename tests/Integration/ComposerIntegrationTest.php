<?php

namespace Gaambo\DeployerUtils\Tests\Integration;

use Deployer\Exception\ConfigurationException;
use Deployer\ProcessRunner\ProcessRunner;
use Deployer\Ssh\RunParams;
use Gaambo\DeployerUtils\Composer;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Output\OutputInterface;

class ComposerIntegrationTest extends IntegrationTestCase
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
        $this->deployer->config->set('composer_action', 'install');
        $this->deployer->config->set('composer_options', '--no-dev --no-interaction');
        $this->deployer->config->set('bin/composer', 'composer');
        $this->deployer->config->set('bin/php', 'php');
    }

    public function testRunDefault(): void
    {
        $this->expectCommands(['cd /var/www/current && composer install --no-dev --no-interaction']);

        $this->assertSame('command output', Composer::runDefault('/var/www/current'));
    }

    public function testRunCommandAndScript(): void
    {
        $this->expectCommands([
            'cd /var/www/current && composer require package/name:1.0.0',
            'cd /var/www/current && composer run-script post-install --env=prod',
        ]);

        $this->assertSame(
            'command output',
            Composer::runCommand('/var/www/current', 'require', 'package/name:1.0.0')
        );
        $this->assertSame(
            'command output',
            Composer::runScript('/var/www/current', 'post-install', '--env=prod')
        );
    }

    public function testInstallWithSudoAndCustomBinaryName(): void
    {
        $this->expectCommands([
            'sudo mkdir -p /usr/local/bin',
            'sudo cd /usr/local/bin && curl -sS https://getcomposer.org/installer | php',
            'sudo mv /usr/local/bin/composer.phar /usr/local/bin/composer',
        ]);

        $this->assertSame('/usr/local/bin/composer', Composer::install('/usr/local/bin', 'composer', true));
    }

    public function testInstallWithDefaultNameDoesNotMoveBinary(): void
    {
        $this->expectCommands([
            'mkdir -p /usr/local/bin',
            'cd /usr/local/bin && curl -sS https://getcomposer.org/installer | php',
        ]);

        $this->assertSame('/usr/local/bin/composer.phar', Composer::install('/usr/local/bin'));
    }

    /** @dataProvider verbosityProvider */
    public function testRunCommandAddsVerbosity(
        bool $verbose,
        bool $veryVerbose,
        bool $debug,
        string $flag
    ): void {
        $this->outputMock->method('isVerbose')->willReturn($verbose);
        $this->outputMock->method('isVeryVerbose')->willReturn($veryVerbose);
        $this->outputMock->method('isDebug')->willReturn($debug);
        $this->expectCommands(["cd /var/www/current && composer install $flag"]);

        Composer::runCommand('/var/www/current', 'install');
    }

    public static function verbosityProvider(): array
    {
        return [
            'verbose' => [true, false, false, '-v'],
            'very verbose' => [false, true, false, '-vv'],
            'debug' => [false, false, true, '-vvv'],
        ];
    }

    public function testRunCommandUsesCustomBinary(): void
    {
        $this->deployer->config->set('bin/composer', '/usr/local/bin/composer');
        $this->expectCommands(['cd /var/www/current && /usr/local/bin/composer install --no-dev']);

        Composer::runCommand('/var/www/current', 'install', '--no-dev');
    }

    public function testRunCommandRejectsMissingBinary(): void
    {
        $this->deployer->config->set('bin/composer', null);
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Config option "bin/composer" does not exist');

        Composer::runCommand('/var/www/current', 'install');
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
