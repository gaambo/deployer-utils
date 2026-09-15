<?php

namespace Gaambo\DeployerUtils\Tests\Integration;

use Gaambo\DeployerUtils\Utils;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Output\OutputInterface;

class UtilsIntegrationTest extends IntegrationTestCase
{
    private MockObject $outputMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outputMock = $this->createMock(OutputInterface::class);
        $this->deployer['output'] = $this->outputMock;
    }

    /** @dataProvider verbosityProvider */
    public function testGetVerbosityArgument(
        bool $isVerbose,
        bool $isVeryVerbose,
        bool $isDebug,
        string $expected
    ): void {
        $this->outputMock->method('isVerbose')->willReturn($isVerbose);
        $this->outputMock->method('isVeryVerbose')->willReturn($isVeryVerbose);
        $this->outputMock->method('isDebug')->willReturn($isDebug);

        $this->assertSame($expected, Utils::getVerbosityArgument());
    }

    public static function verbosityProvider(): array
    {
        return [
            'normal' => [false, false, false, ''],
            'verbose' => [true, false, false, '-v'],
            'very verbose' => [false, true, false, '-vv'],
            'debug' => [false, false, true, '-vvv'],
        ];
    }

    public function testConfigParsingHelpers(): void
    {
        $this->assertSame(['one', 'two'], Utils::parseStringArray(['one', null, 2, 'two']));
        $this->assertSame('value', Utils::parseStringOrNull('value'));
        $this->assertNull(Utils::parseStringOrNull(false));
    }
}
